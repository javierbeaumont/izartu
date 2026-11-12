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

# Builds the `system` stage twice without its cache, PHP still from the `php` stage's, and compares the layers: the same
# inputs have to make the same layers, so that a rebuild ships only what changed. For each layer that differs, prints
# how its entries differ, dates to the second: a file a step left undated shows there.

set -eu

cd "$(dirname "$0")/.."

TAG="izartu-check-layers:$$"
WORK="$(mktemp -d)"

trap 'docker rmi -f "$TAG-a" "$TAG-b" >/dev/null 2>&1 || true; rm -rf "$WORK"' EXIT
trap 'exit 1' INT TERM

for build in a b; do
    docker build --load --target system --no-cache-filter system -t "$TAG-$build" .
    docker image inspect --format '{{range .RootFS.Layers}}{{println .}}{{end}}' "$TAG-$build" >"$WORK/$build"
done

if cmp -s "$WORK/a" "$WORK/b"; then
    echo "LAYERS OK"
    exit 0
fi

# The Nth layer's entries, listed by the image's busybox; the saved layer may be compressed or not.
entries() { # BUILD N
    blob="$(sed -n 's/.*"Layers":\[\([^]]*\)\].*/\1/p' "$WORK/$1.save/manifest.json" | tr ',' '\n' | tr -d '"' \
        | sed -n "$2p")"
    docker run --rm -i "$TAG-a" sh -c 'cat >/tmp/layer && { gzip -dc /tmp/layer 2>/dev/null || cat /tmp/layer; } \
        | tar -tvf -' <"$WORK/$1.save/$blob"
}

echo "LAYERS FAIL: two builds of the system stage made different layers" >&2
for build in a b; do
    mkdir "$WORK/$build.save"
    docker save "$TAG-$build" | tar -x -C "$WORK/$build.save"
done
paste "$WORK/a" "$WORK/b" | awk '$1 != $2 { print NR }' | while read -r n; do
    echo "Layer $n:" >&2
    entries a "$n" >"$WORK/a.$n"
    entries b "$n" >"$WORK/b.$n"
    diff "$WORK/a.$n" "$WORK/b.$n" >&2 || true
    ! cmp -s "$WORK/a.$n" "$WORK/b.$n" || echo "  the same entries and dates: a file's contents differ" >&2
done
exit 1
