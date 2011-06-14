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
 * @file admin.php
 * @brief Site administration default page.
 *
 * This page show the admin page.
**/

require_once __DIR__.'/config.php';

define('DIR_PRI', realpath(PRI_DIR).'/');

require_once DIR_PRI.'preload.php';
require_once DIR_PRI.'postload.php';

$template = new Template;
$template->show();

// 1 - User and password (TODO)
/*
if ($login) {
  switch ($page) {
    case 'bookmark':      // Bookmark administration (TODO)
    case 'profile':       // Profile modification (TODO)
    case 'general':       // General configuration (TODO)
    case 'users':         // User administration (TODO)
      break;

  }
}
*/
?>