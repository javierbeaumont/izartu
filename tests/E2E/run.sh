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

# The e2e tier: smoke.sh against a throwaway stack of the published image, built from the tracked files as they are now,
# and torn down whatever happens. Its own project name, credentials and volume, and no published port, keep it off the
# dev stack; the explicit -f and --env-file keep COMPOSE_FILE, local overrides and .env out.

set -e

cd "$(dirname "$0")/../.."

# Compose's default project name (the directory's) plus -e2e: one stack per clone, so two clones never share one.
PROJECT="$(basename "$PWD" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9_-')-e2e"

export DB_USER=izartu DB_PASS=izartu-e2e DB_ROOT_PASS=izartu-e2e-root

E2E_EMAIL=e2e@izartu.test
E2E_USERNAME=e2e
E2E_PASSWORD=e2e-password

SOURCE="$(mktemp -d)"
export SOURCE

compose() {
    docker compose -p "$PROJECT" --project-directory . --env-file /dev/null -f docker/compose.yaml \
        -f tests/E2E/docker-compose.yml "$@"
}

trap 'compose down -v; rm -rf "$SOURCE"' EXIT
trap 'exit 1' INT TERM

# The release archive of the tracked files, staged or not. `stash create` commits them without touching the tree or the
# stash, and prints nothing when nothing changed; the identity is for CI's checkout, which has none.
TREE="$(git -c user.name=e2e -c user.email=e2e@izartu.test stash create)"
git archive "${TREE:-HEAD}" | tar -x -C "$SOURCE"

compose up -d --build --wait app

printf '%s\n' "$E2E_EMAIL" "$E2E_USERNAME" "$E2E_PASSWORD" "$E2E_PASSWORD" owner |
    compose exec -T app php /var/www/izartu/private/cli/adduser.php

compose run --rm --build -e BASE_URL=http://app:8080 -e SMOKE_EMAIL="$E2E_EMAIL" -e SMOKE_USERNAME="$E2E_USERNAME" \
    -e SMOKE_PASSWORD="$E2E_PASSWORD" smoke
