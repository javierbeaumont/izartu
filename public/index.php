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
 * Site default page: renders the bookmark data.
 *
 * Two view types are planned:
 * - Single: a single bookmark page.            izartu / Bookmark / TITLE
 * - List (default), in two groups:
 *   - Defined:
 *     - By tag:                                 izartu / Tag / NAME
 *     - By linker:                              izartu / Linker / NICK
 *     - By modified date:                       izartu / Date / YEAR/MONTH/DAY
 *     - ...
 *   - Undefined:
 *     - Ordered by title:                       izartu / Title
 *     - Ordered by linker:                      izartu / Linker
 *     - Ordered by modified date (default):     izartu / Date
 *     - ...
 */

require_once __DIR__.'/config.php';

/* Autoloading Classes */
spl_autoload_register(function ($class) {
    require PRI_DIR . 'class/' . $class . '.php';
});

if (DEBUG) {
  ini_set('display_errors', 'stdout');
  error_reporting (E_ALL);
  $benchmark = new Benchmark;
}

require_once PRI_DIR.'template/layout.php';
