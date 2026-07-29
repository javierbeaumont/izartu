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

final class TagSearchTest extends TestCase
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

        // Bookmarks 1 and 3 are public; 2 is private to user 1.
        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `bookmark`
                (`id`, `title`, `hlink`, `text`, `user`, `visibility`, `add`, `mod`)
            VALUES
                (1, 'Pub A', 'https://a.test', '', 1, 'public', '2026-01-01 10:00:00', '2026-01-01 10:00:00'),
                (2, 'Priv', 'https://p.test', '', 1, 'private', '2026-01-01 11:00:00', '2026-01-01 11:00:00'),
                (3, 'Pub B', 'https://b.test', '', 1, 'public', '2026-01-01 12:00:00', '2026-01-01 12:00:00')
            SQL,
        );
    }

    public function testTheCloudKeepsTheMostUsedTagsAndSortsThemAlphabetically(): void
    {
        for ($n = 1; $n <= 55; $n++) {
            $this->tag(sprintf('tag%02d', $n), [1]);
        }
        $this->tag('zz-top', [1, 3]);

        $cloud = $this->probe()->cloud();
        $names = array_column($cloud, 'name');

        $this->assertCount(CLOUD_SIZE, $cloud);
        $this->assertContains('zz-top', $names, 'the most-used tag survives the cap');
        $this->assertNotContains('tag55', $names);

        $sorted = $names;
        sort($sorted);
        $this->assertSame($sorted, $names, 'the cloud is shown alphabetically');
    }

    public function testSearchMatchesSubstringsAlphabetically(): void
    {
        $this->tag('php', [1]);
        $this->tag('phpunit', [3]);
        $this->tag('sql', [1]);

        $names = array_column($this->probe()->search(null, 'ph'), 'name');

        $this->assertSame(['php', 'phpunit'], $names);
    }

    public function testSearchHidesPrivateOnlyTagsFromStrangers(): void
    {
        $this->tag('secreto', [2]);

        $this->assertSame([], $this->probe()->search(null, 'secreto'));
        $this->assertSame([], $this->probe()->search(2, 'secreto'), 'another user');

        $owner = $this->probe()->search(1, 'secreto');
        $this->assertSame('secreto', $owner[0]['name']);
        $this->assertSame(1, (int) $owner[0]['value']);
        $this->assertSame(0, $this->probe()->count(null, 'secreto'));
    }

    public function testAnEmptyTermIsTheFullIndexAndPaginates(): void
    {
        for ($n = 1; $n <= TAGS_PAGE_SIZE + 5; $n++) {
            $this->tag(sprintf('tag%03d', $n), [1]);
        }

        $this->assertSame(TAGS_PAGE_SIZE + 5, $this->probe()->count(null, ''));

        $first = array_column($this->probe()->search(null, '', 1), 'name');
        $this->assertCount(TAGS_PAGE_SIZE, $first);
        $this->assertSame('tag001', $first[0]);

        $second = array_column($this->probe()->search(null, '', 2), 'name');
        $this->assertCount(5, $second);
        $this->assertSame(sprintf('tag%03d', TAGS_PAGE_SIZE + 1), $second[0]);
    }

    public function testSearchScopedToTagsOnlySeesCooccurringTags(): void
    {
        $this->tag('php', [1]);
        $this->tag('docs', [1]);
        $this->tag('doctrine', [3]);

        $names = array_column($this->probe()->search(null, 'doc', 1, ['php']), 'name');

        $this->assertSame(['docs'], $names, 'doctrine does not co-occur with php');
        $this->assertSame([], $this->probe()->search(null, 'ph', 1, ['php']), 'the active filter is excluded');
        $this->assertSame(1, $this->probe()->count(null, 'doc', ['php']));
    }

    public function testSearchScopedToAUserOnlySeesTheirTags(): void
    {
        $this->pdo->exec('TRUNCATE `user`');
        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `user`
                (`id`, `username`, `email`, `hash`, `role`)
            VALUES
                (1, 'javi', 'javi@izartu.test', 'x', 'user'),
                (2, 'bob', 'bob@izartu.test', 'x', 'user')
            SQL,
        );
        $this->pdo->exec('UPDATE `bookmark` SET `user` = 2 WHERE `id` = 3');
        $this->tag('php', [1]);
        $this->tag('vim', [3]);

        $this->assertSame(['php'], array_column($this->probe()->search(null, '', 1, [], 'javi'), 'name'));
        $this->assertSame(1, $this->probe()->count(null, '', [], 'javi'));
    }

    public function testSearchTreatsLikeWildcardsAsLiterals(): void
    {
        $this->tag('a%b', [1]);
        $this->tag('plain', [1]);

        $names = array_column($this->probe()->search(null, '%'), 'name');

        $this->assertSame(['a%b'], $names);
    }

    private function tag(string $name, array $bookmarks): void
    {
        $query = $this->pdo->prepare('INSERT INTO `tag` (`name`) VALUES (:name)');
        $query->execute([':name' => $name]);
        $id = (int) $this->pdo->lastInsertId();

        $link = $this->pdo->prepare('INSERT INTO `bookmark_tag` (`bookmark`, `tag`) VALUES (:bookmark, :tag)');
        foreach ($bookmarks as $bookmark) {
            $link->execute([':bookmark' => $bookmark, ':tag' => $id]);
        }
    }

    private function probe(): Crud
    {
        return new class extends Crud {
            use Tag;

            public function cloud(?int $viewer = null): array
            {
                return $this->getCloud($viewer);
            }

            public function search(
                ?int $viewer,
                string $term,
                int $page = 1,
                array $tags = [],
                ?string $username = null,
            ): array {
                return $this->searchTags($viewer, $term, $page, $tags, $username);
            }

            public function count(?int $viewer, string $term, array $tags = [], ?string $username = null): int
            {
                return $this->countTags($viewer, $term, $tags, $username);
            }
        };
    }
}
