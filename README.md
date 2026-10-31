# izartu

izartu is a lightweight, self-hosted web bookmark manager based on tags:
your bookmarks are private by default, and making one public shares it on
your instance's feed. Plain PHP and MySQL, **zero runtime dependencies** (no
framework, no packages in production), released under the GNU AGPLv3.

## Why izartu

* **Personal first, social second.** Every bookmark is private (only you) or
  public (anyone on your instance). The public feed, tag pages and tag cloud
  emerge from personal use.
* **Self-contained.** No build step, no framework to upgrade, nothing to
  `npm install`. A production install is just these files, PHP and MySQL.
* **Yours.** Self-hosted and AGPL-licensed: your bookmarks live on your
  server, and so does the public layer, scoped to your instance.

## Status

izartu is being rebuilt toward its first release (v0.0) and has no published
release yet. The core already works: login, bookmark add/edit/delete with
tags and visibility, and the public feed with pagination and tag filtering.
Expect rough edges and breaking changes while the rebuild is under way.

## Features

* Public bookmark feed, browsable anonymously: newest first, paginated, and
  filterable by user (`/user/USERNAME`) or by tags: `/tag/NAME`, and tags
  combine with commas (`/tag/php,docs` lists bookmarks carrying both). The
  tag cloud always describes the list on screen, and inside a tag filter its
  links narrow it further; `/tags` lists every tag, paginated and searchable,
  and reached from a filtered list it stays scoped to it.
  Each bookmark shows who added it and when, linking to that user's
  bookmarks.
* Log in to add, edit and delete bookmarks, all inline: a bookmark is edited
  in place in the list (same look, editable), where it can also be deleted,
  and new bookmarks are added the same way on top of the list. Editing is
  restricted to the bookmark's owner (or an admin). Your own user page lists
  all your bookmarks, public and private; `/user/me` always takes you there.
* Per-bookmark visibility: public (anyone on the instance) or private (only
  its owner: no other account sees it, whatever its role, and neither do its
  tags leak into the cloud).
* Comma-separated tags, normalised automatically (trimmed, lower-cased,
  deduplicated).
* Runs at a domain root or in a sub-directory (base path is auto-detected).
* Hardened sessions (HttpOnly, SameSite, Secure on HTTPS), CSRF-protected
  forms, and HTML-escaped output.

## Installation with Docker

Requires only Docker (with the compose plugin).

```sh
git clone https://github.com/javierbeaumont/izartu.git
cd izartu
```

The database credentials are deliberately not defined in the repository:
choose your own in a `.env` file next to `docker-compose.yml` (gitignored).
`DB_USER`/`DB_PASS` are the application's MySQL account; `DB_ROOT_PASS` is
the root password of the bundled MySQL container (Docker setup only, never
read by izartu):

```sh
cat > .env <<'EOF'
DB_USER=izartu
DB_PASS=choose-a-password
DB_ROOT_PASS=choose-another-password
EOF
```

Then bring the stack up:

```sh
docker compose up --build
```

Open <http://localhost:8080>. Every time it starts, the app container applies the migrations the database lacks, so the
first start builds the schema. Create your first user (interactive; give it the `owner` role):
```sh
docker compose exec app php /var/www/izartu/private/cli/adduser.php
```

Log in at `/login` and start adding bookmarks.

## Installation without Docker

Requirements:

* PHP with the `pdo_mysql` and `mbstring` extensions. izartu supports every PHP version with [security
  support](https://www.php.net/supported-versions.php) (currently 8.2 to 8.5; the floor rises as versions reach end of
  life), and CI runs the unit and integration tests on each of them.
* MySQL >= 8.4
* A web server that routes requests to `public/index.php`. Apache works through the shipped `.htaccess` (needs
  `mod_rewrite`) plus `AllowEncodedSlashes NoDecode` in the vhost, which `.htaccess` cannot set: without it, a tag
  with a `/` in its name links to a 404.

Steps:

1. Copy the repository to the server. Point the web server's document root
   at `public/`; `private/` must stay outside the web root (as shipped).
2. Create an empty MySQL database, and a user with every privilege on it: the migrations create and alter its tables.
3. Provide the `DB_*` environment variables (see Configuration) to the PHP
   process. With Apache and mod_php, a minimal vhost looks like:

   ```apache
   <VirtualHost *:80>
     ServerName bookmarks.example.org
     DocumentRoot /var/www/izartu/public
     AllowEncodedSlashes NoDecode

     <Directory /var/www/izartu/public>
       AllowOverride All
       Require all granted
     </Directory>

     SetEnv DB_USER user_name
     SetEnv DB_PASS user_password
   </VirtualHost>
   ```

   (With PHP-FPM, set the same variables in the pool's `env[...]` entries.)
4. Build the schema: `php private/cli/migrate.php`. Like every CLI script, it reads the `DB_*` variables from its own
   environment (for example `DB_USER=user_name DB_PASS=user_password php private/cli/migrate.php`).
5. Create the first user: `php private/cli/adduser.php`.
6. Log in at `/login`.

izartu also runs from a sub-directory (e.g. `https://example.org/bookmarks/`);
no configuration is needed, the base path is detected from the request. Set
`BASE` only if a reverse proxy serves the app under a path the request does not
carry.

## Upgrading

Back up the database first: migrations only go forward. Then replace the files with the new release and apply its
migrations: with Docker, `docker compose up -d --build` (the app applies them on start); without Docker,
`php private/cli/migrate.php`. `migrate.php` applies, in order, every migration the database lacks, and refuses a
database it cannot continue from: one newer than the code, or one it did not build. Until the migrations are applied, a
page the old schema cannot serve answers 503, and PHP's error log says to run `migrate.php`.

## Configuration

Every setting is read from an environment variable, so a deployment never
needs to edit tracked files (with Docker, put them in your `.env`; without
it, in `SetEnv` or the FPM pool). The catalogue, with its defaults, lives in
[`private/config.php`](private/config.php):

* `BASE`: base URL path of the install; optional, detected from the request
  (empty at a domain root, `/bookmarks` under one).
* `VERSION`: what the footer reports and links to as the running source; optional. When unset it comes from the tree: a
  release download has it stamped in, and a clone reads it from its git metadata. Set it for a deploy that has neither;
  failing all three, the footer links to the repository root.
* `DB_HOST`: optional, defaults to `localhost`.
* `DB_NAME`: optional, defaults to `izartu`.
* `DB_PASS`: required, no default.
* `DB_PORT`: optional, defaults to `3306`.
* `DB_USER`: required, no default.
* `CLOUD_SIZE`: most-used tags shown in the tag cloud; optional, defaults
  to `50`.
* `PAGE_SIZE`: bookmarks per feed page; optional, defaults to `10`.
* `TAGS_PAGE_SIZE`: tags per page on the tag index; optional, defaults
  to `100`.
* `TIMEZONE`: timezone of the dates shown, a name from [PHP's list](https://www.php.net/timezones) such as
  `Europe/Madrid`; optional, defaults to PHP's `date.timezone`, UTC when that is unset. A name PHP does not know stops
  the app.
* `DEBUG`: optional, off by default. Set it to `1` to enable debug mode: PHP's errors and the cause of a failed request
  on the page, a `Server-Timing` response header with request metrics (PHP time, database time and query count, peak
  memory; shown natively in the browser devtools network panel) and a collapsible per-query timing panel at the bottom
  of every page, where repeated queries are flagged.

## Managing users

Users are managed from the command line. `adduser.php` prompts for email, username, password and role (`owner`, `admin`
or `user`), the password twice and without showing it on a terminal, hashes it and inserts the user. Login is by email;
the username is the public display name shown next to the user's bookmarks.

Inputs are validated: a real email address, a username of 3-32 characters
(letters, digits, `_` or `-`), and a password of at least 8 characters. The
username `me` is reserved, since `/user/me` is the alias for your own page.

`passwd.php` changes a user's password: it asks for the email and the new password, twice, without showing it on a
terminal, and ends every session the user has open, so a leaked password stops working at once.

`deluser.php` deletes a user with their bookmarks and the tags only that user's bookmarks have. It asks for the email,
shows the username, the role and how many bookmarks go with them, and deletes only once the email is typed again. It
ends the user's open sessions too. The only owner cannot be deleted.

Ending sessions takes PHP's session files and the right to delete them: without Docker, run `passwd.php` and
`deluser.php` as root or as the web server's user.

### With Docker

```sh
docker compose exec app php /var/www/izartu/private/cli/adduser.php
docker compose exec app php /var/www/izartu/private/cli/passwd.php
docker compose exec app php /var/www/izartu/private/cli/deluser.php
```

### Without Docker

```sh
php private/cli/adduser.php
php private/cli/passwd.php
php private/cli/deluser.php
```

## Errors and logs

A request that fails because the database is unreachable or behind the code answers 503 with `Retry-After`; any other
failure answers 500. The cause goes to PHP's error log, and debug mode shows it on the page too.

### With Docker

PHP's log goes to the app container's output, mixed with Apache's access log; its lines are tagged `php:`.

```sh
docker compose logs app | grep 'php:'
```

### Without Docker

PHP's log is the file `error_log` names in `php.ini` or, when it names none, the web server's error log.

## Development

Every task below has a `make` target (`make help` lists them); the hooks and CI
call the same targets, so there is a single definition of each command.

### Pre-commit hooks

The repo ships a [pre-commit](https://pre-commit.com) configuration
([`.pre-commit-config.yaml`](.pre-commit-config.yaml)) so nothing lands unchecked: formatting, static analysis, the AGPL
headers and the usual hygiene and secret-scanning checks run on every commit, and the three test tiers on every push.
The PHP hooks use the Docker toolchain, so they need your `.env`. Install them once:

```sh
make hooks
```

### Tests

Tests come in three tiers. Unit and integration run with PHPUnit, a **dev-only** dependency (Composer); e2e is a shell
script with curl.

* **Unit** (`tests/Unit/`): the code in isolation, with no database.
* **Integration** (`tests/Integration/`): the real components wired together (MySQL, the filesystem), against dedicated
  test databases so they never touch real data.
* **E2E** (`tests/E2E/`): `smoke.sh` drives the running app over HTTP for what only real HTTP can show: the home page
  up, a login keeping its session across requests, and a tag URL with an encoded slash reaching the app.

#### With Docker

The test databases, `izartu_test` and `izartu_migration_test`, are created empty on the first `db` start, and
`make test-integration` migrates `izartu_test` before the suite runs (a volume created before the migrations needs
`docker compose down -v` once). Install the dev dependencies once (writes `vendor/`, gitignored):

```sh
make deps
```

Run the three tiers, or one:

```sh
make test              # all three
make test-unit         # unit only; starts no database
make test-integration  # integration only
make test-e2e          # e2e only, on a throwaway stack
```

`make test-e2e` brings up a stack of its own (separate Compose project, database and credentials, no published port),
creates a user, runs `smoke.sh` against it and removes it all, whatever the outcome. It ignores your `.env`, so the app
runs on its default settings, and needs no `make deps`.

#### Without Docker

Needs PHP + Composer on the host, MySQL reachable, and two empty databases, `izartu_test` and `izartu_migration_test`,
both accessible to your MySQL user. Migrate the first, then run the suite:

```sh
composer install
DB_NAME=izartu_test DB_USER=you DB_PASS=yourpass php private/cli/migrate.php
DEBUG=1 DB_NAME=izartu_test DB_USER=you DB_PASS=yourpass vendor/bin/phpunit
```

(`DEBUG=1` because part of the suite exercises the debug instrumentation.) `vendor/bin/phpunit --testsuite unit` runs
the unit tier alone, with no database at all.

### Static analysis

PHPStan (also a dev-only dependency) runs at level 10 (the maximum) over the runtime code;
see [`phpstan.dist.neon`](phpstan.dist.neon).

#### With Docker

```sh
make analyse
```

#### Without Docker

```sh
vendor/bin/phpstan analyse --memory-limit=512M
```

### Code style

Code follows [PER Coding Style](https://www.php-fig.org/per/coding-style/)
(max line length 120), enforced with PHP-CS-Fixer (also a dev-only
dependency).

#### With Docker

```sh
make fmt    # apply
make style  # check only
```

#### Without Docker

```sh
vendor/bin/php-cs-fixer fix           # apply
vendor/bin/php-cs-fixer fix --dry-run # check only
```

## License

*izartu* uses the GNU AGPLv3 license.

Please read the [LICENSE](LICENSE) file for more information.

The wordmark is set in [Inter](https://rsms.me/inter/), by The Inter Project Authors, under the SIL Open Font License
1.1: see [public/font/OFL.txt](public/font/OFL.txt).
