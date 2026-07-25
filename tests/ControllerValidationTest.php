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

final class ControllerValidationTest extends TestCase
{
    public function testValidBookmarkHasNoErrors(): void
    {
        $this->assertSame([], Controller::validate($this->bookmark()));
    }

    public function testTitleIsRequired(): void
    {
        $bookmark = $this->bookmark();
        $bookmark->title = '';

        $this->assertArrayHasKey('title', Controller::validate($bookmark));
    }

    public function testLinkMustBeAValidUrl(): void
    {
        $bookmark = $this->bookmark();
        $bookmark->hlink = 'not a url';

        $this->assertArrayHasKey('link', Controller::validate($bookmark));
    }

    public function testLinkIsRequired(): void
    {
        $bookmark = $this->bookmark();
        $bookmark->hlink = '';

        $this->assertArrayHasKey('link', Controller::validate($bookmark));
    }

    public function testDescriptionIsOptional(): void
    {
        $bookmark = $this->bookmark();
        $bookmark->text = '';

        $this->assertSame([], Controller::validate($bookmark));
    }

    private function bookmark(): Bookmark
    {
        $bookmark = new Bookmark();
        $bookmark->title = 'Valid';
        $bookmark->hlink = 'https://valid.test/page';
        $bookmark->text = 'optional';

        return $bookmark;
    }
}
