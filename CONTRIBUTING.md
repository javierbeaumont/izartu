# Contributing to izartu

Thanks for considering a contribution.

## Scope

The project's core constraint is **zero runtime dependencies**: no framework, no Composer packages in production, no
build step. A production install is the files in this repository, PHP and MySQL. Any change that adds a runtime
dependency will be rejected, and so will one that reimplements in PHP what the database already does well.

izartu is also personal first: bookmarks are private by default and the public layer is scoped to your own instance.
Features that assume a central service, or that only make sense for a large multi-user site, are out of scope.

Open an issue before sending a non-trivial pull request: the direction is decided outside the tracker, so a change can
be well built and still not belong here.

## Reporting bugs

Use the issue templates. Include the commit you are running, how you installed it (Docker or manual) and your PHP and
MySQL versions.

For security-sensitive issues, see [SECURITY.md](SECURITY.md).

## Pull requests

- Discuss non-trivial changes in an issue first.
- One concern per commit, with a **single-line** imperative subject.
- Sign your commits (`git commit -S`) if you can.
- Update `README.md` in the same commit when behaviour, configuration or requirements change.
- Add or adjust tests for what you change.

## Working on it

Everything runs in Docker through `make` targets; `make help` lists them. You need a `.env` with the database
credentials (see the README).

```sh
make hooks   # install the pre-commit hooks, once
make up      # start the stack
make check   # style, static analysis and the test suite (what CI runs)
```

The hooks run the whole toolchain before a commit lands, and the suite before a push, so `make check` rarely has
surprises left. The individual targets are `make style` / `make fmt` (PHP-CS-Fixer, [PER Coding
Style](https://www.php-fig.org/per/coding-style/)), `make analyse` (PHPStan at level 10) and `make test` (PHPUnit).

Code style beyond the fixer: `//` for comments, `/** */` docblocks on classes, methods, properties and constants (they
are published as API documentation), and comments only where the *why* is not obvious.

## Continuous integration

Every push and pull request runs the same toolchain in CI, across every supported PHP and MySQL version (see
`.github/workflows/`). A green local `make check` is usually enough to keep it green.

## License

izartu is licensed under the GNU AGPLv3, and contributions are accepted under the same licence. Every source file
carries the licence header; the hooks add it for you.
