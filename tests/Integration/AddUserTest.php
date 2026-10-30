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

final class AddUserTest extends TestCase
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

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->pdo->exec('TRUNCATE `user`');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testCreatesTheUserWithAHashedPassword(): void
    {
        [$code] = $this->addUser(['cli@izartu.test', 'cli', 'secret123', 'secret123', 'admin']);

        $row = $this->rows()[0];

        $this->assertSame(0, $code);
        $this->assertSame(['cli', 'cli@izartu.test', 'admin'], [$row['username'], $row['email'], $row['role']]);
        $this->assertNotSame('secret123', $row['hash'], 'never stored in the clear');
        $this->assertTrue(password_verify('secret123', $row['hash']));
    }

    public function testTheEmailIsLowerCasedAndAnEmptyRoleIsOwner(): void
    {
        $this->addUser(['  CLI@Izartu.Test ', 'cli', 'secret123', 'secret123', '']);

        $row = $this->rows()[0];

        $this->assertSame(['cli@izartu.test', 'owner'], [$row['email'], $row['role']]);
    }

    public function testInvalidInputIsRefusedBeforeConnecting(): void
    {
        // An unresolvable host: if the script tried to connect, the error would be a connection failure instead.
        [$code, $error] = $this->addUser(
            ['not an email', 'me', 'short', 'short', 'root'],
            ['DB_HOST' => 'no-database.invalid'],
        );

        $this->assertSame(1, $code);
        $this->assertStringContainsString('A valid email address', $error);
        $this->assertStringContainsString('That username is reserved.', $error);
        $this->assertStringContainsString('Password must be at least 8 characters.', $error);
        $this->assertStringContainsString('Role must be one of', $error);
        $this->assertStringNotContainsString('Could not create the user', $error);
    }

    public function testPasswordsThatDifferAreRefusedBeforeConnecting(): void
    {
        [$code, $error] = $this->addUser(
            ['cli@izartu.test', 'cli', 'secret123', 'secret124', 'owner'],
            ['DB_HOST' => 'no-database.invalid'],
        );

        $this->assertSame(1, $code);
        $this->assertSame("The two passwords differ.\n", $error);
    }

    /**
     * Both columns are unique under the `ai_ci` collation, so a change of case does not make a second account.
     */
    public function testAUsernameOrEmailAlreadyTakenIsRefused(): void
    {
        $this->addUser(['cli@izartu.test', 'cli', 'secret123', 'secret123', 'user']);

        [$sameName, $nameError] = $this->addUser(['other@izartu.test', 'CLI', 'secret123', 'secret123', 'user']);
        [$sameEmail, $emailError] = $this->addUser(['cli@izartu.test', 'other', 'secret123', 'secret123', 'user']);

        $this->assertSame([1, 1], [$sameName, $sameEmail]);
        $this->assertStringContainsString('Could not create the user', $nameError);
        $this->assertStringContainsString('Could not create the user', $emailError);
        $this->assertCount(1, $this->rows());
    }

    /**
     * Regression: adduser.php built its own DSN without `DB_PORT` (or socket hosts), so on any other port it reached
     * whatever listened on 3306. Nothing listens on port 1: the script must fail there, not create the user elsewhere.
     */
    public function testConnectsThroughTheConfiguredPort(): void
    {
        [$code, $error] = $this->addUser(
            ['cli@izartu.test', 'cli', 'secret123', 'secret123', 'owner'],
            ['DB_PORT' => '1'],
        );

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Could not create the user', $error);
        $this->assertSame([], $this->rows());
    }

    private function rows(): array
    {
        return $this->pdo->query('SELECT `username`, `email`, `hash`, `role` FROM `user` ORDER BY `id`')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Run the script as an operator would, answering its five prompts.
     *
     * @param list<string> $answers Email, username, password, the password again and role.
     * @param array<string, string> $env Variables to set on top of the current environment.
     * @return array{int, string} The exit code and what the script wrote to stderr.
     */
    private function addUser(array $answers, array $env = []): array
    {
        $process = proc_open(
            [PHP_BINARY, PRIVATE_DIR . 'cli/adduser.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            array_merge(getenv(), $env),
        );
        fwrite($pipes[0], implode("\n", $answers) . "\n");
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);

        return [proc_close($process), $error];
    }
}
