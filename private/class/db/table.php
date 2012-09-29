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
 * @file table.php
 * @brief Database table general class.
 *
 * General actions used in a database table.
 *
 * @class Table
 * @brief  General actions class.
 */

class Table {
  private static $db;

/**
 * @fn __construct
 * @brief To initialize a new database PDO instance.
 */

  public function __construct () {
    self::$db = Database::open();
  }

/**
 * @fn process
 * @brief To save data in a database table
 * @param $query SQL query
 * @return Rows in a database table (or an error)
 */

  private static function process($query) {
    if (empty($query)) {
      trigger_error('Data not found', E_USER_ERROR);
    } else {
      $data = $query->fetchAll(PDO::FETCH_ASSOC);
      $query->closeCursor();
      return $data;
    }
  }

/**
 * @fn save
 * @brief To save data in a database table
 * @param $sql SQL query
 * @param $param Query parameters
 */

  public static function save($sql, $param) {
    if ($param['id']) {
      $query = self::$db->prepare($sql);
      $query->bindParam($param['id'][0], $param['id'][1], $param['id'][2], $param['id'][3]);
    } else {
      $query = $this->db->prepare($id);
    }
    foreach ($param as $value) {
      $query->bindParam($value[0], $value[1], $value[2], $value[3]);
    }
    $query->execute();
    return self::process($query);
  }

/**
 * @fn read
 * @brief To read data in a database table
 * @param $sql SQL query
 * @param $param Query parameters
 * @return Rows in a database table
 */

  public static function read($sql, $param = FALSE) {
    $query = self::$db->prepare($sql);
    if (is_array($param)) {
      foreach ($param as $value) {
        $query->bindParam($value[0], $value[1], $value[2], $value[3]);
      }
    }
    $query->execute();
    return self::process($query);
  }

/**
 * @fn delete
 * @brief To delete data in a database table
 * @param $sql SQL query
 * @param $param Query parameters
 */

  public static function delete($sql, $param) {
    $query = self::$db->prepare($sql);
    foreach ($param as $value) {
      $query->bindParam($value[0], $value[1], $value[2], $value[3]);
    }
    $query->execute();
    return self::process($query);
  }

}