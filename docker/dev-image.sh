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

# Builds the dev image of a test platform, php or mysql, from docker/dev/<platform>-<version>/ as
# izartu-dev-<platform>:<version>. The version is PHP_VERSION or MYSQL_VERSION, or else latest: the newest there, also
# tagged :latest, Compose's default. An image already labelled with the hash of that directory's files and this script
# is not rebuilt, nor are its base images pulled. With --hash, it prints that hash instead.

set -eu

cd "$(dirname "$0")/.."

case "${1:-}" in
    php) version=${PHP_VERSION:-latest} ;;
    mysql) version=${MYSQL_VERSION:-latest} ;;
    *) echo "usage: $0 php|mysql [--hash]" >&2; exit 2 ;;
esac

newest=$(for dir in docker/dev/"$1"-*; do echo "${dir#docker/dev/"$1"-}"; done | sort -V | tail -n 1)
[ "$version" != latest ] || version=$newest
dir=docker/dev/$1-$version
tag=izartu-dev-$1:$version
[ -f "$dir/Dockerfile" ] || { echo "no $dir/Dockerfile" >&2; exit 1; }

files=$(git ls-files --cached --others --exclude-standard "$dir")
[ -n "$files" ] || { echo "git lists no files in $dir" >&2; exit 1; }
want=$(printf '%s\n' "$files" | LC_ALL=C sort -u | xargs cat docker/dev-image.sh | git hash-object --stdin)

case "${2:-}" in
    "") ;;
    --hash) echo "$want"; exit 0 ;;
    *) echo "usage: $0 php|mysql [--hash]" >&2; exit 2 ;;
esac

have=$(docker image inspect -f '{{index .Config.Labels "izartu.dev"}}' "$tag" 2>/dev/null || true)

if [ "$have" = "$want" ]; then
    echo "$tag: up to date"
else
    docker build --load --label "izartu.dev=$want" -t "$tag" "$dir"
fi

[ "$version" != "$newest" ] || docker tag "$tag" "izartu-dev-$1:latest"
