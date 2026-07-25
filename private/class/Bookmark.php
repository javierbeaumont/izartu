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
        $rows = (new self())->select(' WHERE `id` = :id', [[':id', $id, PDO::PARAM_INT, 255]]);

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
     * @return list<string> Lower-case, trimmed, deduplicated names; empties dropped.
     */
    public static function parseTags(string $tags): array
    {
        $names = array_map(static fn(string $name): string => mb_strtolower(trim($name)), explode(',', $tags));

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
     * Read bookmarks ordered by modification date.
     *
     * @param bool $order true for ascending order, false (default) for descending.
     * @return list<self> One bookmark per row, newest first by default.
     */
    final protected function orderByDate(bool $order = false): array
    {
        $order = $order ? 'ASC' : 'DESC';

        return array_map(self::hydrate(...), $this->select(' ORDER BY `mod` ' . $order, false));
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
                `id`, `title`, `hlink`, `text`, `user`, `visibility`, `add`, `mod`
            FROM `bookmark`
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
        $bookmark->visibility = Visibility::from($row['visibility']);
        $bookmark->add = $row['add'];
        $bookmark->mod = $row['mod'];

        return $bookmark;
    }

}
