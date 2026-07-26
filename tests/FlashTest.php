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

final class FlashTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testPullReturnsTheMessageOnceAndClearsIt(): void
    {
        Flash::set('Bookmark added.');

        $this->assertSame('Bookmark added.', Flash::pull());
        $this->assertNull(Flash::pull());
    }

    public function testPullIsNullWhenNothingWasSet(): void
    {
        $this->assertNull(Flash::pull());
    }

    public function testANewMessageReplacesThePendingOne(): void
    {
        Flash::set('First.');
        Flash::set('Second.');

        $this->assertSame('Second.', Flash::pull());
    }
}
