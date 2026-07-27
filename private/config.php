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
 * Site configuration: the operator-editable settings (database connection,
 * feed size, runtime flags). Fixed wiring lives in `bootstrap.php`.
 */

################################################################################
############################# BASIC CONFIGURATION ##############################
################################################################################

/** Database host ('localhost' by default). */
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
/** Database user (from the `DB_USER` environment variable). */
define('DB_USER', getenv('DB_USER'));
/** Database password (from the `DB_PASS` environment variable). */
define('DB_PASS', getenv('DB_PASS'));
/** Database name ('izartu' by default). */
define('DB_NAME', getenv('DB_NAME') ?: 'izartu');

/** Bookmarks per page on the feed. */
define('PAGE_SIZE', 10);

################################################################################
############################ ADVANCED CONFIGURATION ############################
################################################################################

/** Database type. Only `MySQL` is supported. */
define('DB_TYPE', 'MySQL');
/** Database port. Only 3306 is supported. */
define('DB_PORT', 3306);

################################################################################
############################ DEVELOPER CONFIGURATION ###########################
################################################################################

/** Debug mode: TRUE enables error output, the Server-Timing header and the query panel. Must be FALSE in production. */
define('DEBUG', true);
