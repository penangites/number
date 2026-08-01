<?php

declare(strict_types=1);

namespace Penangites\Number;

use InvalidArgumentException;
use JsonSerializable;
use Penangites\Number\Support\Decimal;
use Stringable;

/**
 * An immutable, chainable number stored as an exact decimal string. All
 * arithmetic uses bcmath: addition, subtraction, multiplication, and the
 * percentage operations are exact at any precision; only divide() and round()
 * discard digits, and they do so at an explicit scale with an explicit
 * {@see RoundingMode}. Operations return new instances; the original is never
 * mutated. String and integer inputs are preserved exactly; a float is stored
 * as the shortest decimal that reads back as that same double, so whatever
 * precision it had on arrival is kept — including any it had already lost.
 *
 *     Number::of('19.90')->tax(Percentage::fromPercent('6'))->toString(); // "21.094"
 */
readonly class Number implements JsonSerializable, Stringable
{
    /**
     * @param  numeric-string  $value
     */
    private function __construct(private string $value) {}

    /**
     * Create a number from a decimal value.
     *
     * Strings and integers are preserved exactly. A float is stored as the
     * shortest decimal that reads back as the same double — nothing is added
     * and nothing is discarded, so a value that reached this call intact stays
     * intact, and one that had already drifted shows that drift rather than
     * hiding it. Use a string to keep a decimal exact end to end.
     *
     * @throws InvalidArgumentException When $value is not a valid finite decimal.
     */
    public static function of(float|int|string $value): self
    {
        return new self(Decimal::normalize(Decimal::stringify($value)));
    }

    // -------------------------------------------------------------------------
    // Views
    // -------------------------------------------------------------------------

    /**
     * The canonical decimal string (no trailing zeros, no leading '+').
     *
     * @return numeric-string
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Return an approximate float representation.
     *
     * The conversion may lose precision or exceed the float range. Use
     * toString() when the exact value is required.
     */
    public function toFloat(): float
    {
        return (float) $this->value;
    }

    /**
     * The value as an integer, only when the conversion is lossless.
     *
     * @throws InvalidArgumentException When the value has a fractional part or
     *                                  does not fit in a PHP integer.
     */
    public function toInt(): int
    {
        if (Decimal::scaleOf($this->value) > 0) {
            throw new InvalidArgumentException("[{$this->value}] has a fractional part; round() it before converting to an integer.");
        }

        if (bccomp($this->value, (string) PHP_INT_MAX) > 0 || bccomp($this->value, (string) PHP_INT_MIN) < 0) {
            throw new InvalidArgumentException("[{$this->value}] does not fit in an integer.");
        }

        return (int) $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    // -------------------------------------------------------------------------
    // Arithmetic (immutable)
    // -------------------------------------------------------------------------

    public function add(self|float|int|string $addend): self
    {
        $addend = self::wrap($addend);
        $scale = max(Decimal::scaleOf($this->value), Decimal::scaleOf($addend->value));

        return new self(Decimal::normalize(bcadd($this->value, $addend->value, $scale)));
    }

    public function subtract(self|float|int|string $subtrahend): self
    {
        $subtrahend = self::wrap($subtrahend);
        $scale = max(Decimal::scaleOf($this->value), Decimal::scaleOf($subtrahend->value));

        return new self(Decimal::normalize(bcsub($this->value, $subtrahend->value, $scale)));
    }

    /**
     * Exact at any precision: the product of a and b decimal places never
     * needs more than a + b decimal places.
     */
    public function multiply(self|float|int|string $factor): self
    {
        $factor = self::wrap($factor);
        $scale = Decimal::scaleOf($this->value) + Decimal::scaleOf($factor->value);

        return new self(Decimal::normalize(bcmul($this->value, $factor->value, $scale)));
    }

    /**
     * Division is the one operation that can produce endless digits, so it
     * takes an explicit scale and rounding mode. Rounding uses the exact
     * remainder — every mode is correct even for digits beyond the scale.
     *
     * The scale is the maximum number of digits after the decimal point.
     * Trailing zeros are removed from the returned canonical value.
     *
     * @throws InvalidArgumentException When $scale is negative or $divisor is zero.
     */
    public function divide(self|float|int|string $divisor, int $scale = 12, RoundingMode $mode = RoundingMode::HalfAwayFromZero): self
    {
        self::assertScale($scale);

        $divisor = self::wrap($divisor);

        if ($divisor->isZero()) {
            throw new InvalidArgumentException('Cannot divide by zero.');
        }

        return new self(self::roundedDivision($this->value, $divisor->value, $scale, $mode));
    }

    /**
     * Round to at most $scale digits after the decimal point.
     *
     * Trailing zeros are removed from the returned canonical value, so rounding
     * to two decimal places may produce "1.2" rather than "1.20".
     *
     * @throws InvalidArgumentException When $scale is negative.
     */
    public function round(int $scale = 0, RoundingMode $mode = RoundingMode::HalfAwayFromZero): self
    {
        self::assertScale($scale);

        return new self(self::roundedDivision($this->value, '1', $scale, $mode));
    }

    public function negate(): self
    {
        return new self(Decimal::normalize(bcmul($this->value, '-1', Decimal::scaleOf($this->value))));
    }

    public function abs(): self
    {
        return $this->isNegative() ? $this->negate() : $this;
    }

    // -------------------------------------------------------------------------
    // Percentage calculations (exact — chain round() when you need a scale)
    // -------------------------------------------------------------------------

    /**
     * The portion this percentage represents of the value (10% of 200 is 20).
     */
    public function percentOf(Percentage $percentage): self
    {
        $ratio = $percentage->toRatio();
        $scale = Decimal::scaleOf($this->value) + Decimal::scaleOf($ratio);

        return new self(Decimal::normalize(bcmul($this->value, $ratio, $scale)));
    }

    public function increaseBy(Percentage $percentage): self
    {
        return $this->add($this->percentOf($percentage));
    }

    public function decreaseBy(Percentage $percentage): self
    {
        return $this->subtract($this->percentOf($percentage));
    }

    /**
     * The value after adding tax/surcharge of this percentage (alias of increaseBy).
     */
    public function tax(Percentage $percentage): self
    {
        return $this->increaseBy($percentage);
    }

    /**
     * The value after applying a discount of this percentage (alias of decreaseBy).
     */
    public function discount(Percentage $percentage): self
    {
        return $this->decreaseBy($percentage);
    }

    // -------------------------------------------------------------------------
    // Comparison
    // -------------------------------------------------------------------------

    public function compareTo(self|float|int|string $other): int
    {
        $other = self::wrap($other);
        $scale = max(Decimal::scaleOf($this->value), Decimal::scaleOf($other->value));

        return bccomp($this->value, $other->value, $scale);
    }

    public function equals(self|float|int|string $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function greaterThan(self|float|int|string $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function greaterThanOrEqual(self|float|int|string $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    public function lessThan(self|float|int|string $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function lessThanOrEqual(self|float|int|string $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->value, '0', Decimal::scaleOf($this->value)) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->value, '0', Decimal::scaleOf($this->value)) > 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->value, '0', Decimal::scaleOf($this->value)) < 0;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private static function wrap(self|float|int|string $value): self
    {
        return $value instanceof self ? $value : self::of($value);
    }

    private static function assertScale(int $scale): void
    {
        if ($scale < 0) {
            throw new InvalidArgumentException('Scale must be zero or greater.');
        }
    }

    /**
     * Divide $a by $b and round the quotient to $scale decimal places per
     * $mode. bcdiv() truncates toward zero, so the exact remainder tells us
     * whether — and by how much — the true quotient goes past the truncation,
     * which makes every mode correct even when the discarded digits lie beyond
     * any fixed working scale.
     *
     * @param  numeric-string  $a
     * @param  numeric-string  $b  must be non-zero
     * @return numeric-string
     */
    private static function roundedDivision(string $a, string $b, int $scale, RoundingMode $mode): string
    {
        $truncated = bcdiv($a, $b, $scale);

        $productScale = Decimal::scaleOf($truncated) + Decimal::scaleOf($b);
        $remainderScale = max($productScale, Decimal::scaleOf($a));
        $remainder = bcsub($a, bcmul($truncated, $b, $productScale), $remainderScale);

        if (bccomp($remainder, '0', $remainderScale) === 0) {
            return Decimal::normalize($truncated);
        }

        $signOfA = bccomp($a, '0', Decimal::scaleOf($a));
        $signOfB = bccomp($b, '0', Decimal::scaleOf($b));
        $quotientSign = $signOfA * $signOfB;

        $unit = $scale === 0 ? '1' : bcdiv('1', bcpow('10', (string) $scale), $scale);
        $step = $quotientSign >= 0 ? $unit : bcsub('0', $unit, $scale);
        $stepped = bcadd($truncated, $step, $scale);

        $rounded = match ($mode) {
            RoundingMode::Down => $truncated,
            RoundingMode::Up => $stepped,
            RoundingMode::Ceiling => $quotientSign > 0 ? $stepped : $truncated,
            RoundingMode::Floor => $quotientSign < 0 ? $stepped : $truncated,
            RoundingMode::HalfAwayFromZero => self::pastHalf($remainder, $b, $scale, $remainderScale) ? $stepped : $truncated,
        };

        return Decimal::normalize($rounded);
    }

    /**
     * Whether the discarded fraction is at least half of one unit at $scale:
     * f >= 0.5  ⟺  2·|remainder|·10^scale >= |divisor|.
     *
     * @param  numeric-string  $remainder
     * @param  numeric-string  $divisor
     */
    private static function pastHalf(string $remainder, string $divisor, int $scale, int $remainderScale): bool
    {
        $absRemainder = bccomp($remainder, '0', $remainderScale) < 0 ? bcsub('0', $remainder, $remainderScale) : $remainder;
        $divisorScale = Decimal::scaleOf($divisor);
        $absDivisor = bccomp($divisor, '0', $divisorScale) < 0 ? bcsub('0', $divisor, $divisorScale) : $divisor;

        $doubled = bcmul(bcmul($absRemainder, '2', $remainderScale), bcpow('10', (string) $scale), $remainderScale);

        return bccomp($doubled, $absDivisor, max($remainderScale, $divisorScale)) >= 0;
    }
}
