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

/**
 * @file preload.php
 * @brief The data and files to load before the page content.
 *
 * Loads database and internationalization files.
 */

if (DEBUG) {
  ini_set('display_errors', 'stdout');
  error_reporting (E_ALL | E_STRICT);
  require_once DIR_PRI.'class/benchmark.php';
  new Benchmark;
}

/* Database */
require_once DIR_PRI.'class/db/database.php';
require_once DIR_PRI.'class/db/table.php';

new Table();

/* Internationalization */
require_once DIR_PRI.'class/i18n.php';
require_once DIR_PRI.'class/php-gettext/gettext.inc';

$i18n = new I18n;
define ('LANG', $i18n->getLang());

_setlocale(LC_MESSAGES, LANG);
_bindtextdomain('messages', DIR_PRI.'locale');
_bind_textdomain_codeset('messages', 'UTF-8');
_textdomain('messages');

?>