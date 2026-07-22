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
 * A bookmark record and its read queries.
 */
class Bookmark extends Crud
{
    /**
     * Read bookmarks matching an SQL condition.
     *
     * @param string|false $cond Extra SQL appended to the base SELECT, or false.
     * @param list<array{0: string, 1: mixed, 2: int, 3: int}>|false $param
     *   Bind parameters for $cond (each: [name, value, PDO type, length]), or false.
     * @return list<array<string, mixed>> One row per bookmark.
     */
    private function select(string|false $cond, array|false $param): array
    {
        return $this->read(
            <<<SQL
            SELECT
                `id`, `title`, `hlink`, `hlang`, `htype`, `text`, `user`, `add`, `mod`
            FROM
                `bookmark`
            $cond
            SQL,
            $param,
        );
    }

    /**
     * Read bookmarks ordered by modification date.
     *
     * @param array<string, mixed>|false $search Filters (e.g. `lang`), or false for none.
     * @param bool $order true for ascending order, false (default) for descending.
     * @return list<array<string, mixed>> One row per bookmark.
     */
    final protected function orderByDate(array|false $search = false, bool $order = false): array
    {
        $cond = $param = false;
        if (!empty($search) and array_key_exists('lang', $search) and $search['lang']) {
            $param[0] = [':lang', $search['lang'], PDO::PARAM_INT, 255];
            $cond .= ' WHERE `lang` = :lang';
        }
        $order ? $order = 'ASC' : $order = 'DESC';
        $cond .= ' ORDER BY `mod` ' . $order;

        return $this->select($cond, $param);
    }

}
