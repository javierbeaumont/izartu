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
 * @file db/data.php
 * @brief The data class.
 *
 * Data methods.
 *
 * @class Data
 * @brief Data related methods.
**/

class Data {

  private $id;
  private $lang;
  private $type;
  private $title;
  private $hlink;
  private $hlang;
  private $htype;
  private $text;
  private $user;
  private $add;
  private $mod;

/**
* @fn saveData
* @brief Save data.
*/
  private function saveData() {
    if ($this->id) {
      $query = $db->prepare('UPDATE `'.PREFIX.'data`
                              SET   `lang`  = :lang,
                                    `title` = :title,
                                    `hlink` = :hlink,
                                    `hlang` = :hlang,
                                    `htype` = :htype,
                                    `text`  = :text,
                             WHERE  `id`    = :id');
      $query->bindParam(':id', $this->id, PDO::PARAM_INT, 255);
    } else {
      $query = $db->prepare('INSERT INTO `'.PREFIX.'data`
                                    (`lang`, `title`, `hlink`, `hlang`, `htype`, `text`, `user`, `add`)
                             VALUES (:lang,  :title,  :hlink,  :hlang,  :htype,  :text,  :user,  :add)');
      $query->bindParam(':user', $this->user,         PDO::PARAM_STR, 255);
      $query->bindParam(':add',  date('Y-m-d H:i:s'), PDO::PARAM_STR,  19);
    }

    $query->bindParam(':lang',  $this->lang,  PDO::PARAM_INT, 255);
    $query->bindParam(':title', $this->title, PDO::PARAM_STR, 255);
    $query->bindParam(':hlink', $this->hlink, PDO::PARAM_STR, 255);
    $query->bindParam(':hlang', $this->hlang, PDO::PARAM_INT, 255);
    $query->bindParam(':htype', $this->htype, PDO::PARAM_INT, 255);
    $query->bindParam(':text',  $this->text,  PDO::PARAM_STR, 511);
    $query->execute();
  }

/**
* @fn readData
* @brief Read data.
*/
  private function readData($cond, $param) {
    return Table::read('SELECT `id`,   `title`, `hlink`, `hlang`, `htype`,
                                  `text`, `user`,  `add`,    `mod`
                           FROM `'.PREFIX.'data`'.$cond, $param);
  }

/**
* @fn readTag
* @brief Read tag.
*/
  protected function readTag($id) {
    $param[0] = array(':data', $id, PDO::PARAM_INT, 255);
    return Table::read('SELECT `id`, `name` FROM `'.PREFIX.'tag`
                           WHERE `id` IN
                          (SELECT `tag` FROM `'.PREFIX.'data_tag`
                           WHERE `data` = :data)',
                          $param);
  }

/**
* @fn orderByDate
* @brief Read data and order by date.
*/
  protected function orderByDate($search = FALSE, $order = FALSE) {
    $cond = $param = FALSE;
    if (!empty($search) AND array_key_exists('lang', $search) AND $search['lang']){
      $param[0] = array(':lang', $search['lang'], PDO::PARAM_INT, 255);
      $cond .= ' WHERE AND `lang` = :lang';
    }
    $order? $order = 'ASC': $order = 'DESC';
    $cond .= ' ORDER BY `mod` '.$order;

    return self::readData($cond, $param);
  }

/**
* @fn deleteData
* @brief Delete data.
*/
  private function deleteData() {
    $query = $db->prepare('DELETE FROM `'.PREFIX.'data` WHERE `id` = :id');
    $query->bindParam(  ':id',       $this->id,       PDO::PARAM_INT, 255);
    $query->execute();
  }
}

?>