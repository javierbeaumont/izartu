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

## Requirements

* PHP >= 8.2 with the `pdo_mysql` extension
* MySQL >= 8.4
* A web server that routes requests to `public/index.php`. Apache works out
  of the box through the shipped `.htaccess` (needs `mod_rewrite`).

Or skip all of the above and use the included Docker setup.

## Quick start (Docker)

```sh
git clone https://github.com/javierbeaumont/izartu.git
cd izartu
docker compose up --build
```

Open <http://localhost:8080>. The database is created from `izartu.sql` on
first start. Create your first user (interactive; give it the `owner` role):

```sh
docker compose exec app php /var/www/izartu/private/cli/adduser.php
```

Log in at `/login` and start adding bookmarks.

## Manual installation

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

Database credentials are read from environment variables (set in
[`docker-compose.yml`](docker-compose.yml) for the Docker setup):

* `DB_HOST`: optional, defaults to `localhost`.
* `DB_NAME`: optional, defaults to `izartu`.
* `DB_PASS`: required, no default.
* `DB_USER`: required, no default.

Other settings are constants in [`private/config.php`](private/config.php):
`PAGE_SIZE` (bookmarks per feed page, default 10), `CLOUD_SIZE` (most-used
tags shown in the tag cloud, default 50), `TAGS_PAGE_SIZE` (tags per page on
the tag index, default 100) and `DEBUG` (set it to `FALSE` in production). Debug mode enables error output, a `Server-Timing`
response header with request metrics (PHP time, database time and query
count, peak memory; shown natively in the browser devtools network panel)
and a collapsible per-query timing panel at the bottom of every page, where
repeated queries are flagged.

## Managing users

Users are created from the command line. It prompts for email, username,
password and role (`owner`, `admin` or `user`), hashes the password and
inserts the user. Login is by email; the username is the public display name
shown next to the user's bookmarks.

Inputs are validated: a real email address, a username of 3-32 characters
(letters, digits, `_` or `-`), and a password of at least 8 characters.

With Docker:

```sh
docker compose exec app php /var/www/izartu/private/cli/adduser.php
```

Without Docker:

```sh
php private/cli/adduser.php
```

## Development

### Tests

Tests run with PHPUnit, a **dev-only** dependency (Composer). The suite runs
against a dedicated `izartu_test` database, created automatically on the
first `db` start (run `docker compose down -v` once if the volume predates it).

Install the dev dependencies once (writes `vendor/`, gitignored):

```sh
docker compose run --rm test composer install
```

Run the suite:

```sh
docker compose run --rm test
```

Without Docker (PHP 8.5 + Composer on the host, MySQL reachable):

```sh
composer install
DB_NAME=izartu_test vendor/bin/phpunit
```

### Code style

Code follows [PER Coding Style](https://www.php-fig.org/per/coding-style/)
(max line length 120), enforced with PHP-CS-Fixer (also a dev-only
dependency):

```sh
docker compose run --rm test vendor/bin/php-cs-fixer fix           # apply
docker compose run --rm test vendor/bin/php-cs-fixer fix --dry-run # check only
```

## License

*izartu* uses the GNU AGPLv3 license.

Please read the [LICENSE](LICENSE) file for more information.
