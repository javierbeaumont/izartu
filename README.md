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
