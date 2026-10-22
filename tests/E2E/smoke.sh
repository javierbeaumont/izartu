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

# End-to-end smoke test against a running instance, with curl only: what no in-process test can show. The home page is
# up, a login carries its session cookie across requests, and an encoded slash in a tag URL reaches the app. Needs an
# existing user; it changes nothing.
#
#     BASE_URL=http://localhost:8080 SMOKE_EMAIL=... SMOKE_PASSWORD=... SMOKE_USERNAME=... tests/E2E/smoke.sh

set -e

BASE_URL="${BASE_URL:-http://localhost:8080}"
SMOKE_EMAIL="${SMOKE_EMAIL:?set SMOKE_EMAIL to the email of an existing user}"
SMOKE_PASSWORD="${SMOKE_PASSWORD:?set SMOKE_PASSWORD to the password of that user}"
SMOKE_USERNAME="${SMOKE_USERNAME:?set SMOKE_USERNAME to the username of that user}"

JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

fail() {
    echo "SMOKE FAIL: $1" >&2
    exit 1
}

csrf() {
    curl -sf -b "$JAR" -c "$JAR" "$1" | sed -n 's/.*name="csrf" value="\([^"]*\)".*/\1/p' | head -n1
}

echo "1/4 home is up"

for _ in $(seq 1 30); do
    code="$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/" || true)"
    [ "$code" = "200" ] && break
    sleep 2
done

[ "$code" = "200" ] || fail "home returned $code"

echo "2/4 log in"

TOKEN="$(csrf "$BASE_URL/login")"
[ -n "$TOKEN" ] || fail "no CSRF token on /login"

LOCATION="$(curl -sf -b "$JAR" -c "$JAR" -o /dev/null -w '%{redirect_url}' \
    -d "csrf=$TOKEN&email=$SMOKE_EMAIL&password=$SMOKE_PASSWORD" "$BASE_URL/login")"
case "$LOCATION" in
    */user/"$SMOKE_USERNAME") ;;
    *) fail "login did not land on /user/$SMOKE_USERNAME (got '$LOCATION')" ;;
esac

echo "3/4 the session stays logged in"

curl -sf -b "$JAR" -c "$JAR" "$BASE_URL/user/$SMOKE_USERNAME?add" | grep -q 'name="title"' \
    || fail "no add form on /user/$SMOKE_USERNAME?add: the session did not survive the login"

echo "4/4 an encoded slash in a tag URL reaches the app"

# Regression: Apache refuses %2F in a path unless AllowEncodedSlashes allows it, so a tag with a slash linked to a 404.
curl -sf -o /dev/null "$BASE_URL/tag/ci%2Fcd" || fail "/tag/ci%2Fcd did not reach the app"

echo "SMOKE OK"
