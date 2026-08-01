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

final class DebugTest extends TestCase
{
    protected function setUp(): void
    {
        Debug::reset();
    }

    public function testQueriesNormaliseWhitespaceAndCountRepeatedSql(): void
    {
        Debug::query('SELECT 1', 1.0);
        Debug::query("SELECT\n    1", 2.0);
        Debug::query('SELECT 2', 3.0);

        $queries = Debug::queries();

        $this->assertSame('SELECT 1', $queries[1]['sql'], 'whitespace is normalised');
        $this->assertSame(2, $queries[0]['runs']);
        $this->assertSame(2, $queries[1]['runs']);
        $this->assertSame(1, $queries[2]['runs']);
    }

    public function testMetricsAggregateDbTimeAndQueryCount(): void
    {
        Debug::query('SELECT 1', 1.5);
        Debug::query('SELECT 2', 2.0);

        $metrics = Debug::metrics();

        $this->assertSame(3.5, $metrics['db']);
        $this->assertSame(2, $metrics['queries']);
        $this->assertGreaterThan(0, $metrics['app']);
        $this->assertGreaterThan(0, $metrics['mem']);
    }

    public function testServerTimingReportsDbAppAndMemory(): void
    {
        Debug::query('SELECT 1', 1.5);
        Debug::query('SELECT 2', 2.0);

        $this->assertMatchesRegularExpression(
            '/^db;dur=3\.5;desc="2 queries", app;dur=\d+\.\d, mem;desc="peak \d+\.\d{2} MB"$/',
            Debug::serverTiming(),
        );
    }

    public function testExecutedStatementsAreTimedAndCollected(): void
    {
        if (DB_NAME !== 'izartu_test') {
            self::fail('Refusing to run against "' . DB_NAME . '"; expected izartu_test.');
        }

        $crud = new class extends Crud {
            public function probe(): array
            {
                return $this->read('SELECT 42 AS `answer`');
            }
        };

        $rows = $crud->probe();

        $this->assertSame(42, (int) $rows[0]['answer']);
        $this->assertCount(1, Debug::queries());
        $this->assertSame('SELECT 42 AS `answer`', Debug::queries()[0]['sql']);
        $this->assertGreaterThan(0, Debug::queries()[0]['ms']);
    }
}
