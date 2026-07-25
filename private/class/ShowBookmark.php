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
     * Echo one page of the bookmark list ordered by modification date.
     *
     * @param bool $edit Whether to render the edit/delete controls on rows the
     *   current user may manage (pass `Auth::check()`).
     * @param bool $publicOnly Whether to list only public bookmarks (pass
     *   `!Auth::check()`; anonymous visitors never see private bookmarks).
     * @param int $page 1-based page number.
     * @return int The total number of pages (at least 1).
     */
    final public function listOrderByDate(bool $edit = false, bool $publicOnly = true, int $page = 1): int
    {
        ['bookmarks' => $table, 'pages' => $pages] = $this->orderByDate($publicOnly, $page);
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

        return $pages;
    }

    /**
     * The page numbers a pager should display: first, last, and a window
     * around the current page, with null marking each gap (an ellipsis).
     *
     * @param int $page Current 1-based page number.
     * @param int $pages Total number of pages.
     * @param int $radius How many pages to show on each side of the current one.
     * @return list<int|null> Page numbers in order, null where pages are skipped.
     */
    public static function pageWindow(int $page, int $pages, int $radius = 2): array
    {
        $numbers = [1, $pages];
        for ($n = $page - $radius; $n <= $page + $radius; $n++) {
            if ($n >= 1 && $n <= $pages) {
                $numbers[] = $n;
            }
        }
        $numbers = array_values(array_unique($numbers));
        sort($numbers);

        $window = [];
        foreach ($numbers as $i => $n) {
            if ($i > 0 && $n > $numbers[$i - 1] + 1) {
                $window[] = null;
            }
            $window[] = $n;
        }

        return $window;
    }

}
