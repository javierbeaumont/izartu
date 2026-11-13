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

# Checks that one platform's image in a registry, PUSHED, has the same layers as a local image, IMAGE, by their diff
# IDs.

set -eu

if [ "$#" -ne 2 ] || [ -z "$1" ] || [ -z "$2" ]; then
    echo "usage: $0 IMAGE PUSHED, e.g. izartu-e2e ghcr.io/javierbeaumont/izartu@sha256:<digest>" >&2
    exit 2
fi
IMAGE="$1"
PUSHED="$2"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
trap 'exit 1' INT TERM

docker image inspect --format '{{json .RootFS.Layers}}' "$IMAGE" >"$WORK/image.json"
if ! jq -er 'arrays | .[]' "$WORK/image.json" >"$WORK/image"; then
    echo "PUSHED FAIL: $IMAGE has no layers" >&2
    exit 1
fi

docker buildx imagetools inspect "$PUSHED" --format '{{json .Image}}' >"$WORK/pushed.json"
if ! jq -er '.rootfs.diff_ids | arrays | .[]' "$WORK/pushed.json" >"$WORK/pushed"; then
    echo "PUSHED FAIL: $PUSHED is not one platform's image" >&2
    exit 1
fi

if cmp -s "$WORK/image" "$WORK/pushed"; then
    echo "PUSHED OK"
    exit 0
fi

echo "PUSHED FAIL: $PUSHED has other layers than $IMAGE" >&2
diff "$WORK/image" "$WORK/pushed" >&2 || true
exit 1
