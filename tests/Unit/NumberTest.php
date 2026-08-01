<?php

declare(strict_types=1);

use Penangites\Number\Number;
use Penangites\Number\Percentage;
use Penangites\Number\RoundingMode;

it('constructs an exact value from string, int and float', function (): void {
    expect(Number::of('19.90')->toString())->toBe('19.9');
    expect(Number::of(100)->toString())->toBe('100');
    expect(Number::of(1.5)->toString())->toBe('1.5');
});

it('normalizes on construction', function (): void {
    expect(Number::of('.5')->toString())->toBe('0.5');
    expect(Number::of('+0.5')->toString())->toBe('0.5');
    expect(Number::of('0.5000')->toString())->toBe('0.5');
    expect(Number::of('-0')->toString())->toBe('0');
    expect(Number::of(' 1.5 ')->toString())->toBe('1.5');
});

it('accepts scientific notation, the form a float cast hands back', function (): void {
    expect(Number::of('1e5')->toString())->toBe('100000');
    expect(Number::of('1.0E-5')->toString())->toBe('0.00001');
    expect(Number::of((string) 1.0E-5)->toString())->toBe('0.00001');
    expect(Number::of('-2.5e-3')->toString())->toBe('-0.0025');
    expect(Number::of('+1.5E+2')->toString())->toBe('150');
    expect(Number::of('1e0')->toString())->toBe('1');
});

it('expands a mantissa carrying more than one integer digit', function (): void {
    // %e renders exactly one, but a hand-written string need not — reading the
    // point position as a fixed 1 would silently make these 1250 and 12.5.
    //
    expect(Number::of('12.5e3')->toString())->toBe('12500');
    expect(Number::of('125e1')->toString())->toBe('1250');
    expect(Number::of('.5e1')->toString())->toBe('5');
    expect(Number::of('0.5e1')->toString())->toBe('5');
});

it('keeps every digit of an exponent string, past what a float could hold', function (): void {
    expect(Number::of('1.234567890123456789e5')->toString())->toBe('123456.7890123456789');
});

it('rejects a malformed exponent', function (string $value): void {
    expect(fn () => Number::of($value))->toThrow(InvalidArgumentException::class);
})->with(['1e', '1e+', 'e5', '1e5.5', '1ee5', '1e 5']);

it('refuses an exponent that would expand beyond memory', function (string $value): void {
    // Without a ceiling these allocate gigabytes inside str_repeat() and die
    // with a fatal error no caller can catch — from twelve bytes of input.
    //
    expect(fn () => Number::of($value))->toThrow(InvalidArgumentException::class);
})->with(['1e1000000000', '1e-1000000000', '1e99999999999999999999', '1e100001']);

it('expands right up to the exponent ceiling', function (): void {
    expect(strlen(Number::of('1e100000')->toString()))->toBe(100001);
});

it('renders floats at fourteen significant digits regardless of magnitude', function (): void {
    expect(Number::of(0.1 + 0.2)->toString())->toBe('0.3'); // one-ulp noise hidden
    expect(Number::of(123456.789)->toString())->toBe('123456.789');
    expect(Number::of(9876543.21)->toString())->toBe('9876543.21');
    expect(Number::of(-123456.789)->toString())->toBe('-123456.789');
    expect(Number::of(0.00001)->toString())->toBe('0.00001');
    expect(Number::of(1E+20)->toString())->toBe('100000000000000000000');
});

it('rounds large floats at fourteen significant digits instead of exposing binary noise', function (): void {
    expect(Number::of(1.234567890123456e20)->toString())->toBe('123456789012350000000');
});

it('keeps the smallest subnormal float non-zero', function (): void {
    expect(Number::of(5.0e-324)->toString())->toBe('0.'.str_repeat('0', 323).'49406564584125');
});

it('keeps precision far beyond a float', function (): void {
    $value = '0.123456789012345678901234'; // 24 decimals

    expect(Number::of($value)->toString())->toBe($value);
});

it('rejects non-numeric input', function (): void {
    Number::of('abc');
})->throws(InvalidArgumentException::class);

it('rejects non-finite floats without emitting a warning', function (float $value): void {
    // failOnWarning in phpunit.xml turns any coercion warning into a failure.
    expect(fn () => Number::of($value))->toThrow(InvalidArgumentException::class);
})->with([NAN, INF, -INF]);

it('exposes float and integer views', function (): void {
    expect(Number::of('0.25')->toFloat())->toBe(0.25);
    expect(Number::of('42')->toInt())->toBe(42);
    expect(Number::of('-42')->toInt())->toBe(-42);
});

it('refuses a lossy integer conversion', function (): void {
    Number::of('1.5')->toInt();
})->throws(InvalidArgumentException::class);

it('refuses an integer conversion that overflows', function (): void {
    Number::of((string) PHP_INT_MAX)->add(1)->toInt();
})->throws(InvalidArgumentException::class);

it('casts to string and serialises to JSON as the exact string', function (): void {
    expect((string) Number::of('19.90'))->toBe('19.9');
    expect(json_encode(Number::of('19.90')))->toBe('"19.9"');
});

it('adds and subtracts exactly with no float drift', function (): void {
    expect(Number::of('0.1')->add('0.2')->toString())->toBe('0.3');
    expect(Number::of('0.3')->subtract('0.1')->toString())->toBe('0.2');
    expect(Number::of(100)->add(Number::of('0.001'))->toString())->toBe('100.001');
});

it('multiplies exactly at combined scale', function (): void {
    expect(Number::of('1.5')->multiply('2.5')->toString())->toBe('3.75');
    expect(Number::of('0.1')->multiply('0.3')->toString())->toBe('0.03');
    expect(Number::of('19.90')->multiply(3)->toString())->toBe('59.7');
});

it('divides at an explicit scale with half-away-from-zero by default', function (): void {
    expect(Number::of(10)->divide(4)->toString())->toBe('2.5');
    expect(Number::of(1)->divide(3)->toString())->toBe('0.333333333333');
    expect(Number::of(2)->divide(3)->toString())->toBe('0.666666666667');
    expect(Number::of(1)->divide(8, 2)->toString())->toBe('0.13'); // 0.125 rounds away
});

it('divides per the requested rounding mode', function (): void {
    expect(Number::of(10)->divide(3, 2, RoundingMode::Down)->toString())->toBe('3.33');
    expect(Number::of(10)->divide(3, 2, RoundingMode::Up)->toString())->toBe('3.34');
    expect(Number::of(10)->divide(3, 2, RoundingMode::Ceiling)->toString())->toBe('3.34');
    expect(Number::of(10)->divide(3, 2, RoundingMode::Floor)->toString())->toBe('3.33');
});

it('divides negatives per sign-aware modes', function (): void {
    expect(Number::of(-10)->divide(3, 2, RoundingMode::Down)->toString())->toBe('-3.33');
    expect(Number::of(-10)->divide(3, 2, RoundingMode::Up)->toString())->toBe('-3.34');
    expect(Number::of(-10)->divide(3, 2, RoundingMode::Ceiling)->toString())->toBe('-3.33');
    expect(Number::of(-10)->divide(3, 2, RoundingMode::Floor)->toString())->toBe('-3.34');
    expect(Number::of(-1)->divide(8, 2)->toString())->toBe('-0.13'); // half away from zero
});

it('detects a remainder beyond the scale when rounding a division', function (): void {
    // The leftover sits 14 digits out — Up must still see it.
    expect(Number::of('1.00000000000001')->divide(1, 2, RoundingMode::Up)->toString())->toBe('1.01');
    expect(Number::of('1.00000000000001')->divide(1, 2, RoundingMode::Down)->toString())->toBe('1');
});

it('refuses to divide by zero', function (): void {
    Number::of(1)->divide('0.000');
})->throws(InvalidArgumentException::class);

it('refuses a negative scale', function (): void {
    Number::of(1)->divide(3, -1);
})->throws(InvalidArgumentException::class);

it('rounds to a scale without float artefacts', function (): void {
    expect(Number::of('2.5')->round()->toString())->toBe('3');
    expect(Number::of('-2.5')->round()->toString())->toBe('-3');
    expect(Number::of('1.005')->round(2)->toString())->toBe('1.01'); // floats get this wrong
    expect(Number::of('2.4')->round(0, RoundingMode::Up)->toString())->toBe('3');
    expect(Number::of('2.6')->round(0, RoundingMode::Down)->toString())->toBe('2');
    expect(Number::of('-2.4')->round(0, RoundingMode::Ceiling)->toString())->toBe('-2');
    expect(Number::of('-2.4')->round(0, RoundingMode::Floor)->toString())->toBe('-3');
    expect(Number::of('5')->round(2)->toString())->toBe('5');
});

it('negates and reports absolutes', function (): void {
    expect(Number::of('1.5')->negate()->toString())->toBe('-1.5');
    expect(Number::of('-1.5')->negate()->toString())->toBe('1.5');
    expect(Number::of('-1.5')->abs()->toString())->toBe('1.5');
    expect(Number::of('1.5')->abs()->toString())->toBe('1.5');
});

it('applies percentages exactly', function (): void {
    expect(Number::of(200)->percentOf(Percentage::fromPercent(10))->toString())->toBe('20');
    expect(Number::of('19.90')->percentOf(Percentage::fromPercent('6'))->toString())->toBe('1.194');
    expect(Number::of(100)->increaseBy(Percentage::fromPercent('6'))->toString())->toBe('106');
    expect(Number::of(100)->decreaseBy(Percentage::fromPercent('15'))->toString())->toBe('85');
});

it('chains tax and discount as in the readme', function (): void {
    expect(Number::of(100)->discount(Percentage::fromRatio('0.15'))->toString())->toBe('85');
    expect(Number::of('19.90')->tax(Percentage::fromPercent('6'))->toString())->toBe('21.094');
    expect(Number::of('19.90')->tax(Percentage::fromPercent('6'))->round(2)->toString())->toBe('21.09');
});

it('compares by value regardless of trailing zeros', function (): void {
    expect(Number::of('0.10')->equals('0.1'))->toBeTrue();
    expect(Number::of('2')->equals(Number::of('2.000')))->toBeTrue();

    $a = Number::of('1.5');
    $b = Number::of(2);

    expect($a->compareTo($b))->toBe(-1);
    expect($a->lessThan($b))->toBeTrue();
    expect($a->lessThanOrEqual($a))->toBeTrue();
    expect($b->greaterThan($a))->toBeTrue();
    expect($b->greaterThanOrEqual($b))->toBeTrue();
});

it('reports sign', function (): void {
    expect(Number::of('0.0')->isZero())->toBeTrue();
    expect(Number::of('0.01')->isPositive())->toBeTrue();
    expect(Number::of('-0.01')->isNegative())->toBeTrue();
});

it('never mutates the original instance', function (): void {
    $original = Number::of('100');

    $original->add(1)->multiply(2)->discount(Percentage::fromPercent(50));

    expect($original->toString())->toBe('100');
});
