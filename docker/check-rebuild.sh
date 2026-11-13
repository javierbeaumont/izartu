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

# Compares the code in a rebuild of a release's image with the code in the image published under its version, on the
# rebuild's platform: every entry under /var/www/izartu, with its type, mode and numeric owner, a link's target, and a
# file's time, size and sha256, has to be the same. The rest of the image is the packaging, which may change and never
# writes there.

set -eu

if [ "$#" -ne 2 ] || [ -z "$1" ] || [ -z "$2" ]; then
    echo "usage: $0 IMAGE PUBLISHED, e.g. izartu-e2e ghcr.io/javierbeaumont/izartu:0.1.0" >&2
    exit 2
fi
IMAGE="$1"
PUBLISHED="$2"

WORK="$(mktemp -d)"
PULLED=
CONTAINERS=

trap '[ -z "$CONTAINERS" ] || docker rm -f $CONTAINERS >/dev/null 2>&1 || true
    [ -z "$PULLED" ] || docker rmi "$PULLED" >/dev/null 2>&1 || true
    rm -rf "$WORK"' EXIT
trap 'exit 1' INT TERM

PLATFORM="$(docker image inspect --format '{{.Os}}/{{.Architecture}}' "$IMAGE")"

# The published image for this platform alone, by its digest.
MATCH="and .Platform (eq .Platform.OS \"${PLATFORM%/*}\") (eq .Platform.Architecture \"${PLATFORM#*/}\")"
if ! DIGEST="$(docker buildx imagetools inspect "$PUBLISHED" \
    --format "{{range .Manifest.Manifests}}{{if $MATCH}}{{println .Digest}}{{end}}{{end}}" 2>"$WORK/error")"; then
    cat "$WORK/error" >&2
    if grep -qF "$PUBLISHED: not found" "$WORK/error"; then
        echo "REBUILD FAIL: $PUBLISHED is not published; a run by hand on main publishes it" >&2
    fi
    exit 1
fi
if [ -z "$DIGEST" ]; then
    echo "REBUILD FAIL: $PUBLISHED has no $PLATFORM image" >&2
    exit 1
fi
PULLED="${PUBLISHED%:*}@$DIGEST"
docker pull --quiet "$PULLED" >/dev/null

# An image's code, copied out and listed by the rebuilt image's tools, so both lists come out alike. Only a file's time
# is listed: the extraction sets the others'.
code() { # IMAGE OUTPUT
    container="$(docker create "$1")"
    CONTAINERS="$CONTAINERS $container"
    if ! docker cp "$container:/var/www/izartu" - | docker run --rm -i --user root --entrypoint sh "$IMAGE" -c '
        mkdir /tmp/code && tar -x -C /tmp/code && cd /tmp/code/izartu \
            && find . -mindepth 1 ! -type f -exec stat -c "%N %F %a %u:%g" {} + \
            && find . -type f -exec stat -c "%n %a %u:%g %Y %s" {} + \
            && find . -type f -exec sha256sum {} +' >"$2.raw"; then
        echo "REBUILD FAIL: cannot list the code in $1" >&2
        exit 1
    fi
    sort "$2.raw" >"$2"
    if [ ! -s "$2" ]; then
        echo "REBUILD FAIL: no code listed in $1" >&2
        exit 1
    fi
}

code "$PULLED" "$WORK/published"
code "$IMAGE" "$WORK/rebuilt"

if cmp -s "$WORK/published" "$WORK/rebuilt"; then
    echo "REBUILD OK"
    exit 0
fi

echo "REBUILD FAIL: the code differs from the published image's" >&2
diff -u "$WORK/published" "$WORK/rebuilt" >&2 || true
exit 1
