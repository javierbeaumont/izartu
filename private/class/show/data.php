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
 * @file show/data.php
 * @brief Methods to display Data data.
 */

final class DataShow extends Data {

  public function listOrderByDate() {
    $table = parent::orderByDate();
    for ($i = 0, $x = count($table); $i < $x; $i ++) {
      $data = $table[$i];
      $tag2 = parent::readTag($table[$i]['id']);
      $tag = FALSE;
      for ($j = 0, $y = count($tag2); $j < $y; $j ++) {
        $tag .= $tag2[$j]['name'].' ';
      }
      include(PRI_DIR.'template/default/content/list.php');
    }
  }
}

?>