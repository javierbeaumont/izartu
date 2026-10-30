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

final class DelUserTest extends TestCase
{
    /**
     * The other side of the race test, as a second process with its own connection. It locks the gap where `zzz` would
     * go, so the test's save stops after locking `mine` and `aaa` and before linking them. Then it runs deluser.php on
     * user 10, waits until the script blocks or ends, and lets the save go on. It prints what the script printed and
     * exits with its code.
     */
    private const RIVAL = <<<'PHP'
        require $argv[1] . 'bootstrap.php';
        $pdo = new PDO(Database::dsn(DB_HOST, DB_PORT, DB_NAME), DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $blocked = static function (string $statement) use ($pdo): bool {
            foreach ($pdo->query('SHOW FULL PROCESSLIST') as $thread) {
                if (str_starts_with((string) $thread['Info'], $statement) && $thread['Time'] >= 1) {
                    return true;
                }
            }

            return false;
        };
        $until = static function (callable $done): bool {
            $deadline = microtime(true) + 10;
            do {
                usleep(100000);
            } while (!$done() && microtime(true) < $deadline);

            return $done();
        };
        $pdo->beginTransaction();
        $pdo->query("SELECT 1 FROM `tag` WHERE `name` = 'zzz' FOR SHARE")->fetchAll();
        echo "ready\n";
        if (!$until(fn(): bool => $blocked('INSERT INTO `tag`'))) {
            fwrite(STDERR, "The save never blocked.\n");
            exit(1);
        }
        $deluser = proc_open(
            [PHP_BINARY, '-d', 'session.save_path=' . $argv[2], $argv[1] . 'cli/deluser.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        fwrite($pipes[0], "other@izartu.test\nother@izartu.test\n");
        fclose($pipes[0]);
        $waiting = static function () use ($pdo): bool {
            $threads = 0;
            foreach ($pdo->query('SHOW FULL PROCESSLIST') as $thread) {
                $info = (string) $thread['Info'];
                $threads += (int) ($info !== '' && !str_starts_with($info, 'SHOW') && $thread['Time'] >= 1);
            }

            return $threads >= 2;
        };
        $until(fn(): bool => $waiting() || !proc_get_status($deluser)['running']);
        $pdo->rollBack();
        echo stream_get_contents($pipes[1]);
        fwrite(STDERR, (string) stream_get_contents($pipes[2]));
        exit(proc_close($deluser));
        PHP;

    private PDO $pdo;

    private string $sessions = '';

    private string $savePath = '';

    protected function setUp(): void
    {
        if (DB_NAME !== 'izartu_test') {
            self::fail('Refusing to run against "' . DB_NAME . '"; expected izartu_test.');
        }

        $this->pdo = new PDO(Database::dsn(DB_HOST, DB_PORT, DB_NAME), DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['bookmark_tag', 'bookmark', 'tag', 'user'] as $table) {
            $this->pdo->exec('TRUNCATE `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        // User 10 has bookmarks 1 and 2; `mine` is only theirs, and `shared` is on user 1's bookmark 3 too.
        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `user`
                (`id`, `username`, `email`, `hash`, `role`)
            VALUES
                (1, 'cli', 'cli@izartu.test', 'x', 'owner'), (10, 'other', 'other@izartu.test', 'x', 'user')
            SQL,
        );
        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `bookmark`
                (`id`, `title`, `hlink`, `text`, `user`)
            VALUES
                (1, 'One', 'https://1.test', '', 10), (2, 'Two', 'https://2.test', '', 10),
                (3, 'Three', 'https://3.test', '', 1)
            SQL,
        );
        $this->pdo->exec("INSERT INTO `tag` (`id`, `name`) VALUES (1, 'mine'), (2, 'shared')");
        $this->pdo->exec('INSERT INTO `bookmark_tag` (`bookmark`, `tag`) VALUES (1, 1), (1, 2), (2, 1), (3, 2)');

        session_write_close();
        $this->savePath = (string) session_save_path();
        $this->sessions = sys_get_temp_dir() . '/izartu-deluser-' . bin2hex(random_bytes(4));
        mkdir($this->sessions, 0700);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->sessions}/*") ?: []);
        rmdir($this->sessions);

        // The rest of the suite needs a session open (see tests/bootstrap.php).
        session_save_path($this->savePath);
        session_id('');
        Auth::start();
        $_SESSION = [];
    }

    public function testDeletesTheUserWithTheirBookmarksTheTagsOnlyTheyHadAndTheirSessions(): void
    {
        $laptop = $this->session('10-');
        $phone = $this->session('10-');
        $other = $this->session('1-');

        [$code, $output] = $this->deluser(['  Other@Izartu.Test ', 'OTHER@izartu.test']);

        $this->assertSame(0, $code);
        $this->assertSame(
            "Email: other (user) has 2 bookmarks, deleted with the user.\nType the email again to delete the user: "
            . "Deleted other@izartu.test with 2 bookmarks and 1 tag no other user had, and ended 2 open sessions.\n",
            $output,
        );
        $this->assertSame([1], $this->column('SELECT `id` FROM `user`'));
        $this->assertSame([3], $this->column('SELECT `id` FROM `bookmark`'));
        $this->assertSame(['shared'], $this->tags());
        $this->assertSame(['shared'], $this->tags(3));
        $this->assertFileDoesNotExist("{$this->sessions}/sess_{$laptop}");
        $this->assertFileDoesNotExist("{$this->sessions}/sess_{$phone}");
        $this->assertFileExists("{$this->sessions}/sess_{$other}");
    }

    public function testTheEmailMustBeTypedAgain(): void
    {
        $session = $this->session('10-');

        [$code, , $error] = $this->deluser(['other@izartu.test', 'cli@izartu.test']);

        $this->assertSame(1, $code);
        $this->assertSame("The emails differ: nothing was deleted.\n", $error);
        $this->assertSame([1, 10], $this->column('SELECT `id` FROM `user` ORDER BY `id`'));
        $this->assertFileExists("{$this->sessions}/sess_{$session}");
    }

    public function testTheOnlyOwnerCannotBeDeleted(): void
    {
        $session = $this->session('1-');

        [$code, $output, $error] = $this->deluser(['cli@izartu.test', 'cli@izartu.test']);

        $this->assertSame(1, $code);
        $this->assertSame('Email: ', $output);
        $this->assertSame("cli@izartu.test is the only owner, and cannot be deleted.\n", $error);
        $this->assertSame([1, 10], $this->column('SELECT `id` FROM `user` ORDER BY `id`'));
        $this->assertFileExists("{$this->sessions}/sess_{$session}");
    }

    public function testAnOwnerIsDeletedWhileAnotherRemains(): void
    {
        $this->pdo->exec("UPDATE `user` SET `role` = 'owner' WHERE `id` = 10");

        [$code, , $error] = $this->deluser(['cli@izartu.test', 'cli@izartu.test']);

        $this->assertSame(0, $code, $error);
        $this->assertSame([10], $this->column('SELECT `id` FROM `user`'));
    }

    public function testAnUnknownEmailDeletesNothing(): void
    {
        [$code, , $error] = $this->deluser(['nobody@izartu.test']);

        $this->assertSame(1, $code);
        $this->assertSame("No user has the email nobody@izartu.test.\n", $error);
        $this->assertSame([1, 10], $this->column('SELECT `id` FROM `user` ORDER BY `id`'));
    }

    public function testSessionsThatCannotBeEndedLeaveEverything(): void
    {
        [$code, , $error] = $this->deluser(['other@izartu.test', 'other@izartu.test'], "{$this->sessions}/missing");

        $this->assertSame(1, $code);
        $this->assertStringStartsWith(
            "The user's open sessions could not be ended, so nothing was deleted: Cannot read the session directory",
            $error,
        );
        $this->assertSame([1, 10], $this->column('SELECT `id` FROM `user` ORDER BY `id`'));
        $this->assertSame([1, 2, 3], $this->column('SELECT `id` FROM `bookmark` ORDER BY `id`'));
        $this->assertSame(['mine', 'shared'], $this->tags());
    }

    /**
     * Two concurrent deletions of the only two owners: the second counts the owners again once the first has committed,
     * and refuses.
     */
    public function testTwoConcurrentDeletionsCannotLeaveNoOwner(): void
    {
        $this->pdo->exec("UPDATE `user` SET `role` = 'owner' WHERE `id` = 10");
        // Holding `mine` stops the first deletion after it has counted the owners.
        $this->pdo->beginTransaction();
        $this->pdo->query("SELECT 1 FROM `tag` WHERE `name` = 'mine' FOR SHARE")->fetchAll();

        $first = $this->start(['other@izartu.test', 'other@izartu.test']);
        $this->waitForBlocked(1);
        $second = $this->start(['cli@izartu.test', 'cli@izartu.test']);
        $this->waitForBlocked(2);
        $this->pdo->rollBack();

        [$code, , $error] = $this->finish($first);
        $this->assertSame(0, $code, $error);
        [$code, , $error] = $this->finish($second);
        $this->assertSame(1, $code);
        $this->assertSame("cli@izartu.test is the only owner, and cannot be deleted.\n", $error);
        $this->assertSame([1], $this->column('SELECT `id` FROM `user`'));
    }

    /**
     * A save that links `mine` and `aaa`, which only user 10 had, to user 1's bookmark while user 10 is deleted. The
     * save has locked both tags before the deletion tries to, so the deletion waits for the links and keeps the tags,
     * ids and all. `aaa` comes last in the save and first in the index: the save locking all its tags in one statement,
     * in the index's order, is what keeps the two from deadlocking.
     */
    public function testTagsLinkedWhileTheirOnlyHolderIsDeletedAreKept(): void
    {
        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `bookmark`
                (`id`, `title`, `hlink`, `text`, `user`)
            VALUES
                (4, 'Four', 'https://4.test', '', 1)
            SQL,
        );
        $this->pdo->exec("INSERT INTO `tag` (`id`, `name`) VALUES (3, 'aaa')");
        $this->pdo->exec('INSERT INTO `bookmark_tag` (`bookmark`, `tag`) VALUES (2, 3)');
        $bookmark = Bookmark::find(4);
        $rival = proc_open(
            [PHP_BINARY, '-r', self::RIVAL, PRIVATE_DIR, $this->sessions],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertSame("ready\n", fgets($pipes[1]));
        Debug::reset();

        $bookmark?->saveWithTags('mine, zzz, aaa');

        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        $this->assertSame(0, proc_close($rival), $error);
        $this->assertStringEndsWith(
            "Deleted other@izartu.test with 2 bookmarks and 0 tags no other user had, and ended 0 open sessions.\n",
            $output,
        );
        $this->assertSame(['aaa', 'mine', 'zzz'], $this->tags(4));
        $ids = $this->column("SELECT `id` FROM `tag` WHERE `name` IN ('aaa', 'mine') ORDER BY `name`");
        $this->assertSame([3, 1], $ids, 'the tags kept their ids');
        $inserts = array_filter(
            Debug::queries(),
            fn(array $query): bool => str_starts_with($query['sql'], 'INSERT INTO `tag`'),
        );
        $this->assertCount(3, $inserts, 'the save ran once');
    }

    /**
     * @return list<mixed> The first column of every row the query returns.
     */
    private function column(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @return list<mixed> Every tag's name, or the names linked to one bookmark; alphabetical.
     */
    private function tags(?int $bookmark = null): array
    {
        $query = $this->pdo->prepare(
            <<<'SQL'
            SELECT
                `name`
            FROM `tag`
            WHERE
                :bookmark IS NULL
                OR `id` IN (SELECT `tag` FROM `bookmark_tag` WHERE `bookmark` = :bookmark)
            ORDER BY
                `name`
            SQL,
        );
        $query->execute([':bookmark' => $bookmark]);

        return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Open a session in the test's session directory, as a login does, and return its id.
     */
    private function session(string $prefix): string
    {
        session_save_path($this->sessions);
        session_id((string) session_create_id($prefix));
        session_start();
        $_SESSION['csrf'] = 'token';
        $id = (string) session_id();
        session_write_close();

        return $id;
    }

    /**
     * Run the script as an operator would, answering its prompts.
     *
     * @param list<string> $answers The email, and the email again.
     * @param string $sessions The `session.save_path` the script runs with; the test's own by default.
     * @return array{int, string, string} The exit code, and what the script wrote to stdout and to stderr.
     */
    private function deluser(array $answers, string $sessions = ''): array
    {
        return $this->finish($this->start($answers, $sessions));
    }

    /**
     * Start the script and give it its answers, without waiting for it.
     *
     * @param list<string> $answers The email, and the email again.
     * @param string $sessions The `session.save_path` the script runs with; the test's own by default.
     * @return array{resource, array<int, resource>} The process and its pipes.
     */
    private function start(array $answers, string $sessions = ''): array
    {
        $process = proc_open(
            [PHP_BINARY, '-d', 'session.save_path=' . ($sessions ?: $this->sessions), PRIVATE_DIR . 'cli/deluser.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        fwrite($pipes[0], implode("\n", $answers) . "\n");
        fclose($pipes[0]);

        return [$process, $pipes];
    }

    /**
     * Wait for a started script to end.
     *
     * @param array{resource, array<int, resource>} $run The process and its pipes.
     * @return array{int, string, string} The exit code, and what the script wrote to stdout and to stderr.
     */
    private function finish(array $run): array
    {
        [$process, $pipes] = $run;
        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);

        return [proc_close($process), $output, $error];
    }

    /**
     * Wait until a number of statements on other connections have been running for a second, blocked on locks.
     */
    private function waitForBlocked(int $statements): void
    {
        $deadline = microtime(true) + 10;
        do {
            usleep(100000);
            $blocked = 0;
            foreach ($this->pdo->query('SHOW FULL PROCESSLIST') as $thread) {
                $info = (string) $thread['Info'];
                $blocked += (int) ($info !== '' && !str_starts_with($info, 'SHOW') && $thread['Time'] >= 1);
            }
        } while ($blocked < $statements && microtime(true) < $deadline);

        $this->assertGreaterThanOrEqual($statements, $blocked, 'statements blocked');
    }
}
