#!/bin/sh
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

# The e2e tier: smoke.sh against a throwaway stack, torn down whatever happens. Its own project name, credentials and
# volume, and no published port, keep it off the dev stack; the explicit -f keeps COMPOSE_FILE and local overrides out.

set -e

cd "$(dirname "$0")/../.."

# Compose's default project name (the directory's) plus -e2e: one stack per clone, so two clones never share one.
PROJECT="$(basename "$PWD" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9_-')-e2e"

export DB_USER=izartu DB_PASS=izartu-e2e DB_ROOT_PASS=izartu-e2e-root

E2E_EMAIL=e2e@izartu.test
E2E_USERNAME=e2e
E2E_PASSWORD=e2e-password

compose() {
    docker compose -p "$PROJECT" -f docker-compose.yml -f tests/E2E/docker-compose.yml "$@"
}

trap 'compose down -v' EXIT
trap 'exit 1' INT TERM

compose up -d --build --wait app

printf '%s\n' "$E2E_EMAIL" "$E2E_USERNAME" "$E2E_PASSWORD" "$E2E_PASSWORD" owner |
    compose exec -T app php /var/www/izartu/private/cli/adduser.php

compose run --rm --build -e BASE_URL=http://app -e SMOKE_EMAIL="$E2E_EMAIL" -e SMOKE_USERNAME="$E2E_USERNAME" \
    -e SMOKE_PASSWORD="$E2E_PASSWORD" test tests/E2E/smoke.sh
