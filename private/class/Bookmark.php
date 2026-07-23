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
                `id`, `title`, `hlink`, `text`, `user`, `add`, `mod`
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
     * @param bool $order true for ascending order, false (default) for descending.
     * @return list<array<string, mixed>> One row per bookmark.
     */
    final protected function orderByDate(bool $order = false): array
    {
        $order = $order ? 'ASC' : 'DESC';

        return $this->select(' ORDER BY `mod` ' . $order, false);
    }

}
