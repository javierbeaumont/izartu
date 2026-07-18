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
 * Database access for a table: prepare and run a query, then fetch its rows.
 */
class Crud extends Database {

  /**
   * Return all rows of an executed statement as associative arrays.
   *
   * @param PDOStatement $query Executed statement.
   * @return list<array<string, mixed>> The result rows.
   */
  private function process(PDOStatement $query): array {
    if (empty($query)) {
      trigger_error('Data not found', E_USER_ERROR);
    } else {
      $data = $query->fetchAll(PDO::FETCH_ASSOC);
      $query->closeCursor();
      return $data;
    }
  }

  /**
   * Prepare and run a read query, then return its rows.
   *
   * @param string $sql SQL query with bind placeholders.
   * @param list<array{0: string, 1: mixed, 2: int, 3: int}>|false $param
   *   Bind parameters (each: [name, value, PDO type, length]), or false for none.
   * @return list<array<string, mixed>> The result rows.
   */
  final protected function read(string $sql, array|false $param = false): array {
    $query = static::$db->prepare($sql);
    if (is_array($param)) {
      foreach ($param as $value) {
        $query->bindParam($value[0], $value[1], $value[2], $value[3]);
      }
    }
    $query->execute();
    return $this->process($query);
  }

}
