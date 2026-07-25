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
final class ShowBookmark extends Bookmark
{
    use Tag;

    /**
     * Echo the bookmark list ordered by modification date.
     *
     * @param bool $edit Whether to render the edit/delete controls on rows the
     *   current user may manage (pass `Auth::check()`).
     * @param bool $publicOnly Whether to list only public bookmarks (pass
     *   `!Auth::check()`; anonymous visitors never see private bookmarks).
     * @return void
     */
    final public function listOrderByDate(bool $edit = false, bool $publicOnly = true): void
    {
        $table = $this->orderByDate($publicOnly);
        foreach ($table as $bookmark) {
            $list = $this->getTags($bookmark->id);
            $tag = false;
            foreach ($list as $value) {
                $tag .= $value['name'] . ', ';
            }
            echo '
        <div id="list">';
            include PRI_DIR . 'template/contentlist.php';
            echo '
        </div>';
        }
    }

}
