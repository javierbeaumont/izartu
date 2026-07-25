<?php
#  izartu: web bookmark manager based on tags
#
#  Copyright (C) 2011-2026 Javier Beaumont <javierbeaumont@users.noreply.github.com>
#
#  This file is part of izartu.
#
#  izartu is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as
#  published by the Free Software Foundation, either version 3 of the
#  License, or (at your option) any later version.
#
#  izartu is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
#  GNU Affero General Public License for more details.
#
#  You should have received a copy of the GNU Affero General Public License
#  along with izartu. If not, see <https://www.gnu.org/licenses/>.

use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
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
}
