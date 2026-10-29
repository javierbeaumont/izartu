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

final class FailureTest extends TestCase
{
    /** @var list<string> */
    private array $logged = [];

    public function testAnUnreachableDatabaseIsUnavailable(): void
    {
        $answer = Failure::answer(new DatabaseUnavailable('Connection refused'), $this->unasked(...), $this->log(...));

        $this->assertSame(['status' => 503, 'reason' => 'unavailable'], $answer);
        $this->assertStringContainsString('Connection refused', $this->logged[0]);
    }

    public function testAFailedQueryOnADatabaseBehindTheCodeIsAnUpgrade(): void
    {
        $missing = new PDOException("Table 'izartu.tag' doesn't exist");

        $answer = Failure::answer($missing, fn(): bool => true, $this->log(...));

        $this->assertSame(['status' => 503, 'reason' => 'upgrading'], $answer);
        $this->assertStringContainsString("Table 'izartu.tag' doesn't exist", $this->logged[0]);
        $this->assertStringContainsString('run php private/cli/migrate.php', $this->logged[1]);
    }

    public function testAFailedQueryOnAnUpToDateDatabaseIsAnError(): void
    {
        $answer = Failure::answer(new PDOException('Deadlock found'), fn(): bool => false, $this->log(...));

        $this->assertSame(['status' => 500, 'reason' => 'error'], $answer);
        $this->assertCount(1, $this->logged);
    }

    public function testAFailedQueryIsAnErrorWhenTheDatabaseCannotBeChecked(): void
    {
        $behind = fn(): bool => throw new RuntimeException('no migration history');

        $answer = Failure::answer(new PDOException('Unknown column'), $behind, $this->log(...));

        $this->assertSame(['status' => 500, 'reason' => 'error'], $answer);
        $this->assertStringContainsString('Unknown column', $this->logged[0]);
        $this->assertStringContainsString('no migration history', $this->logged[1]);
    }

    public function testAnyOtherFailureIsAnErrorAndLeavesTheDatabaseAlone(): void
    {
        $answer = Failure::answer(new TypeError('Wrong type'), $this->unasked(...), $this->log(...));

        $this->assertSame(['status' => 500, 'reason' => 'error'], $answer);
        $this->assertStringContainsString('Wrong type', $this->logged[0]);
    }

    private function log(string $line): void
    {
        $this->logged[] = $line;
    }

    private function unasked(): bool
    {
        $this->fail('Only a failed query asks whether the database is behind.');
    }
}
