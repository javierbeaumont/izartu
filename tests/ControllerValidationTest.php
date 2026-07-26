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

    /**
     * @return list<string>
     */
    public static function dangerousUrls(): array
    {
        return [
            ['javascript:alert(1)'],
            ['javascript://comment%0aalert(1)'],
            ['data:text/html,<script>alert(1)</script>'],
            ['file:///etc/passwd'],
            ['ftp://host/file'],
            ['http://user:pass@host.test/'],
            ['http://bob@host.test/'],
            ["http://host.test/\npath"],
            ['//host.test/no-scheme'],
            ['http://'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dangerousUrls')]
    public function testRejectsUnsafeOrMalformedUrls(string $url): void
    {
        $bookmark = $this->bookmark();
        $bookmark->hlink = $url;

        $this->assertArrayHasKey('link', Controller::validate($bookmark));
    }

    /**
     * @return list<string>
     */
    public static function validUrls(): array
    {
        return [
            ['https://example.org'],
            ['http://example.org/path?q=1#frag'],
            ['https://sub.example.org:8443/a/b'],
            ['http://localhost/dev'],
            ['http://192.168.1.10/router'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validUrls')]
    public function testAcceptsHttpAndHttpsUrls(string $url): void
    {
        $bookmark = $this->bookmark();
        $bookmark->hlink = $url;

        $this->assertArrayNotHasKey('link', Controller::validate($bookmark));
    }

    public function testTitleLengthIsCapped(): void
    {
        $bookmark = $this->bookmark();
        $bookmark->title = str_repeat('a', 256);

        $this->assertArrayHasKey('title', Controller::validate($bookmark));
    }

    public function testDescriptionLengthIsCapped(): void
    {
        $bookmark = $this->bookmark();
        $bookmark->text = str_repeat('a', 1025);

        $this->assertArrayHasKey('description', Controller::validate($bookmark));
    }

    public function testUrlLengthIsCapped(): void
    {
        $bookmark = $this->bookmark();
        $bookmark->hlink = 'https://e.test/' . str_repeat('a', 2048);

        $this->assertArrayHasKey('link', Controller::validate($bookmark));
    }

    public function testTagsAreFineWithinLimits(): void
    {
        $this->assertNull(Controller::tagError(['php', 'sql', 'web dev']));
        $this->assertNull(Controller::tagError([]));
    }

    public function testTooManyTagsIsRejected(): void
    {
        $names = array_map(static fn(int $i): string => 'tag' . $i, range(1, 26));

        $this->assertNotNull(Controller::tagError($names));
    }

    public function testAnOverlongTagIsRejected(): void
    {
        $this->assertNotNull(Controller::tagError(['ok', str_repeat('x', 256)]));
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
