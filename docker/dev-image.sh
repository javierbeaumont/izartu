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

# Builds the dev image, izartu-dev:PHP_VERSION. An image already labelled with the hash of the Dockerfile and this
# script is not rebuilt, nor are its base images pulled.

set -eu

cd "$(dirname "$0")/.."

php=${PHP_VERSION:-8.5}
tag=izartu-dev:$php
want=$(cat Dockerfile docker/dev-image.sh | git hash-object --stdin)
have=$(docker image inspect -f '{{index .Config.Labels "izartu.dev"}}' "$tag" 2>/dev/null || true)

if [ "$have" = "$want" ]; then
    echo "$tag: up to date"
    exit 0
fi

docker build --load --target test --build-arg "PHP_VERSION=$php" --label "izartu.dev=$want" -t "$tag" .
