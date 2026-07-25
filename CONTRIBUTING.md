# Contributing

Contributions are welcome and will be fully credited.

## Pull requests

- **Discuss big changes first.** Open an issue before investing time in a
  large feature, so we can agree on the direction.
- **One feature per pull request.** Smaller PRs are reviewed and merged faster.
- **Add tests.** Every behavior change needs a Pest test that fails without it.
- **Keep the API small.** This package deliberately does less than it could —
  exact values, one obvious way per operation, no formatting or locale
  concerns. New methods need a strong reason to exist.

## Running the checks

```bash
composer install
composer test
```

`composer test` runs everything CI runs: Pint (code style), PHPStan at level
10 — the maximum, and it stays there — and the Pest suite. To auto-fix code
style:

```bash
composer lint
```

## Commit messages

We use [Conventional Commits](https://www.conventionalcommits.org/) — e.g.
`feat: …`, `fix: …`, with `!` for breaking changes. Add a scope only when it
points at a distinct area, e.g. `feat(percentage): …`.
