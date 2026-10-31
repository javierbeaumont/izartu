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
            [119, '1 minute ago'],
            [120, '2 minutes ago'],
            [3599, '59 minutes ago'],
            [3600, '1 hour ago'],
            [5399, '1 hour ago'],
            [7200, '2 hours ago'],
            [86399, '23 hours ago'],
            [86400, '1 day ago'],
            [172800, '2 days ago'],
            [604799, '6 days ago'],
            [604800, '1 week ago'],
            [2419200, '4 weeks ago'],
            [2591999, '4 weeks ago'],
            [2592000, '1 month ago'],
            [31103999, '11 months ago'],
            [31536000, '1 year ago'],
            [63072000, '2 years ago'],
        ];
    }

    #[DataProvider('ages')]
    public function testAgoReadsAsTheLargestFittingUnitTruncated(int $secondsAgo, string $expected): void
    {
        $this->assertSame($expected, Format::ago(self::NOW - $secondsAgo, self::NOW));
    }

    /**
     * Regression: twelve 30-day months are 360 days, short of a 365-day year, so 360 to 364 days read "12 months ago".
     */
    public function testTwelveMonthsReadAsAYear(): void
    {
        $this->assertSame('1 year ago', Format::ago(self::NOW - 31104000, self::NOW));
        $this->assertSame('1 year ago', Format::ago(self::NOW - 31535999, self::NOW));
    }

    public function testAFutureMomentDoesNotReadAsNegative(): void
    {
        $this->assertSame(
            'just now',
            Format::ago(self::NOW + 3600, self::NOW),
            'a clock skew between the app and the database must not surface as "-1 hours ago"',
        );
    }

    public function testWithoutAReferencePointItIsNow(): void
    {
        $this->assertSame('just now', Format::ago(time()));
    }

    public function testAbsoluteAndIsoFollowTheDefaultTimezone(): void
    {
        $zone = date_default_timezone_get();

        try {
            date_default_timezone_set('UTC');
            $this->assertSame('25 July 2026 at 17:20 UTC', Format::absolute(self::NOW));
            $this->assertSame('2026-07-25T17:20:00+00:00', Format::iso(self::NOW));
            $this->assertSame(
                '1 August 2026 at 09:05 UTC',
                Format::absolute(1785575100),
                'no padded day, a padded hour',
            );

            date_default_timezone_set('Europe/Madrid');
            $this->assertSame('25 July 2026 at 19:20 CEST', Format::absolute(self::NOW));
            $this->assertSame('2026-07-25T19:20:00+02:00', Format::iso(self::NOW));
            $this->assertSame('21 January 2026 at 13:53 CET', Format::absolute(1769000000), 'winter time');

            date_default_timezone_set('America/Sao_Paulo');
            $this->assertSame('25 July 2026 at 14:20 -03', Format::absolute(self::NOW), 'a zone with no abbreviation');
        } finally {
            date_default_timezone_set($zone);
        }
    }
}
