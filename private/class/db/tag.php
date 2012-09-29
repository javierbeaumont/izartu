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
 * @file db/tag.php
 * @brief The tag class.
 *
 * Tag methods..
 *
 * @class Tag
 * @brief Tag related methods.
**/

class Tag {

/**
* @fn read
* @brief Read tag.
*/

  public static function read($id) {
    $param[0] = array(':data', $id, PDO::PARAM_INT, 255);
    return Table::read('SELECT `id`, `name` FROM `'.PREFIX.'tag`
                        WHERE `id` IN
                        (SELECT `tag`
                         FROM `'.PREFIX.'data_tag`
                         WHERE `data` = :data)',
                        $param);
  }

/**
* @fn getCloud
* @brief Get tags for tagcloud.
*/

  protected function getCloud($cond = FALSE, $param = FALSE) {
    return Table::read('SELECT `tag`.`id`, `tag`.`name`, COUNT(`data_tag`.`tag`) AS `value`
                        FROM `tag`
                        LEFT JOIN `data_tag` ON (`data_tag`.`tag` = `tag`.`id`)'.
                        $cond.'
                        GROUP BY `tag`.`name`',
                        $param);
  }

/**
* @fn getCloudByLang
* @brief Get tags for tagcloud by lang.
*/

  protected function getCloudByLang() {
    $cond = 'WHERE `tag`.`lang` = :lang';
    $param[0] = array(':lang', LANG_ID, PDO::PARAM_INT, 5);
    return self::getCloud ($cond, $param[0]);
  }

}