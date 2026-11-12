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
# No TTY: pre-commit gives hooks no terminal on stdin, which older Compose versions do not detect.
RUN := $(COMPOSE) run --rm -T
# Only the integration tests need the database, so no tool starts it.
TOOL := $(RUN) --no-deps test

.PHONY: help up down build deps deps-update test test-unit test-integration test-e2e style fmt analyse check-require \
	check-extensions check-layers validate audit check user hooks

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
	$(RUN) --no-deps -e DB_HOST=no-database.invalid test vendor/bin/phpunit --testsuite unit

test-integration: ## run the integration tests (MySQL, filesystem)
	$(RUN) test sh -c 'php private/cli/migrate.php && vendor/bin/phpunit --testsuite integration'

test-e2e: ## run the end-to-end test on a throwaway stack
	tests/E2E/run.sh

# php-cs-fixer warns, here and in fmt, that it runs on a newer PHP than composer.json's floor: its locked dependencies
# do not install on the floor, and CI runs the tests on every supported version.
style: ## check the code style
	$(TOOL) vendor/bin/php-cs-fixer fix --dry-run

fmt: ## apply the code style
	$(TOOL) vendor/bin/php-cs-fixer fix

analyse: ## run the static analysis
	$(TOOL) vendor/bin/phpstan analyse --no-progress --memory-limit=512M

check-require: ## check that the code calls only PHP's core and the extensions composer.json requires
	$(TOOL) composer-require-checker check

# The image has neither the script nor the files it reads. Without IMAGE, the dev app, rebuilt: its `base` stage has the
# image's modules. With CODE_TAG, that tag's composer.json, and at least its extensions: a release's image is built with
# `main`'s packaging.
check-extensions: ## check the image's PHP modules against composer.json (IMAGE, or else the dev app; CODE_TAG)
	composer="$$($(if $(CODE_TAG),git show 'refs/tags/$(CODE_TAG):composer.json',cat composer.json))" \
		|| { echo "no composer.json in $(or $(CODE_TAG),the working tree)" >&2; exit 1; }; \
	$(if $(IMAGE),docker run --rm -i $(IMAGE),$(RUN) --build --no-deps app) \
		php -- "$$composer" "$$(cat composer-require-checker.json)" $(if $(CODE_TAG),--at-least) \
		< docker/check-extensions.php

check-layers: ## check that two builds of the image's system stage make the same layers
	docker/check-layers.sh

validate: ## check composer.json against the lock file
	$(TOOL) composer validate --strict

audit: ## report known vulnerabilities in the dependencies
	$(TOOL) composer audit

check: style analyse check-require check-extensions check-layers test ## everything the CI runs

user: ## create a user (interactive)
	$(COMPOSE) exec app php /var/www/izartu/private/cli/adduser.php

hooks: ## install the pre-commit hooks
	pre-commit install --hook-type pre-commit --hook-type pre-push
