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

Open <http://localhost:8080>. The database is created from `izartu.sql` on
first start. Create your first user (interactive; give it the `owner` role):

```sh
docker compose exec app php /var/www/izartu/private/cli/adduser.php
```

Log in at `/login` and start adding bookmarks.

## Installation without Docker

Requirements:

* PHP with the `pdo_mysql` extension. izartu supports every PHP version with
  [security support](https://www.php.net/supported-versions.php) (currently
  8.2 to 8.5; the floor rises as versions reach end of life), and CI runs the
  full test suite on each of them.
* MySQL >= 8.4
* A web server that routes requests to `public/index.php`. Apache works out
  of the box through the shipped `.htaccess` (needs `mod_rewrite`).

Steps:

1. Copy the repository to the server. Point the web server's document root
   at `public/`; `private/` must stay outside the web root (as shipped).
2. Create a MySQL database and load the schema: `mysql izartu < izartu.sql`.
3. Provide the `DB_*` environment variables (see Configuration) to the PHP
   process. With Apache and mod_php, a minimal vhost looks like:

   ```apache
   <VirtualHost *:80>
     ServerName bookmarks.example.org
     DocumentRoot /var/www/izartu/public

     <Directory /var/www/izartu/public>
       AllowOverride All
       Require all granted
     </Directory>

     SetEnv DB_USER user_name
     SetEnv DB_PASS user_password
   </VirtualHost>
   ```

   (With PHP-FPM, set the same variables in the pool's `env[...]` entries.)
4. Create the first user: `php private/cli/adduser.php`.
5. Log in at `/login`.

izartu also runs from a sub-directory (e.g. `https://example.org/bookmarks/`);
no configuration is needed, the base path is detected automatically.

## Configuration

Every setting is read from an environment variable, so a deployment never
needs to edit tracked files (with Docker, put them in your `.env`; without
it, in `SetEnv` or the FPM pool). The catalogue, with its defaults, lives in
[`private/config.php`](private/config.php):

* `DB_HOST`: optional, defaults to `localhost`.
* `DB_NAME`: optional, defaults to `izartu`.
* `DB_PASS`: required, no default.
* `DB_PORT`: optional, defaults to `3306`.
* `DB_USER`: required, no default.
* `PAGE_SIZE`: bookmarks per feed page; optional, defaults to `10`.
* `CLOUD_SIZE`: most-used tags shown in the tag cloud; optional, defaults
  to `50`.
* `TAGS_PAGE_SIZE`: tags per page on the tag index; optional, defaults
  to `100`.
* `DEBUG`: optional, off by default. Set it to `1` to enable debug mode:
  error output, a `Server-Timing` response header with request metrics (PHP
  time, database time and query count, peak memory; shown natively in the
  browser devtools network panel) and a collapsible per-query timing panel
  at the bottom of every page, where repeated queries are flagged.

## Managing users

Users are created from the command line. It prompts for email, username,
password and role (`owner`, `admin` or `user`), hashes the password and
inserts the user. Login is by email; the username is the public display name
shown next to the user's bookmarks.

Inputs are validated: a real email address, a username of 3-32 characters
(letters, digits, `_` or `-`), and a password of at least 8 characters.

### With Docker

```sh
docker compose exec app php /var/www/izartu/private/cli/adduser.php
```

### Without Docker

```sh
php private/cli/adduser.php
```

## Development

Every task below has a `make` target (`make help` lists them); the hooks and CI
call the same targets, so there is a single definition of each command.

### Pre-commit hooks

The repo ships a [pre-commit](https://pre-commit.com) configuration
([`.pre-commit-config.yaml`](.pre-commit-config.yaml)) so nothing lands
unchecked: formatting, static analysis, the AGPL headers and the usual hygiene
and secret-scanning checks run on every commit, and the test suite on every
push. The PHP hooks use the Docker toolchain, so they need your `.env`.
Install them once:

```sh
make hooks
```

### Tests

Tests run with PHPUnit, a **dev-only** dependency (Composer). The suite runs
against a dedicated `izartu_test` database so it never touches real data.

#### With Docker

The `izartu_test` database is created automatically on the first `db` start
(run `docker compose down -v` once if the volume predates it). Install the
dev dependencies once (writes `vendor/`, gitignored):

```sh
make deps
```

Run the suite:

```sh
make test
```

#### Without Docker

Needs PHP + Composer on the host, MySQL reachable, and an `izartu_test`
database loaded with the schema:

```sh
composer install
DEBUG=1 DB_NAME=izartu_test DB_USER=you DB_PASS=yourpass vendor/bin/phpunit
```

(`DEBUG=1` because part of the suite exercises the debug instrumentation.)

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
