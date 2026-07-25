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

use PHPUnit\Framework\TestCase;

final class ReadPathTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (DB_NAME !== 'izartu_test') {
            self::fail('Refusing to run against "' . DB_NAME . '"; expected izartu_test.');
        }

        $this->pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        foreach (['bookmark_tag', 'bookmark', 'tag'] as $table) {
            $this->pdo->exec('TRUNCATE `' . $table . '`');
        }
    }

    public function testOrderByDateReturnsBookmarksNewestFirst(): void
    {
        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `bookmark`
                (`title`, `hlink`, `text`, `user`, `add`, `mod`)
            VALUES
                ('Older', 'https://a.test', 'a', 1, '2026-01-01 10:00:00', '2026-01-01 10:00:00'),
                ('Newer', 'https://b.test', 'b', 1, '2026-01-02 10:00:00', '2026-01-02 10:00:00')
            SQL,
        );

        $rows = $this->probe()->order();

        $this->assertSame(['Newer', 'Older'], array_column($rows, 'title'));
    }

    public function testGetTagsReturnsTheTagsOfABookmark(): void
    {
        $this->seedBookmarkWithTags();

        $names = array_column($this->probe()->tagsOf(1), 'name');
        sort($names);

        $this->assertSame(['php', 'sql'], $names);
    }

    public function testTagCloudCountsBookmarksPerTag(): void
    {
        $this->seedBookmarkWithTags();

        $cloud = $this->probe()->cloud();
        $counts = array_column($cloud, 'value', 'name');

        $this->assertSame(1, (int) $counts['php']);
        $this->assertSame(1, (int) $counts['sql']);
    }

    public function testAnonymousListShowsOnlyPublicBookmarks(): void
    {
        $this->seedPublicAndPrivate();

        $this->assertSame(['Public'], array_column($this->probe()->order(true), 'title'));
        $this->assertSame(['Public', 'Secret'], array_column($this->probe()->order(), 'title'));
    }

    public function testAnonymousCloudCountsOnlyPublicBookmarks(): void
    {
        $this->seedPublicAndPrivate();

        $anonymous = array_column($this->probe()->cloud(true), 'value', 'name');
        $this->assertSame(1, (int) $anonymous['php']);
        $this->assertArrayNotHasKey('secret', $anonymous);

        $full = array_column($this->probe()->cloud(), 'value', 'name');
        $this->assertSame(2, (int) $full['php']);
        $this->assertSame(1, (int) $full['secret']);
    }

    private function seedPublicAndPrivate(): void
    {
        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `bookmark`
                (`id`, `title`, `hlink`, `text`, `user`, `visibility`, `add`, `mod`)
            VALUES
                (1, 'Public', 'https://p.test', 'p', 1, 'public', '2026-01-02 10:00:00', '2026-01-02 10:00:00'),
                (2, 'Secret', 'https://s.test', 's', 1, 'private', '2026-01-01 10:00:00', '2026-01-01 10:00:00')
            SQL,
        );
        $this->pdo->exec("INSERT INTO `tag` (`id`, `name`) VALUES (1, 'php'), (2, 'secret')");
        $this->pdo->exec("INSERT INTO `bookmark_tag` (`bookmark`, `tag`) VALUES (1, 1), (2, 1), (2, 2)");
    }

    private function seedBookmarkWithTags(): void
    {
        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `bookmark`
                (`id`, `title`, `hlink`, `text`, `user`, `add`, `mod`)
            VALUES
                (1, 'B', 'https://b.test', 't', 1, '2026-01-01 10:00:00', '2026-01-01 10:00:00')
            SQL,
        );

        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `tag`
                (`id`, `name`)
            VALUES
                (1, 'php'),
                (2, 'sql')
            SQL,
        );

        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `bookmark_tag`
                (`bookmark`, `tag`)
            VALUES
                (1, 1),
                (1, 2)
            SQL,
        );
    }

    private function probe(): Bookmark
    {
        return new class extends Bookmark {
            use Tag;

            public function order(bool $publicOnly = false): array
            {
                return $this->orderByDate($publicOnly);
            }

            public function tagsOf(int $id): array
            {
                return $this->getTags($id);
            }

            public function cloud(bool $publicOnly = false): array
            {
                return $this->getCloud($publicOnly);
            }
        };
    }
}
