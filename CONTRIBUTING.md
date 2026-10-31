# Contributing to izartu

## Scope

The project's core constraint is **zero runtime dependencies**: no framework, no Composer packages in production, no
build step. A production install is the files in this repository, PHP and MySQL. Any change that adds a runtime
dependency will be rejected, and so will one that reimplements in PHP what the database already does well.

izartu is also personal first: bookmarks are private by default and the public layer is scoped to your own instance.
Features that assume a central service, or that only make sense for a large multi-user site, are out of scope.

## Reporting bugs

Use the [bug report template](.github/ISSUE_TEMPLATE/bug_report.md): it asks for what a report needs. Report a
vulnerability privately instead, as [SECURITY.md](SECURITY.md) explains.

## Pull requests

- Open an issue before a non-trivial change: the direction is decided outside the tracker, so a change can be well built
  and still not belong here.
- One concern per commit, with a **single-line** imperative subject.
- Add or adjust tests for what you change, and keep `make check` green.
- Update [`README.md`](README.md) in the same commit when behaviour, configuration or requirements change.
- Sign your commits (`git commit -S`) if you can.

## Development

You need Docker with the Compose plugin, `make` and, for the hooks, [pre-commit](https://pre-commit.com), plus a `.env`
with the database credentials ([README, "Installation"](README.md#installation)). Every task has a `make` target
(`make help` lists them), and the hooks and CI call the same targets, so each command is defined once:

```sh
make deps    # install the dev dependencies (PHPUnit, PHPStan, PHP-CS-Fixer) into vendor/, once
make hooks   # install the hooks, once
make up      # start the app and the database in the background, at http://localhost:8080
make check   # style, static analysis and the three test tiers
```

The hooks ([`.pre-commit-config.yaml`](.pre-commit-config.yaml)) check before each commit the code style and static
analysis, the licence headers, spelling, the shell scripts, YAML, workflows and Dockerfile, whitespace and line length,
and leaked secrets. Before a push they run the tests: unit and integration when PHP changed, e2e unless only Markdown
did. The PHP hooks use the Docker toolchain, so they need the `.env` too.

### Tests

* **Unit** ([`tests/Unit/`](tests/Unit/)): the code in isolation, with no database.
* **Integration** ([`tests/Integration/`](tests/Integration/)): the real components wired together (MySQL, the
  filesystem), against the test databases `izartu_test` and `izartu_migration_test`, never real data. Docker creates
  both, with [`docker/create-test-db.sh`](docker/create-test-db.sh), on the first start of a new database volume; a
  volume older than one of them needs `docker compose down -v` once, which also deletes the development database.
  `make test-integration` migrates `izartu_test` first.
* **E2E** ([`tests/E2E/`](tests/E2E/)): [`smoke.sh`](tests/E2E/smoke.sh), a shell script with curl, drives the running
  app over HTTP, for what only real HTTP shows: the home page up, a login keeping its session across requests, and a tag
  URL with an encoded slash reaching the app. It runs the published image, built from the tracked files as they are (or
  the one `IMAGE` names, local or in a registry), on a stack of its own (separate Compose project, database and
  credentials, no published port), removed whatever the outcome; it ignores `.env`, so the app runs on its default
  settings, and needs no `make deps`.

```sh
make test              # all three
```

or

```sh
make test-unit         # unit only; starts no database
make test-integration  # integration only
make test-e2e          # e2e only, on a throwaway stack
```

### Static analysis and code style

PHPStan runs at level 10, the highest, over `private/` and `public/`, for every supported PHP version
([`phpstan.dist.neon`](phpstan.dist.neon)). The code follows
[PER Coding Style](https://www.php-fig.org/per/coding-style/), with lines of up to 120 characters, enforced by
PHP-CS-Fixer.

```sh
make analyse  # static analysis
make style    # check the code style
make fmt      # apply it
```

Beyond the fixer: `/** */` docblocks on classes, methods, properties and constants (they are published as API
documentation), and comments only where the *why* is not obvious.

### Without Docker

You need PHP and Composer, a reachable MySQL and, for the integration tests, the two test databases, empty and
accessible to your MySQL user. Migrate `izartu_test` first; the suite runs with `DEBUG=1` because part of it exercises
the debug instrumentation:

```sh
composer install
DB_NAME=izartu_test DB_USER=you DB_PASS=yourpass php private/cli/migrate.php
DEBUG=1 DB_NAME=izartu_test DB_USER=you DB_PASS=yourpass vendor/bin/phpunit   # --testsuite unit needs no database
vendor/bin/phpstan analyse --memory-limit=512M
vendor/bin/php-cs-fixer fix --dry-run                                          # without --dry-run, it applies
```

[`tests/E2E/smoke.sh`](tests/E2E/smoke.sh) runs against any running instance, given its `BASE_URL` and an existing
user's `SMOKE_EMAIL`, `SMOKE_USERNAME` and `SMOKE_PASSWORD`.

## Continuous integration

Every pull request and every push to `main` runs the workflows in [`.github/workflows/`](.github/workflows/): the unit
tests on every supported PHP version, the integration tests on each of them against MySQL 8.4 and 9.7, the e2e test,
style and static analysis, the hooks that need no PHP, and an audit of the dev dependencies, which also runs weekly. A
push to `main` that changes the image builds it for amd64 and arm64, runs the e2e test on each, and publishes it on
Docker Hub and GHCR as `main`. A local `make check` covers one PHP and one MySQL; CI covers the rest.

## License

izartu is licensed under the GNU AGPL, version 3 or any later version, and contributions are accepted under the same
terms. Every source file carries the [licence header](scripts/license-header.txt); the hooks add it for you.
