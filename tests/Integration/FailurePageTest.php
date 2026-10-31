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

final class FailurePageTest extends TestCase
{
    private const DB = 'izartu_migration_test';

    /** @var resource|null The built-in server of the current test. */
    private $server = null;

    private string $logFile = '';

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }

        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testAnUnreachableDatabaseAnswers503AndKeepsTheCauseInTheLog(): void
    {
        [$headers, $body, $logged] = $this->request(['DB_PORT' => '1', 'DEBUG' => '']);

        $this->assertStringContainsString(' 503 ', $headers[0]);
        $this->assertContains('Retry-After: 60', $headers);
        $this->assertStringContainsString('<h2>Not available</h2>', $body);
        $this->assertStringNotContainsString('DatabaseUnavailable', $body);
        $this->assertStringContainsString('DatabaseUnavailable', $logged);
    }

    public function testADatabaseBehindTheCodeAnswers503AndTellsTheOperatorInTheLog(): void
    {
        $pdo = new PDO(Database::dsn(DB_HOST, DB_PORT, self::DB), DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if ($tables !== []) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $pdo->exec('DROP TABLE `' . implode('`, `', $tables) . '`');
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        // Only the first statement, the migration table: a database with no migration applied.
        $baseline = Migration::statements((string) file_get_contents(PRIVATE_DIR . 'migration/0001-baseline.sql'));
        $pdo->exec($baseline[0]);

        [$headers, $body, $logged] = $this->request(['DB_NAME' => self::DB, 'DEBUG' => '']);

        $this->assertStringContainsString(' 503 ', $headers[0]);
        $this->assertStringContainsString('<h2>Upgrading</h2>', $body);
        $this->assertStringContainsString('run php private/cli/migrate.php', $logged);
    }

    /**
     * Regression: without a php.ini, a bootstrap that threw answered 200, its error and file paths on the page, and
     * logged nothing.
     */
    public function testAFailingBootstrapAnswers500AndKeepsTheCauseInTheLog(): void
    {
        [$headers, $body, $logged] = $this->request(['TIMEZONE' => 'Europe/Nowhere', 'DEBUG' => '']);

        $this->assertStringContainsString(' 500 ', $headers[0]);
        $this->assertSame('', $body);
        $this->assertStringContainsString('Unknown TIMEZONE "Europe/Nowhere"', $logged);
    }

    public function testInDebugModeThePageShowsTheCause(): void
    {
        [, $body] = $this->request(['DB_PORT' => '1', 'DEBUG' => '1']);

        $this->assertStringContainsString('DatabaseUnavailable', $body);
    }

    /**
     * Request the home page from PHP's built-in server, with the front controller as its router.
     *
     * @param array<string, string> $env Variables to set on top of the current environment.
     * @return array{list<string>, string, string} The response headers, its body and what the server logged.
     */
    private function request(array $env): array
    {
        do {
            $port = random_int(20000, 40000);
        } while (@fsockopen('127.0.0.1', $port));

        $this->logFile = (string) tempnam(sys_get_temp_dir(), 'izartu-log-');
        $this->server = proc_open(
            [PHP_BINARY, '-d', 'error_log=' . $this->logFile, '-S', '127.0.0.1:' . $port, PUBLIC_DIR . 'index.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            PUBLIC_DIR,
            array_merge(getenv(), $env),
        );

        $deadline = microtime(true) + 5;
        while (!@fsockopen('127.0.0.1', $port) && microtime(true) < $deadline) {
            usleep(50000);
        }

        $response = fopen(
            'http://127.0.0.1:' . $port . '/',
            'r',
            false,
            stream_context_create(['http' => ['ignore_errors' => true]]),
        );
        $this->assertIsResource($response, 'the server answers');
        $headers = stream_get_meta_data($response)['wrapper_data'];
        $body = (string) stream_get_contents($response);
        fclose($response);

        return [$headers, $body, (string) file_get_contents($this->logFile)];
    }
}
