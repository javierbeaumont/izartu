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

final class PasswdTest extends TestCase
{
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
        $this->pdo->exec('TRUNCATE `user`');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $insert = $this->pdo->prepare(
            <<<'SQL'
            INSERT INTO `user`
                (`id`, `username`, `email`, `hash`, `role`)
            VALUES
                (:id, :username, :email, :hash, :role)
            SQL,
        );
        foreach ([[1, 'cli', 'owner'], [10, 'other', 'user']] as [$id, $username, $role]) {
            $insert->execute([
                ':id' => $id,
                ':username' => $username,
                ':email' => "{$username}@izartu.test",
                ':hash' => password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 4]),
                ':role' => $role,
            ]);
        }

        session_write_close();
        $this->savePath = (string) session_save_path();
        $this->sessions = sys_get_temp_dir() . '/izartu-passwd-' . bin2hex(random_bytes(4));
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

    public function testChangesThePasswordAndEndsTheUsersSessions(): void
    {
        $laptop = $this->session('1-');
        $phone = $this->session('1-');
        $other = $this->session('10-');

        [$code, $output] = $this->passwd(['  CLI@Izartu.Test ', 'newsecret1', 'newsecret1']);

        $this->assertSame(0, $code);
        $this->assertSame(
            'Email: New password: Repeat it: Changed the password of cli@izartu.test and ended 2 open sessions.' . "\n",
            $output,
        );
        $this->assertTrue(password_verify('newsecret1', $this->hash(1)));
        $this->assertTrue(password_verify('secret123', $this->hash(10)));
        $this->assertFileDoesNotExist("{$this->sessions}/sess_{$laptop}");
        $this->assertFileDoesNotExist("{$this->sessions}/sess_{$phone}");
        $this->assertFileExists("{$this->sessions}/sess_{$other}");
    }

    public function testPasswordsThatDifferAreRefusedBeforeConnecting(): void
    {
        [$code, , $error] = $this->passwd(['cli@izartu.test', 'newsecret1', 'newsecret2'], [
            'DB_HOST' => 'no-database.invalid',
        ]);

        $this->assertSame(1, $code);
        $this->assertSame("The two passwords differ.\n", $error);
        $this->assertTrue(password_verify('secret123', $this->hash(1)));
    }

    public function testAShortPasswordIsRefusedBeforeConnecting(): void
    {
        [$code, , $error] = $this->passwd(['cli@izartu.test', 'short', 'short'], ['DB_HOST' => 'no-database.invalid']);

        $this->assertSame(1, $code);
        $this->assertSame("Password must be at least 8 characters.\n", $error);
    }

    public function testAnUnknownEmailChangesNothing(): void
    {
        [$code, , $error] = $this->passwd(['nobody@izartu.test', 'newsecret1', 'newsecret1']);

        $this->assertSame(1, $code);
        $this->assertSame("No user has the email nobody@izartu.test.\n", $error);
        $this->assertTrue(password_verify('secret123', $this->hash(1)));
    }

    public function testSessionsThatCannotBeEndedAreReported(): void
    {
        $missing = "{$this->sessions}/missing";

        [$code, , $error] = $this->passwd(['cli@izartu.test', 'newsecret1', 'newsecret1'], [], $missing);

        $this->assertSame(1, $code);
        $this->assertStringStartsWith(
            "The password changed, but the user's open sessions could not be ended: Cannot read the session directory",
            $error,
        );
        $this->assertTrue(password_verify('newsecret1', $this->hash(1)));
    }

    private function hash(int $id): string
    {
        $query = $this->pdo->prepare('SELECT `hash` FROM `user` WHERE `id` = :id');
        $query->execute([':id' => $id]);

        return (string) $query->fetchColumn();
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
     * Run the script as an operator would, answering its three prompts.
     *
     * @param list<string> $answers Email, password and the password again.
     * @param array<string, string> $env Variables to set on top of the current environment.
     * @param string $sessions The `session.save_path` the script runs with; the test's own by default.
     * @return array{int, string, string} The exit code, and what the script wrote to stdout and to stderr.
     */
    private function passwd(array $answers, array $env = [], string $sessions = ''): array
    {
        $process = proc_open(
            [PHP_BINARY, '-d', 'session.save_path=' . ($sessions ?: $this->sessions), PRIVATE_DIR . 'cli/passwd.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            array_merge(getenv(), $env),
        );
        fwrite($pipes[0], implode("\n", $answers) . "\n");
        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);

        return [proc_close($process), $output, $error];
    }
}
