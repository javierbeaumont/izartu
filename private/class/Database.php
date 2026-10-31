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

/**
 * The shared PDO connection, opened on the first query.
 */
class Database
{
    /** @var PDO|null The shared connection; null until the first query. */
    private static ?PDO $db = null;

    /**
     * The shared database connection, opened on first use.
     *
     * Building, validating or formatting a model therefore needs no database. With DEBUG on, statements are created as
     * DebugStatement so every query execution is timed.
     *
     * @return PDO The shared connection, opened by `connect()`.
     * @throws DatabaseUnavailable When the database cannot be reached.
     */
    final protected static function db(): PDO
    {
        if (self::$db === null) {
            try {
                self::$db = self::connect();

                if (DEBUG) {
                    self::$db->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DebugStatement::class]);
                }
            } catch (PDOException $e) {
                throw new DatabaseUnavailable($e->getMessage(), 0, $e);
            }
        }

        return self::$db;
    }

    /**
     * Whether the database lacks migrations the code has.
     *
     * @return bool
     * @throws RuntimeException When the database cannot be checked (see `Migration::version()`).
     */
    public static function behind(): bool
    {
        return (new Migration(self::db(), PRIVATE_DIR . 'migration'))->pending();
    }

    /**
     * Open a connection to the database the `DB_*` settings name.
     *
     * The charset is utf8mb4 and the timezone is the server's.
     *
     * @return PDO The connection, with `options()`.
     * @throws PDOException When the database cannot be reached; when TLS may be why, the message says what to check
     *     (`sslHint()`).
     */
    public static function connect(): PDO
    {
        $options = self::options(DB_HOST, DB_SSL_ENABLED, DB_SSL_VERIFY_CERT, DB_SSL_CA_FILE);

        try {
            return new PDO(self::dsn(DB_HOST, DB_PORT, DB_NAME), DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            $hint = self::sslHint($e->getMessage(), $options);

            throw $hint === '' ? $e : new PDOException($e->getMessage() . '. ' . $hint, (int) $e->getCode(), $e);
        }
    }

    /**
     * Build a MySQL DSN.
     *
     * @param string $host Host name or IP address, or the path of a Unix socket (it starts with `/`).
     * @param int $port TCP port, left out of the DSN when it is MySQL's default (3306) and ignored for a socket.
     * @param string $name Database name.
     * @return string The PDO DSN, with the utf8mb4 charset.
     */
    public static function dsn(string $host, int $port, string $name): string
    {
        $dsn = 'mysql:';

        if (strncmp($host, '/', 1)) {
            $dsn .= 'host=' . $host;
            if ($port !== 3306) {
                $dsn .= ';port=' . $port;
            }
        } else {
            $dsn .= 'unix_socket=' . $host;
        }

        return $dsn . ';dbname=' . $name . ';charset=utf8mb4';
    }

    /**
     * The PDO options of a connection.
     *
     * Errors throw, and prepared statements are native, so integer columns come back as `int`. Over TCP the connection
     * is encrypted, unless `$ssl` is off; over a Unix socket TLS does not work.
     *
     * @param string $host Host name or IP address, or a socket path; `localhost` connects over the default socket.
     * @param bool $ssl Whether to encrypt over TCP.
     * @param bool $verify Whether to verify the server's certificate, name included.
     * @param string $ca CA file to verify it against; '' for the CAs the system trusts.
     * @return array<int, bool|int|string> The options.
     */
    public static function options(string $host, bool $ssl, bool $verify, string $ca): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($ssl && !self::socket($host)) {
            // The cipher list, like the CA, turns TLS on, and verifying alone does not: it would connect in clear.
            // DEFAULT is OpenSSL's own list.
            $options += $verify && $ca !== ''
                ? [self::mysql('SSL_CA') => $ca, self::mysql('SSL_VERIFY_SERVER_CERT') => true]
                : [self::mysql('SSL_CIPHER') => 'DEFAULT', self::mysql('SSL_VERIFY_SERVER_CERT') => $verify];
        }

        return $options;
    }

    /**
     * The `DB_SSL_*` settings another one leaves without effect, as a warning for the operator.
     *
     * @param string $host Host name or IP address, or a socket path, as for `options()`.
     * @param bool $ssl Whether to encrypt over TCP.
     * @param bool $verify Whether to verify the server's certificate.
     * @param string $ca CA file to verify it against.
     * @return string The warning; '' when every setting takes effect.
     */
    public static function sslConflict(string $host, bool $ssl, bool $verify, string $ca): string
    {
        return match (true) {
            !$verify && $ca === '' => '',
            self::socket($host) => 'DB_HOST connects over a Unix socket, which is never encrypted: DB_SSL_VERIFY_CERT'
                . ' and DB_SSL_CA_FILE are ignored.',
            !$ssl => 'DB_SSL_ENABLED is off: DB_SSL_VERIFY_CERT and DB_SSL_CA_FILE are ignored.',
            !$verify => 'DB_SSL_VERIFY_CERT is off: DB_SSL_CA_FILE is ignored.',
            default => '',
        };
    }

    /**
     * What to check when a connection over TLS fails.
     *
     * mysqlnd gives one message for every certificate that does not verify, readable CA or not, and reports a server
     * without TLS as gone away.
     *
     * @param string $error The connection's error message.
     * @param array<int, bool|int|string> $options Its options, `options()`'s.
     * @return string The setting to check, as a sentence; '' when TLS is not why.
     */
    public static function sslHint(string $error, array $options): string
    {
        $verify = $options[self::mysql('SSL_VERIFY_SERVER_CERT')] ?? null;
        $ca = (string) ($options[self::mysql('SSL_CA')] ?? '');

        return match (true) {
            $verify === null => '',
            str_contains($error, 'MySQL server has gone away') => 'MySQL may not accept TLS: DB_SSL_ENABLED=0 connects'
                . ' without it.',
            !$verify || !str_contains($error, 'Cannot connect to MySQL using SSL') => '',
            $ca === '' => "MySQL's certificate must be signed by a CA the system trusts and issued for DB_HOST; for a"
                . ' CA of its own, set DB_SSL_CA_FILE.',
            !is_file($ca) || !is_readable($ca) => 'DB_SSL_CA_FILE is not a readable file: ' . $ca . '.',
            default => "MySQL's certificate must be signed by the CA in DB_SSL_CA_FILE and issued for DB_HOST.",
        };
    }

    /**
     * Whether a host is reached over a Unix socket: a socket path, or `localhost`, which uses the default one.
     *
     * @param string $host Host name or IP address, or a socket path.
     * @return bool
     */
    private static function socket(string $host): bool
    {
        return $host === 'localhost' || !strncmp($host, '/', 1);
    }

    /**
     * A pdo_mysql attribute by its name.
     *
     * @param string $name The name after `ATTR_`, such as 'SSL_CA'.
     * @return int The attribute.
     */
    private static function mysql(string $name): int
    {
        $value = constant((PHP_VERSION_ID >= 80400 ? 'Pdo\Mysql::ATTR_' : 'PDO::MYSQL_ATTR_') . $name);

        return is_int($value) ? $value : throw new LogicException('Not a pdo_mysql attribute: ' . $name);
    }
}
