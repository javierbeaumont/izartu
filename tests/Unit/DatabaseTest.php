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

final class DatabaseTest extends TestCase
{
    private const PLAIN = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];

    // What mysqlnd says when the server has no TLS, and when its certificate does not verify.
    private const GONE = 'SQLSTATE[HY000] [2006] MySQL server has gone away';
    private const REFUSED = 'SQLSTATE[HY000] [2002] Cannot connect to MySQL using SSL';

    public function testTheDefaultPortIsLeftOut(): void
    {
        $this->assertSame('mysql:host=db;dbname=izartu;charset=utf8mb4', Database::dsn('db', 3306, 'izartu'));
    }

    public function testAnyOtherPortIsSpelledOut(): void
    {
        $this->assertSame('mysql:host=db;port=3307;dbname=izartu;charset=utf8mb4', Database::dsn('db', 3307, 'izartu'));
    }

    public function testAPathIsAUnixSocket(): void
    {
        $this->assertSame(
            'mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=izartu;charset=utf8mb4',
            Database::dsn('/run/mysqld/mysqld.sock', 3307, 'izartu'),
        );
    }

    public function testTcpIsEncryptedUnverified(): void
    {
        $this->assertSame(['DEFAULT', false], $this->tls(Database::options('db', true, false, '')));
    }

    public function testACaIsVerifiedAgainst(): void
    {
        $this->assertSame(['/ca.pem', true], $this->tls(Database::options('db', true, true, '/ca.pem')));
    }

    public function testWithoutACaTheSystemsCasAreVerifiedAgainst(): void
    {
        $this->assertSame(['DEFAULT', true], $this->tls(Database::options('db', true, true, '')));
    }

    public function testVerifyingOffIgnoresTheCa(): void
    {
        $this->assertSame(['DEFAULT', false], $this->tls(Database::options('db', true, false, '/ca.pem')));
    }

    public function testSslOffLeavesTcpInClear(): void
    {
        $this->assertSame(self::PLAIN, Database::options('db', false, true, '/ca.pem'));
    }

    public function testAUnixSocketIsNeverEncrypted(): void
    {
        $this->assertSame(self::PLAIN, Database::options('localhost', true, true, '/ca.pem'));
        $this->assertSame(self::PLAIN, Database::options('/run/mysqld/mysqld.sock', true, false, ''));
    }

    public function testWarnsOfNothingWhenEverySettingTakesEffect(): void
    {
        $this->assertSame('', Database::sslConflict('db', true, true, '/ca.pem'));
        $this->assertSame('', Database::sslConflict('db', false, false, ''));
        $this->assertSame('', Database::sslConflict('localhost', true, false, ''));
    }

    public function testWarnsOfVerifyingOverASocket(): void
    {
        $this->assertStringStartsWith(
            'DB_HOST connects over a Unix socket',
            Database::sslConflict('localhost', true, true, ''),
        );
    }

    public function testWarnsOfVerifyingWithSslOff(): void
    {
        $this->assertStringStartsWith('DB_SSL_ENABLED is off', Database::sslConflict('db', false, true, '/ca.pem'));
    }

    public function testWarnsOfACaWithVerifyingOff(): void
    {
        $this->assertStringStartsWith('DB_SSL_VERIFY_CERT is off', Database::sslConflict('db', true, false, '/ca.pem'));
    }

    public function testOnlyATlsFailureGetsAHint(): void
    {
        $tls = Database::options('db', true, true, '');

        $this->assertSame('', Database::sslHint('SQLSTATE[HY000] [1045] Access denied for user', $tls));
        $this->assertSame('', Database::sslHint(self::GONE, Database::options('db', false, false, '')));
    }

    public function testAServerGoneAwayMayLackTls(): void
    {
        $this->assertStringContainsString(
            'DB_SSL_ENABLED=0',
            Database::sslHint(self::GONE, Database::options('db', true, false, '')),
        );
    }

    public function testAnUnverifiedCertificateNamesWhereItsCaComesFrom(): void
    {
        $this->assertStringContainsString(
            'a CA the system trusts',
            Database::sslHint(self::REFUSED, Database::options('db', true, true, '')),
        );
        $this->assertStringContainsString(
            'the CA in DB_SSL_CA_FILE',
            Database::sslHint(self::REFUSED, Database::options('db', true, true, __FILE__)),
        );
    }

    public function testAMissingCaFileIsNamed(): void
    {
        $this->assertSame(
            'DB_SSL_CA_FILE is not a readable file: /nowhere/ca.pem.',
            Database::sslHint(self::REFUSED, Database::options('db', true, true, '/nowhere/ca.pem')),
        );
    }

    /**
     * The TLS options among a connection's, by value.
     *
     * @param array<int, bool|int|string> $options
     * @return list<bool|int|string>
     */
    private function tls(array $options): array
    {
        return array_values(array_diff_key($options, self::PLAIN));
    }
}
