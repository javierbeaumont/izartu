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

final class MigrationTest extends TestCase
{
    public function testMigrationsComeInVersionOrder(): void
    {
        $this->assertSame(
            [1 => 'baseline', 2 => 'add-index', 10 => 'later'],
            Migration::parse(['0002-add-index.sql', '.', '..', '0010-later.sql', '0001-baseline.sql']),
        );
    }

    public function testAnythingButSqlFilesIsIgnored(): void
    {
        $this->assertSame([], Migration::parse(['.', '..', 'README.md', '.gitkeep', '0001-baseline.sql.orig']));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function misnamed(): array
    {
        return [
            ['baseline.sql'],
            ['1-baseline.sql'],
            ['00001-baseline.sql'],
            ['0001_baseline.sql'],
            ['0001-Baseline.sql'],
            ['0001-.sql'],
            ['0001-base--line.sql'],
            ['0000-baseline.sql'],
        ];
    }

    #[DataProvider('misnamed')]
    public function testAMisnamedMigrationIsRefused(string $name): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($name);

        Migration::parse(['0001-baseline.sql', $name]);
    }

    public function testTwoMigrationsWithOneVersionAreRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Two migrations share version 0002.');

        Migration::parse(['0001-baseline.sql', '0002-one.sql', '0002-other.sql']);
    }

    public function testStatementsSplitAtASemicolonEndingALine(): void
    {
        $this->assertSame(
            ["CREATE TABLE `a` (\n  `id` int\n)", 'INSERT INTO `a` VALUES (1)'],
            Migration::statements("CREATE TABLE `a` (\n  `id` int\n);\n\nINSERT INTO `a` VALUES (1);\n"),
        );
    }

    public function testASemicolonInsideALineDoesNotSplit(): void
    {
        $this->assertSame(
            ["INSERT INTO `a` VALUES ('x;y')"],
            Migration::statements("INSERT INTO `a` VALUES ('x;y');\n"),
        );
    }

    public function testCommentLinesAreDropped(): void
    {
        $this->assertSame(
            ['DROP TABLE `a`'],
            Migration::statements("-- Not needed any more.\nDROP TABLE `a`;\n  -- indented\n"),
        );
    }

    public function testSpacesAfterTheSemicolonStillSplit(): void
    {
        $this->assertSame(
            ['DROP TABLE `a`', 'DROP TABLE `b`'],
            Migration::statements("DROP TABLE `a`; \t\nDROP TABLE `b`;"),
        );
    }

    public function testWindowsLineEndingsSplitToo(): void
    {
        $this->assertSame(
            ['DROP TABLE `a`', 'DROP TABLE `b`'],
            Migration::statements("DROP TABLE `a`;\r\nDROP TABLE `b`;\r\n"),
        );
    }

    public function testTheLastStatementMayOmitItsSemicolon(): void
    {
        $this->assertSame(['DROP TABLE `a`'], Migration::statements('DROP TABLE `a`'));
    }

    public function testAFileOfCommentsHasNoStatements(): void
    {
        $this->assertSame([], Migration::statements("-- Nothing yet.\n\n"));
    }

    public function testDmlOnlyRunsInATransaction(): void
    {
        $this->assertTrue(Migration::transactional([
            'INSERT INTO `a` VALUES (1)',
            'update `a` SET `id` = 2',
            'DELETE FROM `a`',
            'REPLACE INTO `a` VALUES (3)',
        ]));
    }

    public function testAnyDdlRunsOutsideATransaction(): void
    {
        $this->assertFalse(Migration::transactional(['INSERT INTO `a` VALUES (1)', 'ALTER TABLE `a` ADD `b` int']));
        $this->assertFalse(Migration::transactional(['ALTER TABLE `a` ADD `b` int']));
    }

    public function testNoStatementsIsNotATransaction(): void
    {
        $this->assertFalse(Migration::transactional([]));
    }
}
