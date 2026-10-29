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

final class ControllerAccessTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (DB_NAME !== 'izartu_test') {
            self::fail('Refusing to run against "' . DB_NAME . '"; expected izartu_test.');
        }

        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        foreach (['bookmark_tag', 'bookmark'] as $table) {
            $this->pdo->exec('TRUNCATE `' . $table . '`');
        }

        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `bookmark`
                (`id`, `title`, `hlink`, `text`, `user`, `visibility`, `created`, `modified`)
            VALUES
                (1, 'Secret', 'https://s.test', 's', 1, 'private', '2026-01-01 10:00:00', '2026-01-01 10:00:00')
            SQL,
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_SESSION = [];
        $_POST = [];
    }

    public function testDeletingSomeoneElsesBookmarkIsNotFound(): void
    {
        $_SESSION['user'] = ['id' => 2, 'username' => 'other', 'role' => 'admin'];
        $_SESSION['csrf'] = 'token';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf' => 'token'];

        [$foreign] = Controller::delete('1');
        $this->assertSame('notfound', $foreign, 'a private bookmark of another user');

        [$missing] = Controller::delete('999');
        $this->assertSame('notfound', $missing);
    }

    public function testAdminsCannotEditAForeignPrivateBookmark(): void
    {
        $_SESSION['user'] = ['id' => 2, 'role' => 'admin'];

        [$template] = Controller::edit('1');

        $this->assertSame('notfound', $template);
    }

    public function testTheOwnerEditFormRerendersInlineOnInvalidInput(): void
    {
        $_SESSION['user'] = ['id' => 1, 'username' => 'goodname', 'role' => 'user'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'token';
        $_POST = ['csrf' => 'token', 'title' => '', 'link' => 'https://s.test', 'return' => 'user/goodname'];

        [$template, $vars] = Controller::edit('1');

        $this->assertSame('home', $template);
        $this->assertSame(1, $vars['editId']);
        $this->assertArrayHasKey('title', $vars['formErrors']);
        $this->assertSame('https://s.test', $vars['formBookmark']->hlink, 'typed values survive the re-render');
    }
}
