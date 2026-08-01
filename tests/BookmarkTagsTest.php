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

use PHPUnit\Framework\TestCase;

final class BookmarkTagsTest extends TestCase
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

    public function testParseTagsNormalisesNames(): void
    {
        $this->assertSame(
            ['php', 'sql', 'web dev'],
            Bookmark::parseTags(' PHP, sql , php, , Web Dev,'),
        );
    }

    public function testParseTagsCollapsesInnerWhitespace(): void
    {
        $this->assertSame(
            ['web dev'],
            Bookmark::parseTags("web  dev, web\tdev, web dev"),
        );
    }

    public function testSaveTagsCreatesAndLinksTags(): void
    {
        $bookmark = $this->savedBookmark();

        $bookmark->saveTags('php, sql');

        $this->assertSame(['php', 'sql'], $this->linkedTagNames($bookmark->id));
    }

    public function testSaveTagsReusesAnExistingTag(): void
    {
        $this->pdo->exec("INSERT INTO `tag` (`name`) VALUES ('php')");
        $bookmark = $this->savedBookmark();

        $bookmark->saveTags('php');

        $count = $this->pdo->query("SELECT COUNT(*) FROM `tag` WHERE `name` = 'php'")->fetchColumn();
        $this->assertSame(1, (int) $count);
        $this->assertSame(['php'], $this->linkedTagNames($bookmark->id));
    }

    public function testSaveTagsReplacesPreviousLinks(): void
    {
        $bookmark = $this->savedBookmark();
        $bookmark->saveTags('php, sql');

        $bookmark->saveTags('web');

        $this->assertSame(['web'], $this->linkedTagNames($bookmark->id));
    }

    public function testTagsReturnsTheNamesAlphabetically(): void
    {
        $bookmark = $this->savedBookmark();
        $bookmark->saveTags('sql, php');

        $this->assertSame(['php', 'sql'], $bookmark->tags());
    }

    public function testTagsForBatchesAPageInOneMap(): void
    {
        $first = $this->savedBookmark();
        $first->saveTags('sql, php');
        $second = $this->savedBookmark();
        $second->saveTags('web');
        $bare = $this->savedBookmark();

        $tags = $first->tagsFor([$first->id, $second->id, $bare->id]);

        $this->assertSame(['php', 'sql'], $tags[$first->id], 'alphabetical per bookmark');
        $this->assertSame(['web'], $tags[$second->id]);
        $this->assertArrayNotHasKey($bare->id, $tags, 'untagged ids are absent');
        $this->assertSame([], $first->tagsFor([]));
    }

    public function testDeleteRemovesTheBookmarkAndItsLinks(): void
    {
        $bookmark = $this->savedBookmark();
        $bookmark->saveTags('php');
        $id = $bookmark->id;

        $bookmark->delete();

        $this->assertNull($bookmark->id);
        $this->assertNull(Bookmark::find($id));
        $links = $this->pdo->query('SELECT COUNT(*) FROM `bookmark_tag`')->fetchColumn();
        $this->assertSame(0, (int) $links);
    }

    private function savedBookmark(): Bookmark
    {
        $bookmark = new Bookmark();
        $bookmark->title = 'Tagged';
        $bookmark->hlink = 'https://t.test';
        $bookmark->text = 't';
        $bookmark->user = 1;
        $bookmark->save();

        return $bookmark;
    }

    /**
     * @return list<string> The names linked to a bookmark, alphabetical.
     */
    private function linkedTagNames(int $bookmark): array
    {
        $query = $this->pdo->prepare(
            <<<'SQL'
            SELECT
                `tag`.`name`
            FROM `tag`
            JOIN
                `bookmark_tag` ON (`bookmark_tag`.`tag` = `tag`.`id`)
            WHERE
                `bookmark_tag`.`bookmark` = :bookmark
            ORDER BY
                `tag`.`name`
            SQL,
        );
        $query->execute([':bookmark' => $bookmark]);

        return $query->fetchAll(PDO::FETCH_COLUMN);
    }
}
