# izartu

Web bookmark manager based on tags.

## Requirements

* PHP   >= 8.2
* MySQL >= 8.4

Older releases may still run *izartu*, but they are officially end-of-life and unsupported.

## Running with Docker

A LAMP setup (Apache + MySQL + PHP) with Docker is included:

```sh
docker compose up --build
```

Then open <http://localhost:8080>. On first run the database is created from
`izartu.sql`. Bookmarks are managed directly in the database (`data`, `tag` and
`data_tag` tables) for now.

## Creating users

Users can be created from the command line. This is how you create the first user
(the owner). It prompts for email, username, password and role, hashes the password
and inserts the user; then log in at `/login`.

With Docker:

```sh
docker compose exec app php /var/www/izartu/private/cli/adduser.php
```

Without Docker:

```sh
php private/cli/adduser.php
```

## Tests

Tests run with PHPUnit, a **dev-only** dependency (Composer). The suite runs against
a dedicated `izartu_test` database, created automatically on the first `db` start
(run `docker compose down -v` once if the volume predates it).

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

## Code style

Code follows [PER Coding Style](https://www.php-fig.org/per/coding-style/) (max
line length 120), enforced with PHP-CS-Fixer (also a dev-only dependency):

```sh
docker compose run --rm test vendor/bin/php-cs-fixer fix           # apply
docker compose run --rm test vendor/bin/php-cs-fixer fix --dry-run # check only
```

## Configuration

Database credentials are read from environment variables (set in
[`docker-compose.yml`](docker-compose.yml)):

* `DB_HOST`: optional, defaults to `localhost`.
* `DB_NAME`: optional, defaults to `izartu`.
* `DB_PASS`: required, no default.
* `DB_USER`: required, no default.

## License

*izartu* uses the GNU AGPLv3 license.

Please read the [LICENSE](LICENSE) file for more information.
