<?php

declare(strict_types=1);

use Penangites\Number\Percentage;

it('constructs an exact ratio from string, int and float', function (): void {
    expect(Percentage::fromRatio('0.1234')->toRatio())->toBe('0.1234');
    expect(Percentage::fromRatio(1)->toRatio())->toBe('1');
    expect(Percentage::fromRatio(1.5)->toRatio())->toBe('1.5');
});

it('constructs from percent by dividing by 100 exactly', function (): void {
    expect(Percentage::fromPercent('12.3456')->toRatio())->toBe('0.123456');
    expect(Percentage::fromPercent(10)->toRatio())->toBe('0.1');
    expect(Percentage::fromPercent('100')->toRatio())->toBe('1');
});

it('keeps precision far beyond a float (exact string)', function (): void {
    $ratio = '0.123456789012345678'; // 18 decimals — past double precision

    expect(Percentage::fromRatio($ratio)->toRatio())->toBe($ratio);
});

it('exposes percent and float views', function (): void {
    $percentage = Percentage::fromRatio('0.1234');

    expect($percentage->toPercent())->toBe('12.34');
    expect($percentage->toFloat())->toBe(0.1234);
});

it('serialises to array', function (): void {
    expect(Percentage::fromRatio('0.1234')->toArray())->toBe([
        'ratio' => '0.1234',
        'percent' => '12.34',
    ]);
});

it('casts to string and serialises to JSON as the exact ratio', function (): void {
    $rate = Percentage::fromPercent('6');

    expect((string) $rate)->toBe('0.06');
    expect(json_encode($rate))->toBe('"0.06"');

    // Every property is private, so without JsonSerializable a nested rate
    // encoded to {} and vanished from the payload with no error.
    //
    expect(json_encode(['rate' => $rate]))->toBe('{"rate":"0.06"}');

    // The ratio is serialised, not the percent, because it is the one view a
    // single constructor reads back.
    //
    expect(Percentage::fromRatio((string) $rate)->equals($rate))->toBeTrue();
});

it('adds and subtracts exactly with no float drift', function (): void {
    expect(Percentage::fromRatio('0.1')->add(Percentage::fromRatio('0.2'))->toRatio())->toBe('0.3');
    expect(Percentage::fromRatio('0.3')->subtract(Percentage::fromRatio('0.1'))->toRatio())->toBe('0.2');

    // combine two rates of different scale
    //
    expect(Percentage::fromRatio('0.06')->add(Percentage::fromPercent('10'))->toPercent())->toBe('16');
});

it('negates', function (): void {
    expect(Percentage::fromRatio('0.06')->negate()->toRatio())->toBe('-0.06');
    expect(Percentage::fromRatio('-0.06')->negate()->toRatio())->toBe('0.06');
});

it('compares by value regardless of trailing zeros', function (): void {
    expect(Percentage::fromRatio('0.10')->equals(Percentage::fromRatio('0.1')))->toBeTrue();

    $a = Percentage::fromRatio('0.06');
    $b = Percentage::fromRatio('0.10');

    expect($a->compareTo($b))->toBe(-1);
    expect($a->lessThan($b))->toBeTrue();
    expect($a->lessThanOrEqual($a))->toBeTrue();
    expect($b->greaterThan($a))->toBeTrue();
    expect($b->greaterThanOrEqual($b))->toBeTrue();
});

it('reports sign', function (): void {
    expect(Percentage::fromRatio('0')->isZero())->toBeTrue();
    expect(Percentage::fromRatio('0.0')->isZero())->toBeTrue();
    expect(Percentage::fromRatio('0.01')->isPositive())->toBeTrue();
    expect(Percentage::fromRatio('-0.01')->isNegative())->toBeTrue();
});

it('normalizes on construction', function (): void {
    expect(Percentage::fromRatio('.5')->toRatio())->toBe('0.5');
    expect(Percentage::fromRatio('+0.5')->toRatio())->toBe('0.5');
    expect(Percentage::fromRatio('0.5000')->toRatio())->toBe('0.5');
    expect(Percentage::fromRatio('-0')->toRatio())->toBe('0');
    expect(Percentage::fromRatio(' 0.5 ')->toRatio())->toBe('0.5');
});

it('rejects non-numeric input', function (): void {
    Percentage::fromRatio('abc');
})->throws(InvalidArgumentException::class);
