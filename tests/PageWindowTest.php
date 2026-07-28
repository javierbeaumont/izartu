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

final class PageWindowTest extends TestCase
{
    public function testASinglePageHasNoGaps(): void
    {
        $this->assertSame([1], Bookmark::pageWindow(1, 1));
    }

    public function testFewPagesShowEveryNumber(): void
    {
        $this->assertSame([1, 2, 3, 4], Bookmark::pageWindow(2, 4));
    }

    public function testAGapToTheLastPageBecomesNull(): void
    {
        $this->assertSame(
            [1, 2, 3, null, 3124],
            Bookmark::pageWindow(1, 3124),
        );
    }

    public function testAMiddlePageOpensGapsOnBothSides(): void
    {
        $this->assertSame(
            [1, null, 48, 49, 50, 51, 52, null, 100],
            Bookmark::pageWindow(50, 100),
        );
    }

    public function testTheWindowClampsAtTheEdges(): void
    {
        $this->assertSame(
            [1, null, 98, 99, 100],
            Bookmark::pageWindow(100, 100),
        );
    }
}
