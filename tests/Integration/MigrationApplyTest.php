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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MigrationApplyTest extends TestCase
{
    private const DB = 'izartu_migration_test';

    private const BASELINE = <<<'SQL'
        CREATE TABLE `migration` (
          `version` smallint unsigned NOT NULL,
          `name` varchar(255) NOT NULL,
          `applied` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`version`)
        );
        CREATE TABLE `a` (`id` int NOT NULL, PRIMARY KEY (`id`));
        SQL;

    private PDO $pdo;
    private string $dir;

    protected function setUp(): void
    {
        $this->pdo = new PDO(Database::dsn(DB_HOST, DB_PORT, self::DB), DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $tables = $this->tables();
        if ($tables !== []) {
            $this->pdo->exec('DROP TABLE `' . implode('`, `', $tables) . '`');
        }

        $this->dir = sys_get_temp_dir() . '/izartu-migration-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testAnEmptyDatabaseGetsTheWholeSchema(): void
    {
        $migration = $this->shipped();

        $this->assertSame('0001-baseline', $migration->applyNext());
        $this->assertNull($migration->applyNext());
        $this->assertSame(['bookmark', 'bookmark_tag', 'migration', 'tag', 'user'], $this->tables());
        $this->assertSame([[1, 'baseline']], $this->recorded());
    }

    public function testAnUpToDateDatabaseHasNothingToApply(): void
    {
        $this->shipped()->applyNext();

        $migration = $this->shipped();

        $this->assertNull($migration->applyNext());
        $this->assertSame(1, $migration->version());
        $this->assertSame([[1, 'baseline']], $this->recorded());
    }

    public function testMigrationsApplyInOrderFromWhereTheDatabaseIs(): void
    {
        $this->write('0001-baseline.sql', self::BASELINE);
        $this->write('0002-add-b.sql', 'ALTER TABLE `a` ADD `b` int;');
        (new Migration($this->pdo, $this->dir))->applyNext();

        $this->write('0010-fill.sql', "INSERT INTO `a` VALUES (1, 2);\nINSERT INTO `a` VALUES (3, 4);");
        $migration = new Migration($this->pdo, $this->dir);

        $this->assertSame('0002-add-b', $migration->applyNext());
        $this->assertSame('0010-fill', $migration->applyNext());
        $this->assertNull($migration->applyNext());
        $this->assertSame([[1, 2], [3, 4]], $this->rows('SELECT `id`, `b` FROM `a` ORDER BY `id`'));
        $this->assertSame([[1, 'baseline'], [2, 'add-b'], [10, 'fill']], $this->recorded());
    }

    public function testAMigrationIsPendingUntilItIsApplied(): void
    {
        $this->write('0001-baseline.sql', self::BASELINE);
        $this->write('0002-add-b.sql', 'ALTER TABLE `a` ADD `b` int;');
        $migration = new Migration($this->pdo, $this->dir);
        $migration->applyNext();

        $this->assertTrue($migration->pending());

        $migration->applyNext();

        $this->assertFalse($migration->pending());
    }

    public function testTablesWithoutAMigrationHistoryAreRefused(): void
    {
        $this->pdo->exec('CREATE TABLE `stray` (`id` int)');

        try {
            $this->shipped()->applyNext();
            $this->fail('A database built by something else must be left alone.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('no migration history', $e->getMessage());
        }

        $this->assertSame(['stray'], $this->tables());
    }

    public function testADatabaseNewerThanTheCodeIsRefused(): void
    {
        $this->shipped()->applyNext();
        $this->pdo->exec("INSERT INTO `migration` (`version`, `name`) VALUES (9999, 'future')");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The database is at version 9999, newer than the latest migration (1)');

        $this->shipped()->applyNext();
    }

    public function testAFailedMigrationIsNotRecorded(): void
    {
        $this->write('0001-baseline.sql', self::BASELINE);
        $this->write('0002-broken.sql', 'ALTER TABLE `missing` ADD `b` int;');
        $migration = new Migration($this->pdo, $this->dir);
        $migration->applyNext();

        try {
            $migration->applyNext();
            $this->fail('A failed statement must stop the migration.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                '0002-broken failed at statement 1 (ALTER TABLE `missing`',
                $e->getMessage(),
            );
        }

        $this->assertSame(1, $migration->version());
    }

    public function testAFailedDmlMigrationIsRolledBack(): void
    {
        $this->write('0001-baseline.sql', self::BASELINE);
        $this->write('0002-fill.sql', "INSERT INTO `a` VALUES (1);\nINSERT INTO `a` VALUES (1);");
        $migration = new Migration($this->pdo, $this->dir);
        $migration->applyNext();

        try {
            $migration->applyNext();
            $this->fail('A duplicate key must stop the migration.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('0002-fill failed at statement 2', $e->getMessage());
        }

        $this->assertSame([], $this->rows('SELECT `id` FROM `a`'), 'the first INSERT is rolled back');
        $this->assertSame(1, $migration->version());
    }

    /**
     * @return list<array{0: string}>
     */
    public static function unsafe(): array
    {
        return [
            ["CREATE TABLE `b` (`id` int);\nINSERT INTO `a` VALUES (1);"],
            ["CREATE TABLE `b` (`id` int);\nCREATE TABLE `c` (`id` int);"],
        ];
    }

    #[DataProvider('unsafe')]
    public function testALaterMigrationMixingDdlWithOtherStatementsIsRefused(string $sql): void
    {
        $this->write('0001-baseline.sql', self::BASELINE);
        $this->write('0002-unsafe.sql', $sql);
        $migration = new Migration($this->pdo, $this->dir);
        $migration->applyNext();

        try {
            $migration->applyNext();
            $this->fail('DDL cannot share a migration with anything else.');
        } catch (RuntimeException $e) {
            $this->assertSame('Migration 0002-unsafe must be one DDL statement or DML only.', $e->getMessage());
        }

        $this->assertSame(['a', 'migration'], $this->tables());
    }

    public function testAnEmptyMigrationIsRefused(): void
    {
        $this->write('0001-baseline.sql', self::BASELINE);
        $this->write('0002-nothing.sql', "-- Nothing yet.\n");
        $migration = new Migration($this->pdo, $this->dir);
        $migration->applyNext();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration 0002-nothing is empty or unreadable.');

        $migration->applyNext();
    }

    /**
     * A baseline that failed half-way leaves the `migration` table empty, so the next run applies it again, and
     * `IF NOT EXISTS` makes it create only what is missing.
     */
    public function testABaselineThatStoppedHalfWayCompletesOnTheNextRun(): void
    {
        $sql = file_get_contents(PRIVATE_DIR . 'migration/0001-baseline.sql');
        foreach (array_slice(Migration::statements((string) $sql), 0, 2) as $statement) {
            $this->pdo->exec($statement);
        }

        $this->assertSame('0001-baseline', $this->shipped()->applyNext());
        $this->assertSame(['bookmark', 'bookmark_tag', 'migration', 'tag', 'user'], $this->tables());
        $this->assertSame([[1, 'baseline']], $this->recorded());
    }

    public function testAnUnreadableDirectoryIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read the migrations in ' . $this->dir . '/missing.');

        new Migration($this->pdo, $this->dir . '/missing');
    }

    private function shipped(): Migration
    {
        return new Migration($this->pdo, PRIVATE_DIR . 'migration');
    }

    private function write(string $name, string $sql): void
    {
        file_put_contents($this->dir . '/' . $name, $sql);
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        sort($tables);

        return $tables;
    }

    /**
     * @return list<array{0: int, 1: string}>
     */
    private function recorded(): array
    {
        return $this->rows('SELECT `version`, `name` FROM `migration` ORDER BY `version`');
    }

    /**
     * @return list<list<mixed>>
     */
    private function rows(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_NUM);
    }
}
