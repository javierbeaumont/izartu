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

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Relative dates, asserted against a fixed reference point so they do not race the clock.
 */
final class FormatTest extends TestCase
{
    private const NOW = 1785000000;

    /**
     * @return list<array{int, string}> Seconds into the past, expected wording.
     */
    public static function ages(): array
    {
        return [
            [0, 'just now'],
            [59, 'just now'],
            [60, '1 minute ago'],
            [120, '2 minutes ago'],
            [3600, '1 hour ago'],
            [7200, '2 hours ago'],
            [86400, '1 day ago'],
            [172800, '2 days ago'],
            [604800, '1 week ago'],
            [2592000, '1 month ago'],
            [31536000, '1 year ago'],
            [63072000, '2 years ago'],
        ];
    }

    #[DataProvider('ages')]
    public function testAgoReadsAsTheLargestFittingUnit(int $secondsAgo, string $expected): void
    {
        $this->assertSame($expected, Format::ago(self::NOW - $secondsAgo, self::NOW));
    }

    public function testAFutureMomentDoesNotReadAsNegative(): void
    {
        $this->assertSame(
            'just now',
            Format::ago(self::NOW + 3600, self::NOW),
            'a clock skew between the app and the database must not surface as "-1 hours ago"',
        );
    }

    public function testAbsoluteAndIsoDescribeTheSameMoment(): void
    {
        $this->assertSame(date('j F Y \a\t H:i', self::NOW), Format::absolute(self::NOW));
        $this->assertSame(date('c', self::NOW), Format::iso(self::NOW));
    }
}
