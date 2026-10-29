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

final class ViewTest extends TestCase
{
    protected function setUp(): void
    {
        if (DB_NAME !== 'izartu_test') {
            self::fail('Refusing to run against "' . DB_NAME . '"; expected izartu_test.');
        }

        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['bookmark_tag', 'bookmark', 'tag', 'user'] as $table) {
            $pdo->exec('TRUNCATE `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $pdo->exec(
            <<<'SQL'
            INSERT INTO `user`
                (`id`, `username`, `email`, `hash`, `role`)
            VALUES
                (1, 'owner', 'o@izartu.test', 'x', 'user')
            SQL,
        );
        $pdo->exec(
            <<<'SQL'
            INSERT INTO `bookmark`
                (`id`, `title`, `hlink`, `text`, `user`, `visibility`, `created`, `modified`)
            VALUES
                (1, 'Public', 'https://p.test', 'p', 1, 'public', '2026-01-01 10:00:00', '2026-01-01 10:00:00')
            SQL,
        );
        $pdo->exec("INSERT INTO `tag` (`id`, `name`) VALUES (1, 'php')");
        $pdo->exec('INSERT INTO `bookmark_tag` (`bookmark`, `tag`) VALUES (1, 1)');
    }

    /**
     * @return list<array{string}> A request URI.
     */
    public static function pages(): array
    {
        return [['/'], ['/tags'], ['/tag/php'], ['/user/owner'], ['/login'], ['/nope']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function testEveryRouteRendersAWholePage(string $uri): void
    {
        $html = View::render(...Controller::dispatch($uri, ''));

        $this->assertStringStartsWith('<!DOCTYPE html>', ltrim($html));
        $this->assertStringEndsWith('</html>', rtrim($html));
    }

    /**
     * Regression: `home.php` pulled in the tag widget with `include_once`, so a second page rendered in the same
     * process came out without it.
     */
    public function testTheTagWidgetSurvivesASecondRender(): void
    {
        $first = View::render(...Controller::dispatch('/', ''));
        $second = View::render(...Controller::dispatch('/', ''));

        $this->assertStringContainsString('Find a tag', $first);
        $this->assertStringContainsString('Find a tag', $second);
    }

    /**
     * Regression: a template that threw left its output buffer open, half a page in it, which would have come out
     * before the error page.
     */
    public function testATemplateThatFailsLeavesNoOutputBehind(): void
    {
        $level = ob_get_level();
        $broken = new class {
            public function __toString(): string
            {
                throw new RuntimeException('Broken on purpose.');
            }
        };

        try {
            View::render('error', ['reason' => 'error', 'cause' => $broken]);
            $this->fail('Printing the cause must fail.');
        } catch (RuntimeException) {
        }

        $this->assertSame($level, ob_get_level());
    }
}
