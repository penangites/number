# Number

An immutable, chainable `Number` value object for PHP with exact bcmath
arithmetic — plus a `Percentage` companion. No dependencies, no floats,
no drift.

```php
use Penangites\Number\Number;
use Penangites\Number\Percentage;

Number::of('19.90')
    ->tax(Percentage::fromPercent('6'))
    ->toString(); // "21.094"
```

> **Status:** work in progress — not yet released.

## Requirements

- PHP 8.3+
- `bcmath` extension

## License

MIT — see [LICENSE](LICENSE).
