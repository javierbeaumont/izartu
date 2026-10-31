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

final class BootstrapTest extends TestCase
{
    public function testWithoutTimezoneTheDatesKeepPhpsOwnZone(): void
    {
        $this->assertSame([0, 'Asia/Tokyo', ''], $this->boot(null));
    }

    public function testTimezoneSetsTheZoneOfTheDates(): void
    {
        $this->assertSame([0, 'Europe/Madrid', ''], $this->boot('Europe/Madrid'));
    }

    public function testAnUnknownTimezoneStopsTheApp(): void
    {
        [$status, $zone, $error] = $this->boot('Europe/Nowhere');

        $this->assertNotSame(0, $status);
        $this->assertSame('', $zone);
        $this->assertStringContainsString('Unknown TIMEZONE "Europe/Nowhere"', $error);
    }

    /**
     * Require the bootstrap in a PHP process of its own, whose `date.timezone` is Asia/Tokyo.
     *
     * @param string|null $timezone The `TIMEZONE` setting, or null for none.
     * @return array{int, string, string} The exit status, the zone the process ends up in, and what it wrote to stderr.
     */
    private function boot(?string $timezone): array
    {
        $env = getenv();
        unset($env['TIMEZONE']);
        if ($timezone !== null) {
            $env['TIMEZONE'] = $timezone;
        }

        $process = proc_open(
            [
                PHP_BINARY, '-d', 'date.timezone=Asia/Tokyo', '-d', 'display_errors=stderr',
                '-r', 'require $argv[1]; echo date_default_timezone_get();', PRIVATE_DIR . 'bootstrap.php',
            ],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env,
        );
        $zone = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);

        return [proc_close($process), $zone, $error];
    }
}
