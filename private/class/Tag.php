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
     * Return the tag-cloud data: the `CLOUD_SIZE` most-used tags with their
     * visible-bookmark count, ordered alphabetically.
     *
     * A viewer counts public bookmarks plus their own private ones; tags with
     * no visible bookmark are not returned.
     *
     * @param int|null $viewer The viewer's user id (`Auth::id()`), or null for anonymous.
     * @return list<array<string, mixed>> One row per tag (columns: `id`, `name`, `value`).
     */
    private function getCloud(?int $viewer = null): array
    {
        $cond = '';
        $param = false;

        if ($viewer !== null) {
            $cond .= ' OR `bookmark`.`user` = :viewer';
            $param = [[':viewer', $viewer, PDO::PARAM_INT, 255]];
        }

        $limit = CLOUD_SIZE;

        return $this->read(
            <<<SQL
            SELECT * FROM (
                SELECT
                    `tag`.`id`, `tag`.`name`, COUNT(`bookmark_tag`.`tag`) AS `value`
                FROM `tag`
                JOIN
                    `bookmark_tag` ON (`bookmark_tag`.`tag` = `tag`.`id`)
                JOIN
                    `bookmark` ON (`bookmark`.`id` = `bookmark_tag`.`bookmark`)
                WHERE
                    (`bookmark`.`visibility` = 'public' $cond)
                GROUP BY
                    `tag`.`id`, `tag`.`name`
                ORDER BY
                    `value` DESC, `name` ASC
                LIMIT $limit
            ) AS `cloud`
            ORDER BY
                `name` ASC
            SQL,
            $param,
        );
    }

    /**
     * Return one page of the tags whose name contains the search term, with
     * their visible-bookmark count, ordered alphabetically. Same visibility
     * rules as the cloud. An empty term matches every tag (the tag index).
     *
     * @param int|null $viewer The viewer's user id (`Auth::id()`), or null for anonymous.
     * @param string $term The search term (matched as a substring).
     * @param int $page 1-based page number (`TAGS_PAGE_SIZE` tags per page).
     * @return list<array<string, mixed>> One row per tag (columns: `id`, `name`, `value`).
     */
    private function searchTags(?int $viewer, string $term, int $page = 1): array
    {
        [$cond, $param] = $this->searchFilter($viewer, $term);
        $limit = TAGS_PAGE_SIZE;
        $offset = ($page - 1) * TAGS_PAGE_SIZE;

        return $this->read(
            <<<SQL
            SELECT
                `tag`.`id`, `tag`.`name`, COUNT(`bookmark_tag`.`tag`) AS `value`
            FROM `tag`
            JOIN
                `bookmark_tag` ON (`bookmark_tag`.`tag` = `tag`.`id`)
            JOIN
                `bookmark` ON (`bookmark`.`id` = `bookmark_tag`.`bookmark`)
            WHERE
                (`bookmark`.`visibility` = 'public' $cond)
                AND `tag`.`name` LIKE :term
            GROUP BY
                `tag`.`id`, `tag`.`name`
            ORDER BY
                `name` ASC
            LIMIT $limit OFFSET $offset
            SQL,
            $param,
        );
    }

    /**
     * Count the tags whose name contains the search term (see `searchTags`).
     *
     * @param int|null $viewer The viewer's user id (`Auth::id()`), or null for anonymous.
     * @param string $term The search term (matched as a substring).
     * @return int How many visible tags match.
     */
    private function countTags(?int $viewer, string $term): int
    {
        [$cond, $param] = $this->searchFilter($viewer, $term);

        $rows = $this->read(
            <<<SQL
            SELECT
                COUNT(DISTINCT `tag`.`id`) AS `total`
            FROM `tag`
            JOIN
                `bookmark_tag` ON (`bookmark_tag`.`tag` = `tag`.`id`)
            JOIN
                `bookmark` ON (`bookmark`.`id` = `bookmark_tag`.`bookmark`)
            WHERE
                (`bookmark`.`visibility` = 'public' $cond)
                AND `tag`.`name` LIKE :term
            SQL,
            $param,
        );

        return (int) $rows[0]['total'];
    }

    /**
     * The shared visibility + search-term WHERE pieces for tag searches.
     *
     * @param int|null $viewer The viewer's user id, or null for anonymous.
     * @param string $term The search term (LIKE wildcards are escaped).
     * @return array{0: string, 1: list<array{0: string, 1: mixed, 2: int, 3: int}>}
     */
    private function searchFilter(?int $viewer, string $term): array
    {
        $cond = '';
        $param = [[':term', '%' . addcslashes($term, '%_\\') . '%', PDO::PARAM_STR, 255]];

        if ($viewer !== null) {
            $cond .= ' OR `bookmark`.`user` = :viewer';
            $param[] = [':viewer', $viewer, PDO::PARAM_INT, 255];
        }

        return [$cond, $param];
    }

}
