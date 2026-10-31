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

# Gives what a build step created or changed the date of /etc/alpine-release, so the same packages make the same layer.
# Docker's mounts stay as they are, and so does /usr/local, which has PHP's own date.
find /bin /etc /home /lib /root /run /sbin /tmp /usr /var -xdev \
    \( -path /etc/hosts -o -path /etc/resolv.conf -o -path /usr/local \) -prune \
    -o -newer /etc/alpine-release -exec touch -h -r /etc/alpine-release {} +
