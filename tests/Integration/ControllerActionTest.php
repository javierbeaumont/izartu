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

final class ControllerActionTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (DB_NAME !== 'izartu_test') {
            self::fail('Refusing to run against "' . DB_NAME . '"; expected izartu_test.');
        }

        $_SESSION = ['csrf' => 'token'];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['bookmark_tag', 'bookmark', 'tag', 'user'] as $table) {
            $this->pdo->exec('TRUNCATE `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $query = $this->pdo->prepare(
            <<<'SQL'
            INSERT INTO `user`
                (`id`, `username`, `email`, `hash`, `role`)
            VALUES
                (1, 'owner', 'owner@izartu.test', :hash, 'user')
            SQL,
        );
        $query->execute([':hash' => password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 4])]);

        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `bookmark`
                (`id`, `title`, `hlink`, `text`, `user`, `visibility`, `created`, `modified`)
            VALUES
                (1, 'Old', 'https://old.test', 'o', 1, 'public', '2026-01-01 10:00:00', '2026-01-01 10:00:00')
            SQL,
        );
        $this->pdo->exec("INSERT INTO `tag` (`id`, `name`) VALUES (1, 'old')");
        $this->pdo->exec('INSERT INTO `bookmark_tag` (`bookmark`, `tag`) VALUES (1, 1)');
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_SESSION = [];
    }

    public function testALoginWithTheRightPasswordGoesToTheOwnPage(): void
    {
        $_POST = ['csrf' => 'token', 'email' => ' Owner@Izartu.Test ', 'password' => 'secret123'];

        $this->assertSame('/user/owner', self::redirectOf(fn() => Controller::login()));
        $this->assertSame(['id' => 1, 'username' => 'owner', 'role' => 'user'], Auth::user());
    }

    public function testALoginWithoutTheSessionTokenIsRefusedEvenWithTheRightPassword(): void
    {
        foreach (['a wrong token' => 'wrong', 'no token' => null] as $case => $token) {
            $_POST = ['csrf' => $token, 'email' => 'owner@izartu.test', 'password' => 'secret123'];

            $this->assertSame(['login', ['error' => true]], Controller::login(), $case);
            $this->assertFalse(Auth::check(), $case);
        }
    }

    public function testAValidBookmarkIsSavedWithItsTagsAndTheListComesBack(): void
    {
        $this->logIn();
        $_POST = [
            'csrf' => 'token',
            'title' => ' New ',
            'link' => 'https://new.test/page',
            'description' => 'Something',
            'tags' => 'PHP, Web  Dev',
            'visibility' => 'on',
            'return' => 'tag/php?page=2',
        ];

        $this->assertSame('/tag/php?page=2', self::redirectOf(fn() => Controller::add()));

        $id = (int) $this->pdo->query('SELECT MAX(`id`) FROM `bookmark`')->fetchColumn();
        $saved = Bookmark::find($id);
        $this->assertNotNull($saved);
        $this->assertSame(['New', 'https://new.test/page', 'Something', 1, Visibility::Public], [
            $saved->title, $saved->hlink, $saved->text, $saved->user, $saved->visibility,
        ]);
        $this->assertSame(['php', 'web dev'], $this->tagsOf($id));
        $this->assertSame('Bookmark added.', Flash::pull());
    }

    public function testAGetOnEditOpensTheInlineFormOnTheOwnPage(): void
    {
        $this->logIn();
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->assertSame('/user/owner?edit=1', self::redirectOf(fn() => Controller::edit('1')));
    }

    public function testAValidEditIsSavedAndTheListComesBack(): void
    {
        $this->logIn();
        $_POST = [
            'csrf' => 'token',
            'title' => 'Edited',
            'link' => 'https://edited.test',
            'description' => '',
            'tags' => 'sql',
            'return' => 'user/owner',
        ];

        $this->assertSame('/user/owner', self::redirectOf(fn() => Controller::edit('1')));

        $saved = Bookmark::find(1);
        $this->assertNotNull($saved);
        $this->assertSame(['Edited', 'https://edited.test', ''], [$saved->title, $saved->hlink, $saved->text]);
        $this->assertSame(Visibility::Private, $saved->visibility, 'an unticked visibility box means private');
        $this->assertSame(['sql'], $this->tagsOf(1), 'the old tags are replaced');
        $this->assertSame('Bookmark saved.', Flash::pull());
    }

    public function testAnEditWithAStaleTokenSavesNothing(): void
    {
        $this->logIn();
        $_POST = ['csrf' => 'stale', 'title' => 'Edited', 'link' => 'https://edited.test', 'tags' => 'sql'];

        [, $vars] = Controller::edit('1');

        $this->assertArrayHasKey('csrf', $vars['formErrors']);
        $this->assertSame('Old', Bookmark::find(1)?->title);
        $this->assertSame(['old'], $this->tagsOf(1));
    }

    public function testDeletingRemovesTheBookmarkAndItsTagLinks(): void
    {
        $this->logIn();
        $_POST = ['csrf' => 'token', 'return' => ''];

        $this->assertSame('/', self::redirectOf(fn() => Controller::delete('1')));

        $this->assertNull(Bookmark::find(1));
        $this->assertSame([], $this->tagsOf(1));
        $this->assertSame('Bookmark deleted.', Flash::pull());
    }

    private function logIn(): void
    {
        $_SESSION['user'] = ['id' => 1, 'username' => 'owner', 'role' => 'user'];
    }

    private function tagsOf(int $bookmark): array
    {
        $query = $this->pdo->prepare(
            <<<'SQL'
            SELECT
                `tag`.`name`
            FROM `bookmark_tag`
            JOIN
                `tag` ON (`tag`.`id` = `bookmark_tag`.`tag`)
            WHERE
                `bookmark_tag`.`bookmark` = :bookmark
            ORDER BY
                `tag`.`name`
            SQL,
        );
        $query->execute([':bookmark' => $bookmark]);

        return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Where an action redirects to; the test fails if it does not redirect.
     */
    private static function redirectOf(callable $action): string
    {
        try {
            $action();
        } catch (Redirect $redirect) {
            return $redirect->location;
        }

        self::fail('No redirect');
    }
}
