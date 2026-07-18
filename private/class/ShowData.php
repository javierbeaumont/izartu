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
 * Render bookmark listings.
 */
final class ShowData extends Data {
    use Tag;

  /**
   * Echo the bookmark list ordered by modification date.
   *
   * @param bool $edit Reserved: whether to render edit controls (not yet used).
   * @return void
   */
  final public function listOrderByDate(bool $edit = true): void {
    $table = $this->orderDataByDate();
    foreach ($table as $data) {
      $list = $this->getTags($data['id']);
      $tag = FALSE;
      foreach ($list as $value) {
        $tag .= $value['name'].', ';
      }
      echo '
        <div id="list">';
      include PRI_DIR.'template/contentlist.php';
      echo '
        </div>';
    }
  }

}
