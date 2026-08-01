# Changelog

All notable changes to `penangites/number` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `Percentage` now implements `JsonSerializable` and `Stringable`, so it
  serialises to its exact ratio string. It previously encoded to `{}` — every
  property is private — which dropped the rate from a JSON payload with no
  error.

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
