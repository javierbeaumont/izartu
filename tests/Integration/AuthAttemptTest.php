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

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->pdo->exec('TRUNCATE `user`');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
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

    /**
     * A hash below PHP's default cost, as PHP 8.2 and 8.3 made them, reaches it at the next login: left behind, it
     * would make its email's login slower or faster than an unknown one's.
     */
    public function testALoginBringsTheHashToTheDefaultCost(): void
    {
        $this->seedUser();

        $this->assertTrue(Auth::attempt('owner@izartu.test', 'secret123'));

        $hash = $this->storedHash();
        $this->assertSame(PASSWORD_BCRYPT_DEFAULT_COST, password_get_info($hash)['options']['cost'] ?? null);
        $this->assertTrue(password_verify('secret123', $hash));
    }

    public function testAHashAtTheDefaultCostIsKept(): void
    {
        $this->seedUser(password_hash('secret123', PASSWORD_DEFAULT));
        $hash = $this->storedHash();

        $this->assertTrue(Auth::attempt('owner@izartu.test', 'secret123'));

        $this->assertSame($hash, $this->storedHash());
    }

    public function testAWrongPasswordKeepsTheHash(): void
    {
        $this->seedUser();
        $hash = $this->storedHash();

        $this->assertFalse(Auth::attempt('owner@izartu.test', 'wrong-password'));

        $this->assertSame($hash, $this->storedHash());
    }

    /**
     * A new password stored between a login's read of the hash and its rehash must stay: the rehash would bring back
     * the old one.
     */
    public function testTheRehashKeepsAHashThatChangedSinceItWasRead(): void
    {
        $this->seedUser();
        $read = $this->storedHash();
        $this->pdo->exec("UPDATE `user` SET `hash` = 'changed' WHERE `id` = 1");

        $this->assertFalse((new User())->replaceHash(1, $read, password_hash('secret123', PASSWORD_DEFAULT)));

        $this->assertSame('changed', $this->storedHash());
    }

    public function testTheRehashTellsHashesThatDifferInCaseApart(): void
    {
        $this->seedUser();
        $read = $this->storedHash();
        $this->pdo->exec('UPDATE `user` SET `hash` = UPPER(`hash`) WHERE `id` = 1');

        $this->assertFalse((new User())->replaceHash(1, $read, password_hash('secret123', PASSWORD_DEFAULT)));

        $this->assertSame(strtoupper($read), $this->storedHash());
    }

    private function seedUser(?string $hash = null): void
    {
        $query = $this->pdo->prepare(
            <<<'SQL'
            INSERT INTO `user`
                (`id`, `username`, `email`, `hash`, `role`)
            VALUES
                (1, 'owner', 'owner@izartu.test', :hash, 'owner')
            SQL,
        );
        $query->execute([':hash' => $hash ?? password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 4])]);
    }

    private function storedHash(): string
    {
        return (string) $this->pdo->query('SELECT `hash` FROM `user` WHERE `id` = 1')->fetchColumn();
    }
}
