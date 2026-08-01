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

final class BookmarkModelTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (DB_NAME !== 'izartu_test') {
            self::fail('Refusing to run against "' . DB_NAME . '"; expected izartu_test.');
        }

        $this->pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $this->pdo->exec('TRUNCATE `bookmark`');
    }

    public function testFindReturnsNullWhenMissing(): void
    {
        $this->assertNull(Bookmark::find(999));
    }

    public function testFindHydratesTheBookmark(): void
    {
        $this->seedBookmark();

        $bookmark = Bookmark::find(1);

        $this->assertInstanceOf(Bookmark::class, $bookmark);
        $this->assertSame('Example', $bookmark->title);
        $this->assertSame('https://e.test', $bookmark->hlink);
        $this->assertSame(1, $bookmark->user);
        $this->assertSame(Visibility::Public, $bookmark->visibility);
    }

    public function testSaveInsertsANewBookmark(): void
    {
        $bookmark = new Bookmark();
        $bookmark->title = 'Fresh';
        $bookmark->hlink = 'https://fresh.test';
        $bookmark->text = 'new';
        $bookmark->user = 1;
        $bookmark->visibility = Visibility::Public;
        $bookmark->save();

        $this->assertNotNull($bookmark->id);

        $found = Bookmark::find($bookmark->id);
        $this->assertSame('Fresh', $found->title);
        $this->assertSame(Visibility::Public, $found->visibility);
    }

    public function testSaveUpdatesAnExistingBookmark(): void
    {
        $this->seedBookmark();

        $bookmark = Bookmark::find(1);
        $bookmark->title = 'Renamed';
        $bookmark->visibility = Visibility::Private;
        $bookmark->save();

        $found = Bookmark::find(1);
        $this->assertSame('Renamed', $found->title);
        $this->assertSame(Visibility::Private, $found->visibility);
    }

    public function testPublicBookmarksAreVisibleToEveryone(): void
    {
        $bookmark = new Bookmark();
        $bookmark->user = 1;
        $bookmark->visibility = Visibility::Public;

        $this->assertTrue($bookmark->visibleTo(null));
        $this->assertTrue($bookmark->visibleTo(1));
        $this->assertTrue($bookmark->visibleTo(2));
    }

    public function testPrivateBookmarksAreVisibleOnlyToTheirOwner(): void
    {
        $bookmark = new Bookmark();
        $bookmark->user = 1;
        $bookmark->visibility = Visibility::Private;

        $this->assertTrue($bookmark->visibleTo(1));
        $this->assertFalse($bookmark->visibleTo(null));
        $this->assertFalse($bookmark->visibleTo(2));
    }

    private function seedBookmark(): void
    {
        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO `bookmark`
                (`id`, `title`, `hlink`, `text`, `user`, `visibility`, `add`, `mod`)
            VALUES
                (1, 'Example', 'https://e.test', 'x', 1, 'public', '2026-01-01 10:00:00', '2026-01-01 10:00:00')
            SQL,
        );
    }
}
