# Changelog

All notable changes to `penangites/number` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Decimal strings may use scientific notation — `Number::of('1.0E-5')` and
  both `Percentage` constructors now read the form a float cast, a JSON decode
  or a database driver hands back, expanding it positionally with no loss.
  Exponents beyond ±100000 are rejected, since a short string would otherwise
  expand to gigabytes.

### Changed

- **Breaking.** A float is now stored as the shortest decimal that reads back
  as the same double, instead of being rounded to 14 significant digits. The
  old rule could not tell arithmetic noise from the value itself, so above
  roughly 1e13 it silently returned a *different* number:
  `Number::of(123456789012345.0)` gave `123456789012340`, five short, even
  though the double held the value exactly. Such values are now kept.
- The visible cost is that drift which happened before the call is no longer
  concealed: `Number::of(0.1 + 0.2)` is `"0.30000000000000004"`, not `"0.3"`.
  Binary64 cannot represent `0.1` or `0.2`, so that sum never was `0.3` — pass
  a string when a decimal must stay exact.

### Fixed

- `Percentage` now implements `JsonSerializable`, serialising to its exact
  ratio string. It previously encoded to `{}` — every property is private —
  which dropped the rate from a JSON payload with no error. It remains
  deliberately non-`Stringable`: `toPercent()` and `toRatio()` are both
  plausible casts, so neither is implicit.

## [1.0.0] - 2026-07-26

### Added

- `Number` — an immutable, chainable decimal value object with exact bcmath
  arithmetic, explicit-scale division and rounding, and percentage operations
  (`tax`, `discount`, `percentOf`, `increaseBy`, `decreaseBy`).
- `Percentage` — an immutable percentage stored as an exact decimal ratio.
- `RoundingMode` — `HalfAwayFromZero` (default), `Up`, `Down`, `Ceiling`,
  `Floor`.

[Unreleased]: https://github.com/penangites/number/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/penangites/number/releases/tag/v1.0.0
