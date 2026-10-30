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
    /**
     * The rival save of the race test, as a second process with its own connection. It locks the gap where `php` would
     * go, waits until the test's insert has been blocked on that lock for a second, then inserts `php` itself. InnoDB
     * rolls back the lighter transaction, weighing the rows it changed and the locks it holds; twenty inserted rows
     * keep the rival the heavier.
     */
    private const RIVAL = <<<'PHP'
        require $argv[1] . 'bootstrap.php';
        $pdo = new PDO(Database::dsn(DB_HOST, DB_PORT, DB_NAME), DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->beginTransaction();
        $pdo->exec('INSERT INTO `tag` (`name`) VALUES ' . implode(', ', array_map(
            fn(int $i): string => "('rival{$i}')",
            range(1, 20),
        )));
        $pdo->query("SELECT 1 FROM `tag` WHERE `name` = 'php' FOR SHARE")->fetchAll();
        echo "ready\n";
        $deadline = microtime(true) + 10;
        do {
            usleep(100000);
            $blocked = false;
            foreach ($pdo->query('SHOW FULL PROCESSLIST') as $thread) {
                $inserting = str_starts_with((string) $thread['Info'], 'INSERT INTO `tag`');
                $blocked = $blocked || ($inserting && $thread['Time'] >= 1);
            }
        } while (!$blocked && microtime(true) < $deadline);
        if (!$blocked) {
            fwrite(STDERR, "The test's insert never blocked.\n");
            exit(1);
        }
        $pdo->exec("INSERT INTO `tag` (`name`) VALUES ('php')");
        $pdo->commit();
        PHP;

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

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['bookmark_tag', 'bookmark', 'tag', 'user'] as $table) {
            $this->pdo->exec('TRUNCATE `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `user`
                (`id`, `username`, `email`, `hash`, `role`)
            VALUES
                (1, 'owner', 'owner@izartu.test', 'x', 'user')
            SQL,
        );
    }

    public function testASaveCreatesAndLinksTags(): void
    {
        $bookmark = $this->savedBookmark();

        $bookmark->saveWithTags('php, sql');

        $this->assertSame(['php', 'sql'], $this->linkedTagNames($bookmark->id));
    }

    public function testASaveReusesAnExistingTag(): void
    {
        $this->pdo->exec("INSERT INTO `tag` (`name`) VALUES ('php')");
        $bookmark = $this->savedBookmark();

        $bookmark->saveWithTags('php');

        $count = $this->pdo->query("SELECT COUNT(*) FROM `tag` WHERE `name` = 'php'")->fetchColumn();
        $this->assertSame(1, (int) $count);
        $this->assertSame(['php'], $this->linkedTagNames($bookmark->id));
    }

    /**
     * Regression: the upsert spent an id every time the tag already existed, so ids ran out long before that many tags
     * existed.
     */
    public function testReusingATagSpendsNoId(): void
    {
        $this->savedBookmark()->saveWithTags('php');
        $this->savedBookmark()->saveWithTags('php');
        $this->savedBookmark()->saveWithTags('php, sql');

        $ids = $this->pdo->query('SELECT `name`, `id` FROM `tag` ORDER BY `id`')->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame(['php' => 1, 'sql' => 2], $ids);
    }

    /**
     * Regression: at the id limit a new tag hit the upsert's duplicate-key path and silently took the id of an existing
     * tag.
     */
    public function testANewTagWithNoIdLeftFails(): void
    {
        $this->pdo->exec('ALTER TABLE `tag` AUTO_INCREMENT = 4294967295');
        $this->savedBookmark()->saveWithTags('php');
        $bookmark = $this->savedBookmark();

        try {
            $bookmark->saveWithTags('sql');
            $this->fail('A tag with no id left must not be saved.');
        } catch (PDOException) {
        }

        $this->assertSame([], $this->linkedTagNames($bookmark->id));
    }

    /**
     * Two saves creating the same new tag at once: under REPEATABLE READ both lock the gap the tag goes into and
     * deadlock, so the loser's transaction runs again and finds the tag the winner created.
     */
    public function testASaveThatLosesTheRaceForANewTagRetries(): void
    {
        Debug::reset();
        $bookmark = $this->savedBookmark();
        $rival = proc_open(
            [PHP_BINARY, '-r', self::RIVAL, PRIVATE_DIR],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertSame("ready\n", fgets($pipes[1]));

        $bookmark->saveWithTags('php');

        $error = (string) stream_get_contents($pipes[2]);
        $this->assertSame(0, proc_close($rival), $error);
        $this->assertSame(['php'], $this->linkedTagNames($bookmark->id));
        $inserts = array_filter(
            Debug::queries(),
            fn(array $query): bool => str_starts_with($query['sql'], 'INSERT INTO `tag`'),
        );
        $this->assertCount(2, $inserts, 'the transaction deadlocked once and ran again');
    }

    public function testAFailedEditLeavesTheBookmarkAndItsTagsAsTheyWere(): void
    {
        $this->pdo->exec('ALTER TABLE `tag` AUTO_INCREMENT = 4294967295');
        $bookmark = $this->savedBookmark();
        $bookmark->saveWithTags('php');
        $bookmark->title = 'Renamed';

        try {
            $bookmark->saveWithTags('sql');
            $this->fail('A tag with no id left must not be saved.');
        } catch (PDOException) {
        }

        $this->assertSame('Tagged', Bookmark::find($bookmark->id)?->title);
        $this->assertSame(['php'], $this->linkedTagNames($bookmark->id));
    }

    public function testAFailedAddLeavesNoBookmark(): void
    {
        $this->pdo->exec('ALTER TABLE `tag` AUTO_INCREMENT = 4294967295');
        $this->savedBookmark()->saveWithTags('php');
        $bookmark = new Bookmark();
        $bookmark->title = 'New';
        $bookmark->hlink = 'https://n.test';
        $bookmark->user = 1;

        try {
            $bookmark->saveWithTags('sql');
            $this->fail('A tag with no id left must not be saved.');
        } catch (PDOException) {
        }

        $this->assertNull($bookmark->id);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM `bookmark`')->fetchColumn());
    }

    /**
     * Regression: the two spellings are one tag under `ai_ci`, so the second link duplicated the first and the save
     * failed.
     */
    public function testAWordAndItsAccentedSpellingInOneSaveAreOneLink(): void
    {
        $bookmark = $this->savedBookmark();

        $bookmark->saveWithTags('cafe, café');

        $this->assertSame(['cafe'], $this->linkedTagNames($bookmark->id));
    }

    /**
     * The tag name is unique under the `ai_ci` collation, so the first spelling saved names the tag for both.
     */
    public function testAWordAndItsAccentedSpellingAreOneTag(): void
    {
        $this->savedBookmark()->saveWithTags('cafe');
        $bookmark = $this->savedBookmark();

        $bookmark->saveWithTags('café');

        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM `tag`')->fetchColumn());
        $this->assertSame(['cafe'], $this->linkedTagNames($bookmark->id));
    }

    public function testASaveReplacesPreviousLinks(): void
    {
        $bookmark = $this->savedBookmark();
        $bookmark->saveWithTags('php, sql');

        $bookmark->saveWithTags('web');

        $this->assertSame(['web'], $this->linkedTagNames($bookmark->id));
    }

    public function testTagsForBatchesAPageInOneMap(): void
    {
        $first = $this->savedBookmark();
        $first->saveWithTags('sql, php');
        $second = $this->savedBookmark();
        $second->saveWithTags('web');
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
        $bookmark->saveWithTags('php');
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
