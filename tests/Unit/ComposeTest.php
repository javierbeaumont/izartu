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

final class ComposeTest extends TestCase
{
    /**
     * Regression: the `app` service got only the database settings, so the others could not be set with Docker.
     */
    public function testTheAppContainerGetsEverySetting(): void
    {
        preg_match_all("/\\benv\\('([A-Z_]+)'/", (string) file_get_contents(PRIVATE_DIR . 'config.php'), $settings);

        $this->assertEqualsCanonicalizing($settings[1], $this->appEnvironment());
    }

    /**
     * @return list<string> The variables `docker-compose.yml` sets on the `app` service.
     */
    private function appEnvironment(): array
    {
        $compose = (string) file_get_contents(dirname(PRIVATE_DIR) . '/docker-compose.yml');
        preg_match('/^  app:\n(?:    .*\n)*?    environment:\n((?:      .*\n)+)/m', $compose, $block);
        preg_match_all('/^      ([A-Z_]+):/m', $block[1] ?? '', $names);

        return $names[1];
    }
}
