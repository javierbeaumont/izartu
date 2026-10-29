#  izartu: web bookmark manager based on tags
#
#  Copyright (C) 2011-2026 Javier Beaumont <javierbeaumont@users.noreply.github.com>
#
#  This file is part of izartu.
#
#  izartu is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as
#  published by the Free Software Foundation, either version 3 of the
#  License, or (at your option) any later version.
#
#  izartu is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
#  GNU Affero General Public License for more details.
#
#  You should have received a copy of the GNU Affero General Public License
#  along with izartu. If not, see <https://www.gnu.org/licenses/>.

# Every development task, defined once: the hooks, the CI and the README call
# these targets. They all run in the dev-only `test` container.

COMPOSE := docker compose
# php-cs-fixer refuses PHP versions it does not know about yet; the image is 8.5. Only the integration tests need the
# database, so no tool starts it.
TOOL := $(COMPOSE) run --rm --no-deps -e PHP_CS_FIXER_IGNORE_ENV=1 test

.PHONY: help up down build deps deps-update test test-unit test-integration test-e2e style fmt analyse validate audit \
	check user hooks

help: ## list the targets
	@grep -hE '^[a-z][a-z0-9-]*:.*##' $(MAKEFILE_LIST) | sed 's/:[^#]*## /\t/' | expand -t 18

up: ## start the stack in the background
	$(COMPOSE) up -d app db

down: ## stop the stack (the database volume survives)
	$(COMPOSE) down

build: ## build the dev image for PHP_VERSION (default 8.5)
	$(COMPOSE) build test

deps: ## install the dev dependencies from the lock file
	$(TOOL) composer install

deps-update: ## resolve the dev dependencies for the running PHP, ignoring the lock
	$(TOOL) composer update

test: test-unit test-integration test-e2e ## run the three test tiers

# DB_HOST points nowhere, so a unit test that reaches for MySQL fails here too, not only in CI, where this tier runs
# with no database.
test-unit: ## run the unit tests (no database)
	$(COMPOSE) run --rm --no-deps -e DB_HOST=no-database.invalid test vendor/bin/phpunit --testsuite unit

test-integration: ## run the integration tests (MySQL, filesystem)
	$(COMPOSE) run --rm test sh -c 'php private/cli/migrate.php && vendor/bin/phpunit --testsuite integration'

test-e2e: ## run the end-to-end test on a throwaway stack
	tests/E2E/run.sh

style: ## check the code style
	$(TOOL) vendor/bin/php-cs-fixer fix --dry-run

fmt: ## apply the code style
	$(TOOL) vendor/bin/php-cs-fixer fix

analyse: ## run the static analysis
	$(TOOL) vendor/bin/phpstan analyse --no-progress --memory-limit=512M

validate: ## check composer.json against the lock file
	$(TOOL) composer validate --strict

audit: ## report known vulnerabilities in the dependencies
	$(TOOL) composer audit

check: style analyse test ## everything the CI runs

user: ## create a user (interactive)
	$(COMPOSE) exec app php /var/www/izartu/private/cli/adduser.php

hooks: ## install the pre-commit hooks
	pre-commit install --hook-type pre-commit --hook-type pre-push
