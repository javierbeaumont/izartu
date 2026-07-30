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

# End-to-end smoke test against a running instance, with curl only: home,
# login, inline add, feed, tag page and tag search. Needs an existing user.
#
#     BASE_URL=http://localhost:8080 SMOKE_EMAIL=... SMOKE_PASSWORD=... SMOKE_USERNAME=... scripts/smoke.sh

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

echo "1/6 home is up"

for _ in $(seq 1 30); do
    code="$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/" || true)"
    [ "$code" = "200" ] && break
    sleep 2
done

[ "$code" = "200" ] || fail "home returned $code"

echo "2/6 log in"

TOKEN="$(csrf "$BASE_URL/login")"
[ -n "$TOKEN" ] || fail "no CSRF token on /login"

LOCATION="$(curl -sf -b "$JAR" -c "$JAR" -o /dev/null -w '%{redirect_url}' \
    -d "csrf=$TOKEN&email=$SMOKE_EMAIL&password=$SMOKE_PASSWORD" "$BASE_URL/login")"
case "$LOCATION" in
    */user/"$SMOKE_USERNAME") ;;
    *) fail "login did not land on /user/$SMOKE_USERNAME (got '$LOCATION')" ;;
esac

echo "3/6 add a bookmark inline"

TOKEN="$(csrf "$BASE_URL/user/$SMOKE_USERNAME?add")"
[ -n "$TOKEN" ] || fail "no CSRF token on the inline add form"

curl -sf -b "$JAR" -c "$JAR" -o /dev/null \
    -d "csrf=$TOKEN&title=Smoke test&link=https://smoke.example&description=ok" \
    -d "tags=smoke&visibility=public&return=user/$SMOKE_USERNAME" \
    "$BASE_URL/add"

echo "4/6 the anonymous feed shows it"

curl -sf "$BASE_URL/" | grep -q 'Smoke test' || fail "bookmark missing from the feed"

echo "5/6 the tag page shows it"

curl -sf "$BASE_URL/tag/smoke" | grep -q 'Smoke test' || fail "bookmark missing from /tag/smoke"

echo "6/6 the tag search finds the tag"

curl -sf "$BASE_URL/tags?q=smo" | grep -q '>smoke</a>' || fail "tag missing from /tags search"

echo "SMOKE OK"
