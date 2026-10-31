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

final class ConfigTest extends TestCase
{
    public function testTcpIsEncryptedUnverifiedByDefault(): void
    {
        $this->assertSame([true, false], $this->ssl([]));
    }

    public function testACaFileTurnsVerifyingOn(): void
    {
        $this->assertSame([true, true], $this->ssl(['DB_SSL_CA_FILE' => '/ca.pem']));
    }

    public function testOnlyAClearNoTurnsASettingOff(): void
    {
        $this->assertSame(
            [false, false],
            $this->ssl(['DB_SSL_ENABLED' => 'off', 'DB_SSL_VERIFY_CERT' => 'no', 'DB_SSL_CA_FILE' => '/ca.pem']),
        );
        $this->assertSame([true, true], $this->ssl(['DB_SSL_ENABLED' => 'maybe', 'DB_SSL_VERIFY_CERT' => 'maybe']));
    }

    /**
     * Load the settings in a PHP process of its own.
     *
     * @param array<string, string> $ssl The `DB_SSL_*` variables; those left out are unset.
     * @return list<bool> DB_SSL_ENABLED and DB_SSL_VERIFY_CERT.
     */
    private function ssl(array $ssl): array
    {
        $env = array_diff_key(getenv(), array_flip(['DB_SSL_CA_FILE', 'DB_SSL_ENABLED', 'DB_SSL_VERIFY_CERT'])) + $ssl;

        $process = proc_open(
            [
                PHP_BINARY, '-r', 'require $argv[1]; echo json_encode([DB_SSL_ENABLED, DB_SSL_VERIFY_CERT]);',
                PRIVATE_DIR . 'bootstrap.php',
            ],
            [1 => ['pipe', 'w']],
            $pipes,
            null,
            $env,
        );
        $output = (string) stream_get_contents($pipes[1]);
        proc_close($process);

        return json_decode($output, true);
    }
}
