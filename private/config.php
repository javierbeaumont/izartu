<?php
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

/**
 * Site configuration: the catalogue of settings and their defaults. Every one
 * can be overridden through an environment variable of the same name, so a
 * deployment never needs to edit this (tracked) file. Fixed wiring lives in
 * `bootstrap.php`.
 */

/** Database host ('localhost' by default). */
define('DB_HOST', env('DB_HOST', 'localhost'));
/** Database user (required, no default). */
define('DB_USER', env('DB_USER'));
/** Database password (required, no default). */
define('DB_PASS', env('DB_PASS'));
/** Database name ('izartu' by default). */
define('DB_NAME', env('DB_NAME', 'izartu'));
/** Database port (3306 by default). */
define('DB_PORT', (int) env('DB_PORT', '3306'));

/** Bookmarks per page on the feed. */
define('PAGE_SIZE', max(1, (int) env('PAGE_SIZE', '10')));

/** Most-used tags shown in the tag cloud. */
define('CLOUD_SIZE', max(1, (int) env('CLOUD_SIZE', '50')));

/** Tags per page on the tag index (`/tags`). */
define('TAGS_PAGE_SIZE', max(1, (int) env('TAGS_PAGE_SIZE', '100')));

/**
 * Debug mode (off by default; `1`/`true`/`on`/`yes` enable it): error output,
 * the Server-Timing header and the query panel.
 */
define('DEBUG', filter_var(env('DEBUG'), FILTER_VALIDATE_BOOL));
