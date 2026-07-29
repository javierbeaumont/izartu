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
    public ?string $add = null;
    public ?string $mod = null;

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
     * @return void
     */
    public function save(): void
    {
        if ($this->id) {
            $query = static::$db->prepare(
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
            $this->add = date('Y-m-d H:i:s');
            $query = static::$db->prepare(
                <<<'SQL'
                INSERT INTO `bookmark`
                    (`title`, `hlink`, `text`, `user`, `visibility`, `add`)
                VALUES
                    (:title, :hlink, :text, :user, :visibility, :add)
                SQL,
            );

            $query->bindValue(':user', $this->user, PDO::PARAM_INT);
            $query->bindValue(':add', $this->add);
        }

        $query->bindValue(':title', $this->title);
        $query->bindValue(':hlink', $this->hlink);
        $query->bindValue(':text', $this->text);
        $query->bindValue(':visibility', $this->visibility->value);
        $query->execute();

        if (!$this->id) {
            $this->id = (int) static::$db->lastInsertId();
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

        $query = static::$db->prepare(
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
            static fn(string $name): string => mb_strtolower(trim(preg_replace('/\s+/', ' ', $name))),
            explode(',', $tags),
        );

        return array_values(array_unique(array_filter($names, static fn(string $name): bool => $name !== '')));
    }

    /**
     * Replace this bookmark's tags with the ones in a comma-separated string.
     *
     * Each name is upserted into `tag` (reusing the id when the name exists)
     * and linked in `bookmark_tag`.
     *
     * @param string $tags Comma-separated tag names, as typed by the user.
     * @return void
     */
    public function saveTags(string $tags): void
    {
        $this->deleteTags();

        $tag = static::$db->prepare(
            <<<'SQL'
            INSERT INTO `tag`
                (`name`)
            VALUES
                (:name)
            ON DUPLICATE KEY UPDATE
                `id` = LAST_INSERT_ID(`id`)
            SQL,
        );

        $link = static::$db->prepare(
            <<<'SQL'
            INSERT INTO `bookmark_tag`
                (`bookmark`, `tag`)
            VALUES
                (:bookmark, :tag)
            SQL,
        );

        foreach (self::parseTags($tags) as $name) {
            $tag->execute([':name' => $name]);
            $link->execute([':bookmark' => $this->id, ':tag' => (int) static::$db->lastInsertId()]);
        }
    }

    /**
     * The names of this bookmark's tags.
     *
     * @return list<string> Tag names, alphabetical.
     */
    public function tags(): array
    {
        $rows = $this->read(
            <<<'SQL'
            SELECT
                `name`
            FROM `tag`
            WHERE
                `id` IN (
                    SELECT
                        `tag`
                    FROM `bookmark_tag`
                    WHERE
                        `bookmark` = :id
                )
            ORDER BY
                `name`
            SQL,
            [[':id', $this->id, PDO::PARAM_INT, 255]],
        );

        return array_column($rows, 'name');
    }

    /**
     * Unlink every tag from this bookmark.
     *
     * @return void
     */
    public function deleteTags(): void
    {
        $query = static::$db->prepare(
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
        $cond .= ' ORDER BY `bookmark`.`mod` ' . ($order ? 'ASC' : 'DESC');
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
            foreach (array_values($tags) as $i => $name) {
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

        return (int) $rows[0]['total'];
    }

    /**
     * Read bookmark rows matching an SQL condition.
     *
     * @param string|false $cond Extra SQL appended to the base SELECT, or false.
     * @param list<array{0: string, 1: mixed, 2: int, 3: int}>|false $param
     *   Bind parameters for $cond (each: [name, value, PDO type, length]), or false.
     * @return list<array<string, mixed>> One raw row per bookmark.
     */
    private function select(string|false $cond, array|false $param): array
    {
        return $this->read(
            <<<SQL
            SELECT
                `bookmark`.`id`, `bookmark`.`title`, `bookmark`.`hlink`, `bookmark`.`text`,
                `bookmark`.`user`, `bookmark`.`visibility`, `bookmark`.`add`, `bookmark`.`mod`,
                `user`.`username`
            FROM `bookmark`
            LEFT JOIN
                `user` ON (`user`.`id` = `bookmark`.`user`)
            $cond
            SQL,
            $param,
        );
    }

    /**
     * Build a Bookmark from a database row.
     *
     * @param array<string, mixed> $row A bookmark row with the selected columns.
     * @return self The hydrated bookmark.
     */
    private static function hydrate(array $row): self
    {
        $bookmark = new self();
        $bookmark->id = (int) $row['id'];
        $bookmark->title = $row['title'];
        $bookmark->hlink = $row['hlink'];
        $bookmark->text = $row['text'];
        $bookmark->user = (int) $row['user'];
        $bookmark->username = (string) ($row['username'] ?? '');
        $bookmark->visibility = Visibility::from($row['visibility']);
        $bookmark->add = $row['add'];
        $bookmark->mod = $row['mod'];

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
