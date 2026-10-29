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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ForeignKeyTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (DB_NAME !== 'izartu_test') {
            self::fail('Refusing to run against "' . DB_NAME . '"; expected izartu_test.');
        }

        $this->pdo = new PDO(Database::dsn(DB_HOST, DB_PORT, DB_NAME), DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['bookmark_tag', 'bookmark', 'tag', 'user'] as $table) {
            $this->pdo->exec('TRUNCATE `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        foreach (
            [
                "INSERT INTO `user` (`id`, `username`, `email`, `hash`) VALUES (1, 'owner', 'o@izartu.test', 'x')",
                "INSERT INTO `bookmark` (`id`, `title`, `hlink`, `text`, `user`) VALUES (1, 'B', 'https://b', '', 1)",
                "INSERT INTO `tag` (`id`, `name`) VALUES (1, 'php')",
                'INSERT INTO `bookmark_tag` (`bookmark`, `tag`) VALUES (1, 1)',
            ] as $sql
        ) {
            $this->pdo->exec($sql);
        }
    }

    public function testDeletingAUserDeletesTheirBookmarksAndTheirLinks(): void
    {
        $this->pdo->exec('DELETE FROM `user` WHERE `id` = 1');

        $this->assertSame(['bookmark' => 0, 'bookmark_tag' => 0, 'tag' => 1], $this->counts());
    }

    public function testDeletingATagDeletesItsLinks(): void
    {
        $this->pdo->exec('DELETE FROM `tag` WHERE `id` = 1');

        $this->assertSame(['bookmark' => 1, 'bookmark_tag' => 0, 'tag' => 0], $this->counts());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function orphans(): array
    {
        return [
            'a bookmark of no user' => [
                "INSERT INTO `bookmark` (`title`, `hlink`, `text`, `user`) VALUES ('B', 'https://b.test', '', 2)",
            ],
            'a link to no bookmark' => ['INSERT INTO `bookmark_tag` (`bookmark`, `tag`) VALUES (2, 1)'],
            'a link to no tag' => ['INSERT INTO `bookmark_tag` (`bookmark`, `tag`) VALUES (1, 2)'],
        ];
    }

    #[DataProvider('orphans')]
    public function testARowPointingNowhereIsRefused(string $sql): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('a foreign key constraint fails');

        $this->pdo->exec($sql);
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = [];
        foreach (['bookmark', 'bookmark_tag', 'tag'] as $table) {
            $counts[$table] = (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        }

        return $counts;
    }
}
