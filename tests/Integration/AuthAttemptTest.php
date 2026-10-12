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

final class AuthAttemptTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (DB_NAME !== 'izartu_test') {
            self::fail('Refusing to run against "' . DB_NAME . '"; expected izartu_test.');
        }

        $_SESSION = [];

        $this->pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $this->pdo->exec('TRUNCATE `user`');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testAttemptNormalisesTheEmail(): void
    {
        $this->seedUser();

        $this->assertTrue(Auth::attempt('  OWNER@Izartu.Test ', 'secret123'));
        $this->assertTrue(Auth::check());
        $this->assertSame(['id' => 1, 'username' => 'owner', 'role' => 'owner'], Auth::user());
    }

    public function testAttemptRejectsAWrongPassword(): void
    {
        $this->seedUser();

        $this->assertFalse(Auth::attempt('owner@izartu.test', 'wrong'));
        $this->assertFalse(Auth::check());
    }

    public function testAttemptRejectsAnUnknownEmail(): void
    {
        $this->seedUser();

        $this->assertFalse(Auth::attempt('ghost@izartu.test', 'secret123'));
        $this->assertFalse(Auth::check());
    }

    private function seedUser(): void
    {
        $query = $this->pdo->prepare(
            <<<'SQL'
            INSERT INTO `user`
                (`id`, `username`, `email`, `hash`, `role`)
            VALUES
                (1, 'owner', 'owner@izartu.test', :hash, 'owner')
            SQL,
        );
        $query->execute([':hash' => password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 4])]);
    }
}
