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

final class AuthSessionsTest extends TestCase
{
    private string $root = '';

    private string $savePath = '';

    protected function setUp(): void
    {
        session_write_close();
        $this->savePath = (string) session_save_path();
        $this->root = sys_get_temp_dir() . '/izartu-sessions-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0700);
        session_save_path($this->root);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);

        // The rest of the suite needs a session open (see tests/bootstrap.php).
        session_save_path($this->savePath);
        session_id('');
        Auth::start();
        $_SESSION = [];
    }

    public function testEndsEverySessionOfTheUserAndNoOther(): void
    {
        $laptop = $this->session('7-', ['user' => ['id' => 7]]);
        $phone = $this->session('7-', ['user' => ['id' => 7]]);
        $other = $this->session('70-', ['user' => ['id' => 70]]);
        $anonymous = $this->session('', ['csrf' => 'token']);

        $this->assertSame(2, Auth::endSessions(7));

        $this->assertSame([], $this->read($laptop));
        $this->assertSame([], $this->read($phone));
        $this->assertSame(['user' => ['id' => 70]], $this->read($other));
        $this->assertSame(['csrf' => 'token'], $this->read($anonymous));
    }

    public function testFindsTheSessionsInSubdirectories(): void
    {
        session_save_path("2;{$this->root}");
        mkdir("{$this->root}/7/-", 0700, true);
        mkdir("{$this->root}/7/0", 0700, true);
        $mine = $this->session('7-', ['user' => ['id' => 7]]);
        $other = $this->session('70-', ['user' => ['id' => 70]]);

        $this->assertSame(1, Auth::endSessions(7));

        $this->assertSame([], $this->read($mine));
        $this->assertSame(['user' => ['id' => 70]], $this->read($other));
    }

    public function testASessionDirectoryThatDoesNotExistIsRefused(): void
    {
        session_save_path("{$this->root}/missing");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot read the session directory {$this->root}/missing.");

        Auth::endSessions(7);
    }

    /**
     * Write a session through PHP's own handler, under a new id with the prefix given.
     */
    private function session(string $prefix, array $data): string
    {
        $id = (string) session_create_id($prefix);
        session_id($id);
        session_start();
        $_SESSION = $data;
        session_write_close();

        return $id;
    }

    /**
     * What a request with that session's cookie would find in it.
     */
    private function read(string $id): array
    {
        session_id($id);
        session_start();
        $data = $_SESSION;
        session_write_close();

        return $data;
    }
}
