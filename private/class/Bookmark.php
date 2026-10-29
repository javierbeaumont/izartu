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
 * A bookmark: a typed record plus its own persistence (Active Record).
 */
class Bookmark extends Crud
{
    public ?int $id = null;
    public string $title = '';
    public string $hlink = '';
    public string $text = '';
    public int $user = 0;
    /** Owner's display name, read via JOIN; never written back. */
    public string $username = '';
    public Visibility $visibility = Visibility::Private;
    /** Creation time, as epoch seconds. */
    public ?int $created = null;
    /** Last-modification time, as epoch seconds. */
    public ?int $modified = null;

    /**
     * Find a bookmark by id.
     *
     * @param int $id The bookmark id.
     * @return self|null The hydrated bookmark, or null if none has that id.
     */
    public static function find(int $id): ?self
    {
        $rows = (new self())->select(' WHERE `bookmark`.`id` = :id', [[':id', $id, PDO::PARAM_INT, 255]]);

        return $rows ? self::hydrate($rows[0]) : null;
    }

    /**
     * Insert this bookmark when it is new, or update it when it has an id.
     *
     * `created` and `modified` are left to the database, so both come from one clock; this object does not learn their
     * values until it is read back.
     *
     * @return void
     */
    public function save(): void
    {
        if ($this->id) {
            $query = self::db()->prepare(
                <<<'SQL'
                UPDATE `bookmark`
                SET
                    `title` = :title, `hlink` = :hlink, `text` = :text, `visibility` = :visibility
                WHERE
                    `id` = :id
                SQL,
            );

            $query->bindValue(':id', $this->id, PDO::PARAM_INT);
        } else {
            $query = self::db()->prepare(
                <<<'SQL'
                INSERT INTO `bookmark`
                    (`title`, `hlink`, `text`, `user`, `visibility`)
                VALUES
                    (:title, :hlink, :text, :user, :visibility)
                SQL,
            );

            $query->bindValue(':user', $this->user, PDO::PARAM_INT);
        }

        $query->bindValue(':title', $this->title);
        $query->bindValue(':hlink', $this->hlink);
        $query->bindValue(':text', $this->text);
        $query->bindValue(':visibility', $this->visibility->value);
        $query->execute();

        if (!$this->id) {
            $this->id = (int) self::db()->lastInsertId();
        }
    }

    /**
     * Whether a viewer may see this bookmark.
     *
     * Public bookmarks are visible to everyone; private ones only to their
     * owner (no role sees another user's private bookmarks).
     *
     * @param int|null $viewer The viewer's user id, or null for anonymous.
     * @return bool
     */
    public function visibleTo(?int $viewer): bool
    {
        return $this->visibility === Visibility::Public
            || ($viewer !== null && $this->user === $viewer);
    }

    /**
     * Delete this bookmark and its tag links.
     *
     * @return void
     */
    public function delete(): void
    {
        $this->deleteTags();

        $query = self::db()->prepare(
            <<<'SQL'
            DELETE FROM `bookmark`
            WHERE
                `id` = :id
            SQL,
        );

        $query->bindValue(':id', $this->id, PDO::PARAM_INT);
        $query->execute();

        $this->id = null;
    }

    /**
     * Split a comma-separated tag string into normalised tag names.
     *
     * @param string $tags Comma-separated tag names, as typed by the user.
     * @return list<string> Lower-case, trimmed names with inner whitespace
     *   collapsed, deduplicated; empties dropped.
     */
    public static function parseTags(string $tags): array
    {
        $names = array_map(
            static fn(string $name): string => mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name)),
            explode(',', $tags),
        );

        return array_values(array_unique(array_filter($names, static fn(string $name): bool => $name !== '')));
    }

    /**
     * Replace this bookmark's tags with the ones in a comma-separated string.
     *
     * The names missing from `tag` are created, and every name is linked in `bookmark_tag` by name, so a name that
     * exists spends no id and two spellings of one tag (the `ai_ci` collation) make one link.
     *
     * @param string $tags Comma-separated tag names, as typed by the user.
     * @return void
     * @throws PDOException When a new tag has no id left, or the database fails.
     */
    public function saveTags(string $tags): void
    {
        $this->deleteTags();

        $names = self::parseTags($tags);
        if (!$names) {
            return;
        }

        $create = self::db()->prepare(
            <<<'SQL'
            INSERT INTO `tag`
                (`name`)
            SELECT
                `candidate`.`name`
            FROM (SELECT :name AS `name`) AS `candidate`
            WHERE
                NOT EXISTS (SELECT 1 FROM `tag` WHERE `tag`.`name` = `candidate`.`name`)
            SQL,
        );

        foreach ($names as $name) {
            try {
                $create->execute([':name' => $name]);
            } catch (PDOException $e) {
                // Another save created the same tag at once (a deadlock, or the name taken): the second run finds it.
                if (!in_array($e->errorInfo[1] ?? null, [1062, 1213], true)) {
                    throw $e;
                }

                $create->execute([':name' => $name]);
            }
        }

        $in = [];
        $param = [':bookmark' => $this->id];
        foreach ($names as $i => $name) {
            $in[] = ':name' . $i;
            $param[':name' . $i] = $name;
        }

        self::db()->prepare(
            sprintf(
                <<<'SQL'
                INSERT INTO `bookmark_tag`
                    (`bookmark`, `tag`)
                SELECT DISTINCT
                    :bookmark, `id`
                FROM `tag`
                WHERE
                    `name` IN (%s)
                SQL,
                implode(', ', $in),
            ),
        )->execute($param);
    }

    /**
     * The tag names of several bookmarks in a single query (one page's worth).
     *
     * @param list<int> $ids Bookmark ids.
     * @return array<int, list<string>> Tag names (alphabetical) per bookmark
     *   id; ids without tags are absent.
     */
    public function tagsFor(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $in = [];
        $param = [];
        foreach ($ids as $i => $id) {
            $in[] = ':id' . $i;
            $param[] = [':id' . $i, $id, PDO::PARAM_INT, 255];
        }

        /** @var list<array{bookmark: int, name: string}> $rows */
        $rows = $this->read(
            sprintf(
                <<<'SQL'
                SELECT
                    `bookmark_tag`.`bookmark`, `tag`.`name`
                FROM `bookmark_tag`
                JOIN
                    `tag` ON (`tag`.`id` = `bookmark_tag`.`tag`)
                WHERE
                    `bookmark_tag`.`bookmark` IN (%s)
                ORDER BY
                    `tag`.`name`
                SQL,
                implode(', ', $in),
            ),
            $param,
        );

        $tags = [];
        foreach ($rows as $row) {
            $tags[$row['bookmark']][] = $row['name'];
        }

        return $tags;
    }

    /**
     * Unlink every tag from this bookmark.
     *
     * @return void
     */
    public function deleteTags(): void
    {
        $query = self::db()->prepare(
            <<<'SQL'
            DELETE FROM `bookmark_tag`
            WHERE
                `bookmark` = :id
            SQL,
        );

        $query->bindValue(':id', $this->id, PDO::PARAM_INT);
        $query->execute();
    }

    /**
     * Read one page of bookmarks ordered by modification date.
     *
     * @param int|null $viewer The viewer's user id (`Auth::id()`), or null for
     *   anonymous. A viewer sees public bookmarks plus their own private ones.
     * @param int $page 1-based page number; each page holds `PAGE_SIZE` bookmarks.
     * @param list<string> $tags Only bookmarks carrying ALL these tag names; empty for all.
     * @param string|null $username Only bookmarks added by this user, or null for all.
     * @param bool $order true for ascending order, false (default) for descending.
     * @return array{bookmarks: list<self>, pages: int} The page's bookmarks
     *   (newest first by default) and the total number of pages (at least 1).
     */
    final public function orderByDate(
        ?int $viewer = null,
        int $page = 1,
        array $tags = [],
        ?string $username = null,
        bool $order = false,
    ): array {
        [$cond, $param] = $this->filter($viewer, $tags, $username);
        $cond .= ' ORDER BY `bookmark`.`modified` ' . ($order ? 'ASC' : 'DESC');
        $cond .= ' LIMIT ' . PAGE_SIZE . ' OFFSET ' . (($page - 1) * PAGE_SIZE);

        return [
            'bookmarks' => array_map(self::hydrate(...), $this->select($cond, $param)),
            'pages' => max(1, (int) ceil($this->count($viewer, $tags, $username) / PAGE_SIZE)),
        ];
    }

    /**
     * Build the WHERE clause a listing needs (visibility and tag filters).
     *
     * @param int|null $viewer The viewer's user id, or null for anonymous.
     * @param list<string> $tags Only bookmarks carrying ALL these tag names; empty for all.
     * @param string|null $username Only bookmarks added by this user, or null for all.
     * @return array{0: string, 1: list<array{0: string, 1: mixed, 2: int, 3: int}>|false}
     *   The WHERE clause (never empty) and its bind parameters.
     */
    private function filter(?int $viewer, array $tags = [], ?string $username = null): array
    {
        $where = [];
        $param = [];

        if ($viewer === null) {
            $where[] = "`bookmark`.`visibility` = 'public'";
        } else {
            $where[] = "(`bookmark`.`visibility` = 'public' OR `bookmark`.`user` = :viewer)";
            $param[] = [':viewer', $viewer, PDO::PARAM_INT, 255];
        }
        if ($tags) {
            $in = [];
            foreach ($tags as $i => $name) {
                $in[] = ':tag' . $i;
                $param[] = [':tag' . $i, $name, PDO::PARAM_STR, 255];
            }
            $where[] = sprintf(
                <<<'SQL'
                `bookmark`.`id` IN (
                    SELECT `bookmark`
                    FROM `bookmark_tag`
                    JOIN `tag` ON (`tag`.`id` = `bookmark_tag`.`tag`)
                    WHERE `tag`.`name` IN (%s)
                    GROUP BY `bookmark`
                    HAVING COUNT(DISTINCT `tag`.`id`) = %d
                )
                SQL,
                implode(', ', $in),
                count($tags),
            );
        }
        if ($username !== null) {
            $where[] = '`user`.`username` = :username';
            $param[] = [':username', $username, PDO::PARAM_STR, 255];
        }

        return [' WHERE ' . implode(' AND ', $where), $param ?: false];
    }

    /**
     * Count the bookmarks the current listing can see.
     *
     * @param int|null $viewer The viewer's user id, or null for anonymous.
     * @param list<string> $tags Only bookmarks carrying ALL these tag names; empty for all.
     * @param string|null $username Only bookmarks added by this user, or null for all.
     * @return int The bookmark count.
     */
    private function count(?int $viewer, array $tags = [], ?string $username = null): int
    {
        [$cond, $param] = $this->filter($viewer, $tags, $username);

        /** @var list<array{total: int}> $rows */
        $rows = $this->read(
            <<<SQL
            SELECT
                COUNT(*) AS `total`
            FROM `bookmark`
            LEFT JOIN
                `user` ON (`user`.`id` = `bookmark`.`user`)
            $cond
            SQL,
            $param,
        );

        return $rows[0]['total'];
    }

    /**
     * Read bookmark rows matching an SQL condition.
     *
     * @param string|false $cond Extra SQL appended to the base SELECT, or false.
     * @param list<array{0: string, 1: mixed, 2: int, 3: int}>|false $param
     *   Bind parameters for $cond (each: [name, value, PDO type, length]), or false.
     * @return list<array{id: int, title: string, hlink: string, text: string, user: int, visibility: string,
     *   created: int, modified: int, username: string|null}> One raw row per bookmark.
     */
    private function select(string|false $cond, array|false $param): array
    {
        /**
         * @var list<array{id: int, title: string, hlink: string, text: string, user: int, visibility: string,
         *   created: int, modified: int, username: string|null}> $rows
         */
        $rows = $this->read(
            <<<SQL
            SELECT
                `bookmark`.`id`, `bookmark`.`title`, `bookmark`.`hlink`, `bookmark`.`text`,
                `bookmark`.`user`, `bookmark`.`visibility`,
                UNIX_TIMESTAMP(`bookmark`.`created`) AS `created`,
                UNIX_TIMESTAMP(`bookmark`.`modified`) AS `modified`,
                `user`.`username`
            FROM `bookmark`
            LEFT JOIN
                `user` ON (`user`.`id` = `bookmark`.`user`)
            $cond
            SQL,
            $param,
        );

        return $rows;
    }

    /**
     * Build a Bookmark from a database row.
     *
     * @param array{id: int, title: string, hlink: string, text: string, user: int, visibility: string, created: int,
     *   modified: int, username: string|null} $row A bookmark row (see `select`).
     * @return self The hydrated bookmark.
     */
    private static function hydrate(array $row): self
    {
        $bookmark = new self();
        $bookmark->id = $row['id'];
        $bookmark->title = $row['title'];
        $bookmark->hlink = $row['hlink'];
        $bookmark->text = $row['text'];
        $bookmark->user = $row['user'];
        $bookmark->username = (string) ($row['username'] ?? '');
        $bookmark->visibility = Visibility::from($row['visibility']);
        $bookmark->created = $row['created'];
        $bookmark->modified = $row['modified'];

        return $bookmark;
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
