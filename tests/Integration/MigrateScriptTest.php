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

final class MigrateScriptTest extends TestCase
{
    private const DB = 'izartu_migration_test';

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO(Database::dsn(DB_HOST, DB_PORT, self::DB), DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if ($tables !== []) {
            $this->pdo->exec('DROP TABLE `' . implode('`, `', $tables) . '`');
        }
    }

    public function testMigratesAnEmptyDatabase(): void
    {
        $this->assertSame(
            [0, "Applied 0001-baseline.\nThe database is at version 1.\n", ''],
            $this->migrate(),
        );
    }

    public function testASecondRunOnlyReportsTheVersion(): void
    {
        $this->migrate();

        $this->assertSame([0, "The database is at version 1.\n", ''], $this->migrate());
    }

    public function testARefusalExitsWithTheReason(): void
    {
        $this->pdo->exec('CREATE TABLE `stray` (`id` int)');

        [$code, $output, $error] = $this->migrate();

        $this->assertSame([1, ''], [$code, $output]);
        $this->assertStringStartsWith('Could not migrate: The database has tables but no migration history', $error);
    }

    public function testAnUnreachableDatabaseExitsWithAnError(): void
    {
        [$code, $output, $error] = $this->migrate(['DB_PORT' => '1']);

        $this->assertSame([1, ''], [$code, $output]);
        $this->assertStringStartsWith('Could not migrate: ', $error);
    }

    public function testASettingLeftWithoutEffectIsWarned(): void
    {
        [$code, , $error] = $this->migrate(['DB_SSL_ENABLED' => '0', 'DB_SSL_CA_FILE' => '/ca.pem']);

        $this->assertSame(0, $code);
        // Over a Unix socket the warning names the socket instead of DB_SSL_ENABLED.
        $this->assertStringStartsWith('Warning: ', $error);
        $this->assertStringEndsWith(": DB_SSL_VERIFY_CERT and DB_SSL_CA_FILE are ignored.\n", $error);
    }

    /**
     * Run the script in a process, against the migration test database.
     *
     * @param array<string, string> $env Extra environment variables, set over the current ones.
     * @return array{int, string, string} The exit code, what the script wrote to stdout and what it wrote to stderr.
     */
    private function migrate(array $env = []): array
    {
        $process = proc_open(
            [PHP_BINARY, PRIVATE_DIR . 'cli/migrate.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            array_merge(getenv(), ['DB_NAME' => self::DB], $env),
        );
        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);

        return [proc_close($process), $output, $error];
    }
}
