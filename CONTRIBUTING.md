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
- Add or adjust tests for what you change, and keep `make check` and `make lint` green.
- Update [`README.md`](README.md) in the same commit when behaviour, configuration or requirements change.
- Sign your commits (`git commit -S`) if you can.

## Development

You need Docker with the Compose plugin, `make`, a `.env` at the clone's root with the database credentials
([README, "Installation"](README.md#installation)) and, for the hooks, [uv](https://docs.astral.sh/uv/), which runs the
[pre-commit](https://pre-commit.com) locked in [`.github/pre-commit/`](.github/pre-commit/) (`make pre-commit-sync`
after the lock changes). Every task has a `make` target (`make help` lists them), and the hooks and CI call the same
targets, so each command is defined once:

```sh
make deps    # install the dev dependencies (PHPUnit, PHPStan, PHP-CS-Fixer) into vendor/, once
make hooks   # install the hooks, once
make up      # start the app and the database in the background, at http://localhost:8080
make check   # check the code and image, run the test tiers
make lint    # run the commit hooks on every file
```

The dev images come from [`docker/dev/`](docker/dev/), a directory per PHP and MySQL version that `PHP_VERSION` and
`MYSQL_VERSION` choose, from the environment or `.env`, the newest by default. A dev database stays on its MySQL only
with `MYSQL_VERSION` in `.env`: once a newer MySQL upgrades it, it may not start on the older one again.

### Hooks

The hooks ([`.pre-commit-config.yaml`](.pre-commit-config.yaml)) check:

- **before each commit**: the code style, static analysis and the extensions the code calls; the licence headers;
  spelling; the shell scripts, JavaScript, YAML, workflows and Dockerfile; whitespace and line length; and leaked
  secrets, which `make check-secrets` also looks for in HEAD's history;
- **before a push**, each when a file it reads changed: the three test tiers and the image's extensions and layers.

### Tests

* **Unit** ([`tests/Unit/`](tests/Unit/)): the code in isolation, with no database.
* **Integration** ([`tests/Integration/`](tests/Integration/)): the real components wired together (MySQL, the
  filesystem), against the test databases `izartu_test` and `izartu_migration_test`, never real data. Docker creates
  both, with [`docker/create-test-db.sh`](docker/create-test-db.sh), on the first start of a new database volume; a
  volume older than one of them needs `docker compose down -v` once, which also deletes the development database.
  `make test-integration` migrates `izartu_test` first. This tier also runs `docker/check-pushed.sh`, against a fake of
  docker.
* **E2E** ([`tests/E2E/`](tests/E2E/)): [`smoke.sh`](tests/E2E/smoke.sh), a shell script with curl, drives the running
  app over HTTP, for what only real HTTP shows: the home page up, a login keeping its session across requests, and a tag
  URL with an encoded slash reaching the app. It runs the published image, built from the tracked files as they are (or
  the one `IMAGE` names, local or in a registry), on a stack of its own (separate Compose project, database and
  credentials, no published port), removed whatever the outcome; it takes only the PHP and MySQL versions from `.env`,
  through `make`, so the app runs on its default settings, and needs no `make deps`.

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
PHP-CS-Fixer. The image's PHP has only PHP's core and the extensions `composer.json` requires, so
[composer-require-checker](https://github.com/maglnet/ComposerRequireChecker) checks that the code calls nothing else
([`composer-require-checker.json`](composer-require-checker.json)), and
[`docker/check-extensions.php`](docker/check-extensions.php) that the image has those and nothing else; a release's
image, built with `main`'s packaging, has at least those of its own `composer.json`.

```sh
make analyse           # static analysis
make check-require     # the extensions the code calls
make check-extensions  # the extensions the image has: IMAGE, or else the dev app, rebuilt; at least CODE_TAG's
make style             # check the code style
make fmt               # apply it
```

Beyond the fixer: `/** */` docblocks on classes, methods, properties and constants (they are published as API
documentation), and comments only where the *why* is not obvious.

### The image's layers

The image's first layers, Alpine's packages and PHP (the Dockerfile's `system` stage), have to be reproducible, so that
an update downloads only the layers that changed. [`docker/check-layers.sh`](docker/check-layers.sh) builds them twice
and compares; for a layer that differs, it shows the entries that do, often a file a build step left undated
([`docker/pin-mtimes.sh`](docker/pin-mtimes.sh)).

```sh
make check-layers  # two builds of the system stage, compared
```

### Without Docker

You need PHP and Composer, a reachable MySQL and, for the integration tests, jq and the two test databases, empty and
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

Every pull request and every push to `main` runs workflows in [`.github/workflows/`](.github/workflows/):

- the unit tests on every supported PHP version, and the integration tests on each of them against MySQL 8.4 and 9.7;
- one build of the image, with the e2e test and the extensions and layers checks;
- the commit hooks and a scan of the history for secrets;
- CodeQL, which also runs weekly, on the workflows and the JavaScript.

A local `make check` covers one PHP and one MySQL; CI covers the rest. A weekly audit of the dev dependencies opens a
pull request when an update within `composer.json`'s constraints fixes the vulnerabilities it finds.

### Releases

A release's image is its code, from its tag, with `main`'s packaging (the Dockerfile, `docker/` and the Makefile).

- **An annotated tag `vX.Y.Z`** on `main`'s last commit builds it for amd64 and arm64, checks its extensions and layers
  and that it has every fix Alpine published, runs the release's own e2e test on each, publishes it on Docker Hub and
  GHCR as `X.Y.Z`, `X.Y` and `latest`, and creates the GitHub Release: the tag's subject as its title, the rest of its
  message as its notes, and the signed source archive and `compose.yaml`.
- **Run by hand on `main`**, the [Container images](.github/workflows/container-images.yml) workflow publishes the
  newest release if its tag's run did not, and otherwise rebuilds its image in place with the same code: a fix to the
  packaging reaches the image without a release.
- **Daily**, a workflow starts that rebuild when Alpine has fixed one of the image's packages.

## License

izartu is licensed under the GNU AGPL, version 3 or any later version, and contributions are accepted under the same
terms. Every source file carries the [licence header](scripts/license-header.txt); the hooks add it for you.
