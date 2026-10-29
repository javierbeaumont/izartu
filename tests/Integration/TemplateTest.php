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

final class TemplateTest extends TestCase
{
    private const TITLE = '<script>alert("t")</script>';

    private const LINK = 'https://x.test/?a="b"&c=<d>';

    private const TEXT = '"d" & <b>';

    private const TAG = '<i>x</i>';

    protected function setUp(): void
    {
        if (DB_NAME !== 'izartu_test') {
            self::fail('Refusing to run against "' . DB_NAME . '"; expected izartu_test.');
        }

        $_SESSION = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        foreach (['bookmark_tag', 'bookmark', 'tag', 'user'] as $table) {
            $pdo->exec('TRUNCATE `' . $table . '`');
        }

        $pdo->exec(
            <<<'SQL'
            INSERT INTO `user`
                (`id`, `username`, `email`, `hash`, `role`)
            VALUES
                (1, 'owner', 'o@izartu.test', 'x', 'user')
            SQL,
        );
        $query = $pdo->prepare(
            <<<'SQL'
            INSERT INTO `bookmark`
                (`id`, `title`, `hlink`, `text`, `user`, `visibility`, `created`, `modified`)
            VALUES
                (1, :title, :hlink, :text, 1, 'public', '2026-01-01 10:00:00', '2026-01-01 10:00:00')
            SQL,
        );
        $query->execute([':title' => self::TITLE, ':hlink' => self::LINK, ':text' => self::TEXT]);
        $tag = $pdo->prepare('INSERT INTO `tag` (`id`, `name`) VALUES (:id, :name)');
        $tag->execute([':id' => 1, ':name' => self::TAG]);
        $tag->execute([':id' => 2, ':name' => 'c++']);
        $pdo->exec('INSERT INTO `bookmark_tag` (`bookmark`, `tag`) VALUES (1, 1), (1, 2)');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
    }

    public function testTheLoginFormCarriesTheSessionToken(): void
    {
        $html = self::page('/login');

        $this->assertStringContainsString('name="csrf" value="' . $_SESSION['csrf'] . '"', $html);
    }

    public function testTheBookmarkFormCarriesTheSessionToken(): void
    {
        $_SESSION['user'] = ['id' => 1, 'username' => 'owner', 'role' => 'user'];
        $_GET = ['add' => ''];

        $html = self::page('/user/owner');

        $this->assertStringContainsString('name="csrf" value="' . $_SESSION['csrf'] . '"', $html);
    }

    /**
     * @return list<array{string}> A page that shows the seeded bookmark or its tags.
     */
    public static function pages(): array
    {
        return [['/'], ['/tag/' . rawurlencode(self::TAG)], ['/user/owner'], ['/tags']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function testWhatAUserTypedIsEscapedAndTagUrlsEncoded(string $uri): void
    {
        $html = self::page($uri);

        $this->assertStringNotContainsString(self::TAG, $html);
        $this->assertStringContainsString(htmlspecialchars(self::TAG), $html);
        $this->assertStringContainsString('href="tag/' . rawurlencode(self::TAG) . '"', $html);
        $this->assertStringContainsString('href="tag/c%2B%2B"', $html);

        // The tag index lists tags, not bookmarks.
        if ($uri !== '/tags') {
            $this->assertStringNotContainsString(self::TITLE, $html);
            $this->assertStringContainsString(htmlspecialchars(self::TITLE), $html);
            $this->assertStringContainsString('href="' . htmlspecialchars(self::LINK) . '"', $html);
            $this->assertStringContainsString(htmlspecialchars(self::TEXT), $html);
        }
    }

    public function testTheEditFormEscapesTheValuesItIsFilledWith(): void
    {
        $_SESSION['user'] = ['id' => 1, 'username' => 'owner', 'role' => 'user'];
        $_GET = ['edit' => '1'];

        $html = self::page('/user/owner');

        $this->assertStringNotContainsString(self::TITLE, $html);
        $this->assertStringContainsString('value="' . htmlspecialchars(self::TITLE) . '"', $html);
        $this->assertStringContainsString('value="' . htmlspecialchars(self::LINK) . '"', $html);
        $this->assertStringContainsString(htmlspecialchars(self::TEXT), $html);
    }

    private static function page(string $uri): string
    {
        return View::render(...Controller::dispatch($uri, ''));
    }
}
