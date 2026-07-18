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
 * A bookmark (data) record and its read/write queries.
 */
class Data extends Crud {

  /** @var int|null Bookmark id. */
  private $id;
  /** @var int|null Language id. */
  private $lang;
  /** @var int|null Type id. */
  private $type;
  /** @var string|null Title. */
  private $title;
  /** @var string|null Target URL. */
  private $hlink;
  /** @var int|null Target language id. */
  private $hlang;
  /** @var int|null Target type id. */
  private $htype;
  /** @var string|null Description text. */
  private $text;
  /** @var string|null Owner nick. */
  private $user;
  /** @var string|null Creation datetime. */
  private $add;
  /** @var string|null Last-modified datetime. */
  private $mod;

  /**
   * Insert or update the current bookmark.
   *
   * @return void
   */
  private function saveData(): void {
    if ($this->id) {
      $query = static::$db->prepare('
        UPDATE `'.PREFIX.'data`
        SET
          `lang` =  :lang,
          `title` = :title,
          `hlink` = :hlink,
          `hlang` = :hlang,
          `htype` = :htype,
          `text` =  :text,
        WHERE `id` = :id');
      $query->bindParam(':id', $this->id, PDO::PARAM_INT, 255);
    } else {
      $query = static::$db->prepare('
        INSERT INTO `'.PREFIX.'data` (
          `lang`, `title`, `hlink`, `hlang`, `htype`, `text`, `user`, `add`
        )
        VALUES (
          :lang,  :title,  :hlink,  :hlang,  :htype,  :text,  :user,  :add
        )');
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
   * Read bookmarks matching an SQL condition.
   *
   * @param string|false $cond Extra SQL appended to the base SELECT, or false.
   * @param list<array{0: string, 1: mixed, 2: int, 3: int}>|false $param
   *   Bind parameters for $cond (each: [name, value, PDO type, length]), or false.
   * @return list<array<string, mixed>> One row per bookmark.
   */
  private function readData(string|false $cond, array|false $param): array {
    return $this->read('
      SELECT
        `id`, `title`, `hlink`, `hlang`, `htype`, `text`, `user`, `add`, `mod`
      FROM `'.PREFIX.'data`' . $cond,
      $param);
  }

  /**
   * Delete the current bookmark.
   *
   * @return void
   */
  private function deleteData(): void {
    $query = static::$db->prepare('DELETE FROM `'.PREFIX.'data` WHERE `id` = :id');
    $query->bindParam(':id', $this->id, PDO::PARAM_INT, 255);
    $query->execute();
  }

  /**
   * Read bookmarks ordered by modification date.
   *
   * @param array<string, mixed>|false $search Filters (e.g. `lang`), or false for none.
   * @param bool $order true for ascending order, false (default) for descending.
   * @return list<array<string, mixed>> One row per bookmark.
   */
  final protected function orderDataByDate(array|false $search = false, bool $order = false): array {
    $cond = $param = FALSE;
    if (!empty($search) AND array_key_exists('lang', $search) AND $search['lang']){
      $param[0] = array(':lang', $search['lang'], PDO::PARAM_INT, 255);
      $cond .= ' WHERE AND `lang` = :lang';
    }
    $order ? $order = 'ASC' : $order = 'DESC';
    $cond .= ' ORDER BY `mod` '.$order;

    return $this->readData($cond, $param);
  }

}
