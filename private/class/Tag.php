<?php

//  izartu: web bookmark manager based on tags
//
//  Copyright (C) 2011-2026 Javier Beaumont <javierbeaumont@users.noreply.github.com>
//
//  This file is part of izartu.
//
//  izartu is free software: you can redistribute it and/or modify
//  it under the terms of the GNU Affero General Public License as
//  published by the Free Software Foundation, either version 3 of the
//  License, or (at your option) any later version.
//
//  izartu is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
//  GNU Affero General Public License for more details.
//
//  You should have received a copy of the GNU Affero General Public License
//  along with izartu. If not, see <https://www.gnu.org/licenses/>.

/**
 * Tag queries: a bookmark's tags and the tag-cloud data.
 */
trait Tag
{
    /**
     * Return the tags attached to a single bookmark.
     *
     * @param int $id Bookmark id.
     * @return list<array{id: int, name: string}> One row per tag.
     */
    private function getTags(int $id): array
    {
        $param[0] = [':bookmark', $id, PDO::PARAM_INT, 255];

        /** @var list<array{id: int, name: string}> $rows */
        $rows = $this->read(
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

        return $rows;
    }

    /**
     * Return the tag-cloud data for the current list: the `CLOUD_SIZE`
     * most-used tags among the bookmarks the list shows, with their counts,
     * ordered alphabetically.
     *
     * A viewer counts public bookmarks plus their own private ones; tags with
     * no visible bookmark are not returned. With `$tags`, only bookmarks
     * carrying ALL of them count, and those tags themselves are left out
     * (they are the active filter). With `$username`, only that user's
     * bookmarks count.
     *
     * @param int|null $viewer The viewer's user id (`Auth::id()`), or null for anonymous.
     * @param list<string> $tags The tag names the list is filtered by; empty for none.
     * @param string|null $username The username the list is filtered by, or null.
     * @return list<array{id: int, name: string, value: int}> One row per tag.
     */
    private function getCloud(?int $viewer = null, array $tags = [], ?string $username = null): array
    {
        [$cond, $join, $extra, $param] = $this->listFilter($viewer, $tags, $username);
        $limit = CLOUD_SIZE;

        /** @var list<array{id: int, name: string, value: int}> $rows */
        $rows = $this->read(
            <<<SQL
            SELECT * FROM (
                SELECT
                    `tag`.`id`, `tag`.`name`, COUNT(`bookmark_tag`.`tag`) AS `value`
                FROM `tag`
                JOIN
                    `bookmark_tag` ON (`bookmark_tag`.`tag` = `tag`.`id`)
                JOIN
                    `bookmark` ON (`bookmark`.`id` = `bookmark_tag`.`bookmark`)
                $join
                WHERE
                    (`bookmark`.`visibility` = 'public' $cond)
                    $extra
                GROUP BY
                    `tag`.`id`, `tag`.`name`
                ORDER BY
                    `value` DESC, `name` ASC
                LIMIT $limit
            ) AS `cloud`
            ORDER BY
                `name` ASC
            SQL,
            $param ?: false,
        );

        return $rows;
    }

    /**
     * Return one page of the tags whose name contains the search term, with
     * their visible-bookmark count, ordered alphabetically. Same visibility
     * and list filters as the cloud (`$tags`/`$username` scope the search to
     * the current list). An empty term matches every tag (the tag index).
     *
     * @param int|null $viewer The viewer's user id (`Auth::id()`), or null for anonymous.
     * @param string $term The search term (matched as a substring).
     * @param int $page 1-based page number (`TAGS_PAGE_SIZE` tags per page).
     * @param list<string> $tags The tag names the list is filtered by; empty for none.
     * @param string|null $username The username the list is filtered by, or null.
     * @return list<array{id: int, name: string, value: int}> One row per tag.
     */
    private function searchTags(
        ?int $viewer,
        string $term,
        int $page = 1,
        array $tags = [],
        ?string $username = null,
    ): array {
        [$cond, $join, $extra, $param] = $this->listFilter($viewer, $tags, $username);
        $param[] = [':term', '%' . addcslashes($term, '%_\\') . '%', PDO::PARAM_STR, 255];
        $limit = TAGS_PAGE_SIZE;
        $offset = ($page - 1) * TAGS_PAGE_SIZE;

        /** @var list<array{id: int, name: string, value: int}> $rows */
        $rows = $this->read(
            <<<SQL
            SELECT
                `tag`.`id`, `tag`.`name`, COUNT(`bookmark_tag`.`tag`) AS `value`
            FROM `tag`
            JOIN
                `bookmark_tag` ON (`bookmark_tag`.`tag` = `tag`.`id`)
            JOIN
                `bookmark` ON (`bookmark`.`id` = `bookmark_tag`.`bookmark`)
            $join
            WHERE
                (`bookmark`.`visibility` = 'public' $cond)
                $extra
                AND `tag`.`name` LIKE :term
            GROUP BY
                `tag`.`id`, `tag`.`name`
            ORDER BY
                `name` ASC
            LIMIT $limit OFFSET $offset
            SQL,
            $param,
        );

        return $rows;
    }

    /**
     * Count the tags whose name contains the search term (see `searchTags`).
     *
     * @param int|null $viewer The viewer's user id (`Auth::id()`), or null for anonymous.
     * @param string $term The search term (matched as a substring).
     * @param list<string> $tags The tag names the list is filtered by; empty for none.
     * @param string|null $username The username the list is filtered by, or null.
     * @return int How many visible tags match.
     */
    private function countTags(?int $viewer, string $term, array $tags = [], ?string $username = null): int
    {
        [$cond, $join, $extra, $param] = $this->listFilter($viewer, $tags, $username);
        $param[] = [':term', '%' . addcslashes($term, '%_\\') . '%', PDO::PARAM_STR, 255];

        /** @var list<array{total: int}> $rows */
        $rows = $this->read(
            <<<SQL
            SELECT
                COUNT(DISTINCT `tag`.`id`) AS `total`
            FROM `tag`
            JOIN
                `bookmark_tag` ON (`bookmark_tag`.`tag` = `tag`.`id`)
            JOIN
                `bookmark` ON (`bookmark`.`id` = `bookmark_tag`.`bookmark`)
            $join
            WHERE
                (`bookmark`.`visibility` = 'public' $cond)
                $extra
                AND `tag`.`name` LIKE :term
            SQL,
            $param,
        );

        return $rows[0]['total'];
    }

    /**
     * The SQL pieces every tag listing shares: the visibility condition, the
     * `user` join and extra WHERE clauses for the list's tag/username filters
     * (with the filtered tag names themselves excluded), and the parameters.
     *
     * @param int|null $viewer The viewer's user id, or null for anonymous.
     * @param list<string> $tags The tag names the list is filtered by; empty for none.
     * @param string|null $username The username the list is filtered by, or null.
     * @return array{0: string, 1: string, 2: string, 3: list<array{0: string, 1: mixed, 2: int, 3: int}>}
     */
    private function listFilter(?int $viewer, array $tags, ?string $username): array
    {
        $cond = '';
        $join = '';
        $extra = '';
        $param = [];

        if ($viewer !== null) {
            $cond .= ' OR `bookmark`.`user` = :viewer';
            $param[] = [':viewer', $viewer, PDO::PARAM_INT, 255];
        }
        if ($tags) {
            $in = [];
            foreach ($tags as $i => $name) {
                $in[] = ':tag' . $i;
                $param[] = [':tag' . $i, $name, PDO::PARAM_STR, 255];
                $param[] = [':not' . $i, $name, PDO::PARAM_STR, 255];
            }
            $extra .= sprintf(
                <<<'SQL'

                    AND `bookmark`.`id` IN (
                        SELECT `bookmark`
                        FROM `bookmark_tag`
                        JOIN `tag` ON (`tag`.`id` = `bookmark_tag`.`tag`)
                        WHERE `tag`.`name` IN (%s)
                        GROUP BY `bookmark`
                        HAVING COUNT(DISTINCT `tag`.`id`) = %d
                    )
                    AND `tag`.`name` NOT IN (%s)
                SQL,
                implode(', ', $in),
                count($tags),
                implode(', ', array_map(static fn(string $p): string => str_replace(':tag', ':not', $p), $in)),
            );
        }
        if ($username !== null) {
            $join = ' LEFT JOIN `user` ON (`user`.`id` = `bookmark`.`user`)';
            $extra .= ' AND `user`.`username` = :username';
            $param[] = [':username', $username, PDO::PARAM_STR, 255];
        }

        return [$cond, $join, $extra, $param];
    }

}
