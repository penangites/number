<?php

declare(strict_types=1);

namespace Penangites\Number;

use InvalidArgumentException;
use Penangites\Number\Support\Decimal;

/**
 * An immutable percentage stored as an exact decimal ratio string (1 = 100%,
 * "0.1234" = 12.34%). All arithmetic uses bcmath, so the value never drifts the
 * way a float would, and precision is unbounded — the only limit on a persisted
 * value is the database column the consuming app chooses. String and integer
 * inputs are preserved exactly; float inputs are rounded to 14 significant
 * digits before being stored.
 */
readonly class Percentage
{
    /**
     * @param  numeric-string  $ratio
     */
    private function __construct(private string $ratio) {}

    /**
     * Create a percentage from a ratio, where 1 represents 100%.
     *
     * Strings and integers are preserved exactly. Floats are rounded to 14
     * significant digits; use a string when every supplied digit must be kept.
     *
     * @throws InvalidArgumentException When $ratio is not a valid finite decimal.
     */
    public static function fromRatio(float|int|string $ratio): self
    {
        return new self(Decimal::normalize(Decimal::stringify($ratio)));
    }

    /**
     * Create a percentage from its human-readable percent value.
     *
     * For example, "12.34" represents 12.34% and is stored as the ratio
     * "0.1234". Strings and integers are preserved exactly. Floats are rounded
     * to 14 significant digits; use a string when every digit must be kept.
     *
     * @throws InvalidArgumentException When $percent is not a valid finite decimal.
     */
    public static function fromPercent(float|int|string $percent): self
    {
        $percent = Decimal::normalize(Decimal::stringify($percent));
        $scale = Decimal::scaleOf($percent) + 2;

        return new self(Decimal::normalize(bcdiv($percent, '100', $scale)));
    }

    // -------------------------------------------------------------------------
    // Views
    // -------------------------------------------------------------------------

    /**
     * @return numeric-string
     */
    public function toRatio(): string
    {
        return $this->ratio;
    }

    public function toPercent(): string
    {
        return Decimal::normalize(bcmul($this->ratio, '100', Decimal::scaleOf($this->ratio)));
    }

    /**
     * Return an approximate float representation of the ratio.
     *
     * The conversion may lose precision or exceed the float range. Use
     * toRatio() when the exact value is required.
     */
    public function toFloat(): float
    {
        return (float) $this->ratio;
    }

    /**
     * @return array{ratio: string, percent: string}
     */
    public function toArray(): array
    {
        return [
            'ratio' => $this->ratio,
            'percent' => $this->toPercent(),
        ];
    }

    // -------------------------------------------------------------------------
    // Arithmetic (immutable)
    // -------------------------------------------------------------------------

    public function add(self $percentage): self
    {
        $scale = max(Decimal::scaleOf($this->ratio), Decimal::scaleOf($percentage->ratio));

        return new self(Decimal::normalize(bcadd($this->ratio, $percentage->ratio, $scale)));
    }

    public function subtract(self $percentage): self
    {
        $scale = max(Decimal::scaleOf($this->ratio), Decimal::scaleOf($percentage->ratio));

        return new self(Decimal::normalize(bcsub($this->ratio, $percentage->ratio, $scale)));
    }

    public function negate(): self
    {
        return new self(Decimal::normalize(bcmul($this->ratio, '-1', Decimal::scaleOf($this->ratio))));
    }

    // -------------------------------------------------------------------------
    // Comparison
    // -------------------------------------------------------------------------

    public function compareTo(self $percentage): int
    {
        $scale = max(Decimal::scaleOf($this->ratio), Decimal::scaleOf($percentage->ratio));

        return bccomp($this->ratio, $percentage->ratio, $scale);
    }

    public function equals(self $percentage): bool
    {
        return $this->compareTo($percentage) === 0;
    }

    public function greaterThan(self $percentage): bool
    {
        return $this->compareTo($percentage) > 0;
    }

    public function greaterThanOrEqual(self $percentage): bool
    {
        return $this->compareTo($percentage) >= 0;
    }

    public function lessThan(self $percentage): bool
    {
        return $this->compareTo($percentage) < 0;
    }

    public function lessThanOrEqual(self $percentage): bool
    {
        return $this->compareTo($percentage) <= 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->ratio, '0', Decimal::scaleOf($this->ratio)) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->ratio, '0', Decimal::scaleOf($this->ratio)) > 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->ratio, '0', Decimal::scaleOf($this->ratio)) < 0;
    }
}
