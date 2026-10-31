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

final class DatabaseConnectionTest extends TestCase
{
    public function testTargetsTheTestDatabase(): void
    {
        // Guardrail: integration tests must never run against the dev database.
        $this->assertSame('izartu_test', DB_NAME);
    }

    public function testConnects(): void
    {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $this->assertSame(1, (int) $pdo->query('SELECT 1')->fetchColumn());
    }

    public function testTcpIsEncrypted(): void
    {
        $this->assertNotSame('', $this->cipher(Database::options($this->tcpHost(), true, false, '')));
    }

    public function testSslOffConnectsInClear(): void
    {
        $this->assertSame('', $this->cipher(Database::options($this->tcpHost(), false, false, '')));
    }

    public function testACaThatDidNotSignTheServerIsRefused(): void
    {
        $key = openssl_pkey_new();
        $cert = openssl_csr_sign(openssl_csr_new(['commonName' => 'izartu test CA'], $key), null, $key, 1);
        $ca = tempnam(sys_get_temp_dir(), 'izartu-ca');
        $this->assertTrue(openssl_x509_export_to_file($cert, $ca));

        try {
            // Database::sslHint() recognizes this failure by its message.
            $this->expectException(PDOException::class);
            $this->expectExceptionMessage('Cannot connect to MySQL using SSL');
            $this->cipher(Database::options($this->tcpHost(), true, true, $ca));
        } finally {
            unlink($ca);
        }
    }

    public function testTheSystemsCasRefuseMysqlsOwnCertificate(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Cannot connect to MySQL using SSL');
        $this->cipher(Database::options($this->tcpHost(), true, true, ''));
    }

    /**
     * DB_HOST, skipping the test when it is a Unix socket, where TLS does not work.
     */
    private function tcpHost(): string
    {
        if (DB_HOST === 'localhost' || str_starts_with(DB_HOST, '/')) {
            $this->markTestSkipped('DB_HOST is a Unix socket');
        }

        return DB_HOST;
    }

    /**
     * The cipher of a new connection with these options: '' when it is not encrypted.
     *
     * @param array<int, bool|int|string> $options
     */
    private function cipher(array $options): string
    {
        $pdo = new PDO(Database::dsn(DB_HOST, DB_PORT, DB_NAME), DB_USER, DB_PASS, $options);

        return (string) $pdo->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_NUM)[1];
    }
}
