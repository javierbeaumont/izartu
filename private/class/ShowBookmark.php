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
     * @param int|null $viewer The viewer's user id (`Auth::id()`), or null for
     *   anonymous. A viewer sees public bookmarks plus their own private ones.
     * @param int $page 1-based page number.
     * @param string|null $tag Only bookmarks carrying this tag name, or null for all.
     * @return int The total number of pages (at least 1).
     */
    final public function listOrderByDate(
        bool $edit = false,
        ?int $viewer = null,
        int $page = 1,
        ?string $tag = null,
    ): int {
        ['bookmarks' => $table, 'pages' => $pages] = $this->orderByDate($viewer, $page, $tag);
        foreach ($table as $bookmark) {
            $list = $this->getTags($bookmark->id);
            $tags = false;
            foreach ($list as $value) {
                $tags .= '<a href="tag/' . rawurlencode($value['name']) . '">'
                    . htmlspecialchars($value['name']) . '</a>, ';
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
