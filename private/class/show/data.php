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
 * @file show/data.php
 * @brief Methods to display Data data.
 */

final class DataShow extends Data {
    private $tag;

/**
 * @fn __construct
 * @brief To initialize a new table.
 */

  public function __construct () {
    $this->tag = new Tag;
  }

  public function listOrderByDate($edit = TRUE) {
    $table = parent::orderByDate();
    foreach ($table as $data) {
      $list = $this->tag->getTag($data['id']);
      $tag = FALSE;
      foreach ($list as $value) {
        $tag .= $value['name'].', ';
      }
      echo '
        <div id="list">';
      include PRI_DIR.'template/content/list.php';
      echo '
        </div>';
    }
  }

}