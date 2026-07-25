# Number

[![Latest Version on Packagist](https://img.shields.io/packagist/v/penangites/number.svg?style=flat-square)](https://packagist.org/packages/penangites/number)
[![Tests](https://img.shields.io/github/actions/workflow/status/penangites/number/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/penangites/number/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/penangites/number.svg?style=flat-square)](https://packagist.org/packages/penangites/number)

An immutable, chainable `Number` value object for PHP with exact bcmath
arithmetic — plus a `Percentage` companion. No runtime dependencies beyond
bcmath, no floating-point arithmetic, no drift.

```php
use Penangites\Number\Number;
use Penangites\Number\Percentage;

Number::of('19.90')
    ->tax(Percentage::fromPercent('6'))
    ->toString(); // "21.094" — exact, no rounding until you ask

Number::of('19.90')
    ->tax(Percentage::fromPercent('6'))
    ->round(2)
    ->toString(); // "21.09"
```

After construction, addition, subtraction, multiplication and percentage
operations are **exact at any precision**. Only `divide()` and `round()` discard
digits — and they take an explicit scale and rounding mode, so precision is
never lost silently.

For Laravel integration (Eloquent casts, validation), see
[`penangites/laravel-number`](https://github.com/penangites/laravel-number).

## Installation

Requires PHP 8.3+ and the `bcmath` extension.

```bash
composer require penangites/number
```

## Usage

### Creating numbers

```php
Number::of('19.90'); // strings are exact — prefer them
Number::of(100);     // integers are exact
Number::of(1.5);     // floats accepted, rounded to 14 significant digits
```

Use strings for decimal values when every supplied digit must be preserved.

### Reading values

```php
$n = Number::of('19.90');

$n->toString(); // "19.9" — canonical decimal string
(string) $n;    // same
$n->toFloat();  // 19.9
$n->toInt();    // throws — has a fractional part; round() first
json_encode($n); // '"19.9"' — serialises as the exact string
```

`toInt()` only converts losslessly: it throws on fractional values instead of
silently truncating.

### Arithmetic

Every operation returns a new instance — a `Number` never changes. Operands
can be another `Number`, an int, a string, or a float.

```php
Number::of('0.1')->add('0.2')->toString();      // "0.3" — no float drift
Number::of('0.3')->subtract('0.1')->toString(); // "0.2"
Number::of('1.5')->multiply('2.5')->toString(); // "3.75" — always exact
Number::of('1.5')->negate()->toString();        // "-1.5"
Number::of('-1.5')->abs()->toString();          // "1.5"
```

### Division and rounding

Division is the one operation that can produce endless digits, so it takes an
explicit scale (default 12) and a `RoundingMode` (default half away from zero):

```php
use Penangites\Number\RoundingMode;

Number::of(1)->divide(3)->toString();                        // "0.333333333333"
Number::of(10)->divide(3, 2)->toString();                    // "3.33"
Number::of(10)->divide(3, 2, RoundingMode::Up)->toString();  // "3.34"

Number::of('2.5')->round()->toString();     // "3"  — half away from zero
Number::of('1.005')->round(2)->toString();  // "1.01" — floats get this wrong
```

Modes: `HalfAwayFromZero` (default), `Up`, `Down`, `Ceiling`, `Floor`. Rounding
uses the exact remainder, so every mode is correct even when the discarded
digits lie far beyond the scale.

Values are returned in canonical form without trailing zeros. The scale limits
decimal precision; it does not format or pad the result:

```php
Number::of('1.204')->round(2)->toString(); // "1.2", not "1.20"
```

### Percentages

```php
$rate = Percentage::fromPercent('6');   // 6%
$rate = Percentage::fromRatio('0.06');  // same thing (1 = 100%)

$rate->toPercent(); // "6"
$rate->toRatio();   // "0.06"
```

`Percentage` supports exact `add`, `subtract`, `negate`, comparisons and sign
checks of its own. Apply one to a `Number`:

```php
$price = Number::of('19.90');
$tax = Percentage::fromPercent('6');

$price->percentOf($tax)->toString();  // "1.194" — the tax amount
$price->tax($tax)->toString();        // "21.094" — price + tax
$price->discount($tax)->toString();   // "18.706" — price - discount
```

`increaseBy` / `decreaseBy` are the general-purpose names for `tax` /
`discount`. All percentage operations are exact — chain `->round()` when you
need to limit decimal precision.

A complete price calculation can stay exact until the final rounding step:

```php
$total = Number::of('199.99')
    ->discount(Percentage::fromPercent('15'))
    ->tax(Percentage::fromPercent('8'))
    ->round(2);

$total->toString(); // "183.59"
```

### Comparison

```php
$a = Number::of('1.5');

$a->equals('1.50');       // true — compared by value
$a->greaterThan(1);       // true
$a->lessThanOrEqual($a);  // true
$a->compareTo(2);         // -1
$a->isZero();
$a->isPositive();
$a->isNegative();
```

## Recipes

### Spelling a number out in words

Deliberately not part of this package — PHP's `intl` extension already does it
in every CLDR locale, so use it directly:

```php
$number = Number::of(100);

$english = new NumberFormatter('en', NumberFormatter::SPELLOUT);
$chinese = new NumberFormatter('zh', NumberFormatter::SPELLOUT);

$english->format($number->toFloat()); // "one hundred"
$chinese->format($number->toFloat()); // "一百"
```

## Testing

```bash
composer test
```

This runs code style checks (Pint), static analysis (PHPStan at level 10, the
maximum) and the Pest test suite.

## Changelog

See [CHANGELOG](CHANGELOG.md) for what has changed recently.

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover a security issue, please read
[our security policy](SECURITY.md) — do not use the issue tracker.

## License

MIT — see [LICENSE](LICENSE).
