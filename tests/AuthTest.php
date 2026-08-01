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

final class AuthTest extends TestCase
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

    public function testIdIsNullForAnonymousAndSetWhenLoggedIn(): void
    {
        $this->assertNull(Auth::id());

        $_SESSION['user'] = ['id' => 7, 'role' => 'user'];
        $this->assertSame(7, Auth::id());
    }

    public function testAnonymousCannotManage(): void
    {
        $this->assertFalse(Auth::canManage(1));
    }

    public function testTheOwnerOfTheBookmarkCanManageIt(): void
    {
        $_SESSION['user'] = ['id' => 7, 'role' => 'user'];

        $this->assertTrue(Auth::canManage(7));
    }

    public function testARegularUserCannotManageForeignBookmarks(): void
    {
        $_SESSION['user'] = ['id' => 7, 'role' => 'user'];

        $this->assertFalse(Auth::canManage(8));
    }

    public function testAdminAndOwnerRolesCanManageAnyBookmark(): void
    {
        $_SESSION['user'] = ['id' => 7, 'role' => 'admin'];
        $this->assertTrue(Auth::canManage(8));

        $_SESSION['user'] = ['id' => 7, 'role' => 'owner'];
        $this->assertTrue(Auth::canManage(8));
    }

    public function testCsrfTokenIsCreatedOnceAndValidates(): void
    {
        $token = Auth::csrfToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, Auth::csrfToken());
        $this->assertTrue(Auth::csrfCheck($token));
    }

    public function testCsrfCheckRejectsWrongOrMissingTokens(): void
    {
        $this->assertFalse(Auth::csrfCheck('anything'), 'no token in the session yet');

        Auth::csrfToken();

        $this->assertFalse(Auth::csrfCheck(str_repeat('0', 64)));
        $this->assertFalse(Auth::csrfCheck(null));
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
