<?php

#  Izartu
#
#  Copyright © 2011 Javier Beaumont <contact@javierbeaumont.org>
#
#  This file is part of Izartu.
#
#  Izartu is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as
#  published by the Free Software Foundation, either version 3 of the
#  License, or (at your option) any later version.
#
#  Izartu is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
#  GNU Affero General Public License for more details.
#
#  You should have received a copy of the GNU Affero General Public License
#  along with Izartu. If not, see <http://www.gnu.org/licenses/>.

################################################################################
############################# BASIC CONFIGURATION ##############################
################################################################################

# PRI_DIR -- 'private/' directory location (and name).
#             Relative to this config.php archive.

define('PRI_DIR', '../private/');

# DB_HOST Host where database is located. 'localhost' by default.
# DB_USER User of the database.
# DB_PASS Password of the database.
# DB_NAME Name of the database.

define('DB_HOST', 'localhost');
define('DB_USER', 'izartuuser');
define('DB_PASS', 'izartupass');
define('DB_NAME', 'izartu');

################################################################################
############################ ADVANCED CONFIGURATION ############################
################################################################################

# DB_TYPE -- Database type. At this moment 'MySQL' is the unique option allowed.
#            'MySQL' by default.
# DB_PORT -- Port of the database. 3306 (MySQL default port).
#            At this moment 3306 is the unique port number allowed.

define('DB_TYPE', 'MySQL');
define('DB_PORT', 3306);

# PREFIX -- Database tables prefix. For example: define('PREFIX', 'iz_');
#           FALSE (none) by default.

define('PREFIX', FALSE);

################################################################################
############################ DEVELOPER CONFIGURATION ###########################
################################################################################

# DEBUG -- Activate (TRUE) or deactivate (FALSE) the debug mode.
#          In real environment ALWAYS DEACTIVATED (FALSE).

define('DEBUG', TRUE);

/**
 * @file config.php
 * @brief Site configuration file.
 *
 * @enum PRI_DIR 'private/' directory location (and name). Relative to this
 *   config.php archive.
 * @enum DB_HOST Host where database is located. 'localhost' by default.
 * @enum DB_USER User of the database.
 * @enum DB_PASS Password of the database.
 * @enum DB_NAME Name of the database.
 * @enum DB_TYPE Database type. At this moment 'MySQL' is the unique option
 *   allowed. 'MySQL' by default.
 * @enum DB_PORT Port of the database. 3306 (MySQL default port). At this moment
 *   3306 is the unique port number allowed.
 * @enum PREFIX Database tables prefix. For example: @code define('PREFIX',
 *   'iz_'); @endcode. FALSE (none) by default.
 * @enum DEBUG Activate (TRUE) or deactivate (FALSE) the debug mode. In real
 *   environment ALWAYS DEACTIVATED.
 */
?>