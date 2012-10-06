<?php
#  Izartu
#
#  Copyright © 2011-2012 Javier Beaumont <contact@javierbeaumont.org>
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

/**
 * @file index.php
 * @brief Site default page.
 *
 * This page show the bookmarks related data. Two types will be allowed:
 * - Single: bookmark page.                  Izartu / Bookmark / TITLE
 * - List (default): bookmarks list. Two groups:
 *   - Defined:
 *     - By tag:                             Izartu / Tag / NAME
 *     - By linker:                          Izartu / Linker / NICK
 *     - By modified date:                   Izartu / Date / YEAR/MONTH/DAY
 *     - ...
 *   - Undefined:
 *     - Ordered by title:                   Izartu / Title
 *     - Ordered by linker:                  Izartu / Linker
 *     - Ordered by modified date (default): Izartu / Date
 *     - ...
 */

require_once __DIR__.'/config.php';

/* Autoloading Classes */
spl_autoload_register(function ($class) {
    require PRI_DIR . 'class/' . $class . '.php';
});

if (DEBUG) {
  ini_set('display_errors', 'stdout');
  error_reporting (E_ALL | E_STRICT);
  $benchmark = new Benchmark;
}

require_once PRI_DIR.'template/layout.php';

/**
 * @mainpage
 *   Izartu is a web bookmark manager based on tags.
 *
 * @par Purpose
 *   The primary purpose of its development is learn some programming issues.
 * @par Requisites
 *   Izartu is developer with PHP and MySQL. Doxygen is used for source code
 *   documentation.
 */