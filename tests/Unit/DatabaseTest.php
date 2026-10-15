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

final class DatabaseTest extends TestCase
{
    public function testTheDefaultPortIsLeftOut(): void
    {
        $this->assertSame('mysql:host=db;dbname=izartu;charset=utf8mb4', Database::dsn('db', 3306, 'izartu'));
    }

    public function testAnyOtherPortIsSpelledOut(): void
    {
        $this->assertSame('mysql:host=db;port=3307;dbname=izartu;charset=utf8mb4', Database::dsn('db', 3307, 'izartu'));
    }

    public function testAPathIsAUnixSocket(): void
    {
        $this->assertSame(
            'mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=izartu;charset=utf8mb4',
            Database::dsn('/run/mysqld/mysqld.sock', 3307, 'izartu'),
        );
    }
}
