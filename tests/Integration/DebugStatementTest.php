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

final class DebugStatementTest extends TestCase
{
    protected function setUp(): void
    {
        Debug::reset();
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

    public function testAStatementThatFailsIsCollectedToo(): void
    {
        $crud = new class extends Crud {
            public function probe(): void
            {
                self::db()->prepare('SELECT JSON_EXTRACT(:document, \'$\')')->execute([':document' => 'not JSON']);
            }
        };

        try {
            $crud->probe();
            $this->fail('Extracting from text that is not JSON must fail.');
        } catch (PDOException) {
        }

        $this->assertSame(["SELECT JSON_EXTRACT(:document, '$')"], array_column(Debug::queries(), 'sql'));
    }
}
