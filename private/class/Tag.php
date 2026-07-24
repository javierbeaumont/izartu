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
 * Tag queries: a bookmark's tags and the tag-cloud data.
 */
trait Tag
{
    /**
     * Return the tags attached to a single bookmark.
     *
     * @param int $id Bookmark id.
     * @return list<array<string, mixed>> One row per tag (columns: `id`, `name`).
     */
    private function getTags(int $id): array
    {
        $param[0] = [':bookmark', $id, PDO::PARAM_INT, 255];
        return $this->read(
            <<<'SQL'
            SELECT
                `id`, `name`
            FROM `tag`
            WHERE
                `id` IN (
                    SELECT
                        `tag`
                    FROM `bookmark_tag`
                    WHERE
                        `bookmark` = :bookmark
                )
            SQL,
            $param,
        );
    }

    /**
     * Return the tag-cloud data: every tag with its bookmark count.
     *
     * @param string|false $cond Extra SQL appended before `GROUP BY`, or false for none.
     * @param list<array{0: string, 1: mixed, 2: int, 3: int}>|false $param
     *   Bind parameters for $cond (each: [name, value, PDO type, length]), or false.
     * @return list<array<string, mixed>> One row per tag (columns: `id`, `name`, `value`).
     */
    private function getCloud(string|false $cond = false, array|false $param = false): array
    {
        return $this->read(
            <<<SQL
            SELECT
                `tag`.`id`, `tag`.`name`, COUNT(`bookmark_tag`.`tag`) AS `value`
            FROM `tag`
            LEFT JOIN
                `bookmark_tag` ON (`bookmark_tag`.`tag` = `tag`.`id`)
            $cond
            GROUP BY
                `tag`.`id`, `tag`.`name`
            SQL,
            $param,
        );
    }

}
