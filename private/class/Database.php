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
 * @file database.php
 * @brief Database initialization.
 *
 * Database select to initialize the PDO instance
 *
 * @todo PostgreSQL and SQLite support
 *
 * @class Database
 * @brief  Configuration file checking and PDO instance.
 */

class Database {
  static protected $db;

/**
 * @fn __construct
 * @brief To open a database connection.
 * @return Connection between PHP and a database server or and E_USER_ERROR.
 */

  final public function __construct() {
    if(!static::$db) {
      try {
        static::$db = new PDO($this->pdoMySQL(), DB_USER, DB_PASS);
      } catch (PDOException $e) {
        trigger_error($e->getMessage(), E_USER_ERROR);
      }
    }
  }

/**
 * @fn pdoMySQL
 * @brief Generic interface to connect to MySQL PDO.
 * @return DSN for connecting to MySQL database.
 */

  final private function pdoMySQL() {
    $dsn = 'mysql:';
    // Unix Socket
    if (strncmp(DB_HOST, '/', 1)) {
      // DB Host
      DB_HOST? $dsn .= 'host='.DB_HOST: $dsn .= 'host=localhost';
      // DB Port
      if (DB_PORT AND is_int(DB_PORT) AND DB_PORT != 3306)
        $dsn .= ';port='.DB_PORT;
    } else {
      $dsn .= 'unix_socket='.DB_HOST;
    }
    // DB Name
    if (DB_NAME)
      $dsn .= ';dbname='.DB_NAME;
    return $dsn;
  }

}