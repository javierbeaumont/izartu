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

# Every development task, defined once.

COMPOSE := docker compose
# The versions in .env, which Compose reads, unless given.
env = $(if $(wildcard .env),$(shell sed -nE \
	's/^[[:space:]]*(export[[:space:]]+)?$(1)[[:space:]]*=[^0-9]*([0-9.]*).*/\2/p' .env | tail -n 1))
PHP_VERSION ?= $(call env,PHP_VERSION)
MYSQL_VERSION ?= $(call env,MYSQL_VERSION)
# A test platform's version: the one given, or else latest, the newest under docker/dev.
newest = $(shell printf '%s\n' $(patsubst docker/dev/$(1)-%,%,$(wildcard docker/dev/$(1)-*)) | sort -V | tail -n 1)
platform = $(if $(filter-out latest,$(2)),$(2),$(call newest,$(1)))
export PHP_VERSION MYSQL_VERSION
override PHP_VERSION := $(call platform,php,$(PHP_VERSION))
override MYSQL_VERSION := $(call platform,mysql,$(MYSQL_VERSION))
# No TTY: pre-commit gives hooks no terminal on stdin, which older Compose versions do not detect.
RUN := docker/dev-image.sh php && $(COMPOSE) run --rm -T
# Only the integration tests need the database, so no tool starts it.
TOOL := $(RUN) --no-deps test
PRE_COMMIT := uv run --locked --project .github/pre-commit pre-commit

.PHONY: help up down build versions deps deps-update test test-unit test-integration test-e2e style fmt analyse \
	check-require check-extensions check-layers check-rebuild upgradable check-pushed validate audit audit-fix check user \
	hooks lint check-secrets pre-commit-sync pre-commit-lock

help: ## list the targets
	@grep -hE '^[a-z][a-z0-9-]*:.*##' $(MAKEFILE_LIST) | sed 's/:[^#]*## /\t/' | expand -t 18

up: ## start the stack in the background
	docker/dev-image.sh mysql
	$(COMPOSE) up -d app db

down: ## stop the stack (the database volume survives)
	$(COMPOSE) down

build: ## build the dev PHP image for PHP_VERSION (unset: the newest PHP)
	docker/dev-image.sh php

versions: ## print the resolved PHP_VERSION, MYSQL_VERSION and their dev images' hashes as an env file
	@echo "PHP_VERSION=$(PHP_VERSION)"
	@hash=$$(docker/dev-image.sh php --hash) && echo "PHP_HASH=$$hash"
	@echo "MYSQL_VERSION=$(MYSQL_VERSION)"
	@hash=$$(docker/dev-image.sh mysql --hash) && echo "MYSQL_HASH=$$hash"

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
	docker/dev-image.sh mysql
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

# composer-require-checker is in the newest PHP's dev image alone.
check-require: export PHP_VERSION := $(call platform,php,latest)
check-require: ## check that the code calls only PHP's core and the extensions composer.json requires
	$(TOOL) composer-require-checker check

# The image has neither the script nor the files it reads. Without IMAGE, the dev app, rebuilt: its `base` stage has the
# image's modules. With CODE_TAG, that tag's composer.json, and at least its extensions: a release's image is built with
# `main`'s packaging.
check-extensions: ## check the image's PHP modules against composer.json (IMAGE, or else the dev app; CODE_TAG)
	composer="$$($(if $(CODE_TAG),git show 'refs/tags/$(CODE_TAG):composer.json',cat composer.json))" \
		|| { echo "no composer.json in $(or $(CODE_TAG),the working tree)" >&2; exit 1; }; \
	$(if $(IMAGE),docker run --rm -i $(IMAGE),$(COMPOSE) run --rm -T --build --no-deps app) \
		php -- "$$composer" "$$(cat composer-require-checker.json)" $(if $(CODE_TAG),--at-least) \
		< docker/check-extensions.php

check-layers: ## check that two builds of the image's system stage make the same layers
	docker/check-layers.sh

# Not in `check`: it needs the image published under the rebuilt release's version.
check-rebuild: ## check that a rebuild of a release (IMAGE) has the code of its published image (PUBLISHED)
	docker/check-rebuild.sh "$(IMAGE)" "$(PUBLISHED)"

# As root, since the image's user cannot update apk's index; silent, so its output is the list alone.
upgradable: ## list the packages Alpine has fixed since the image (IMAGE) was built
	@docker run --rm --user root $(or $(IMAGE),$(error IMAGE is required)) \
		sh -c 'apk update --quiet && apk list --upgradable'

# Not in `check`: it reads a registry.
check-pushed: ## check that a pushed platform image (PUSHED) has the layers of a local one (IMAGE)
	docker/check-pushed.sh "$(IMAGE)" "$(PUSHED)"

validate: ## check composer.json against the lock file
	$(TOOL) composer validate --strict

audit: ## report known vulnerabilities in the dependencies
	$(TOOL) composer audit --locked --abandoned=ignore

audit-fix: ## update the lock file's packages with known vulnerabilities, within composer.json's constraints
	$(TOOL) composer update --minimal-changes --no-install
	$(MAKE) audit

check: style analyse check-require check-extensions check-layers test ## check the code and image, run the test tiers

user: ## create a user (interactive)
	$(COMPOSE) exec app php /var/www/izartu/private/cli/adduser.php

hooks: ## install the pre-commit hooks
	$(PRE_COMMIT) install --hook-type pre-commit --hook-type pre-push

lint: ## run the commit hooks on every file
	$(PRE_COMMIT) run --all-files --show-diff-on-failure

check-secrets: ## check HEAD's history for secrets
	$(PRE_COMMIT) run --hook-stage manual gitleaks-history --all-files

pre-commit-sync: ## install the locked pre-commit, in .github/pre-commit/.venv
	uv sync --locked --project .github/pre-commit

pre-commit-lock: ## lock pre-commit, in .github/pre-commit/uv.lock
	uv lock --project .github/pre-commit
