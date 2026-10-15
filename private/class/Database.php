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
     * Building, validating or formatting a model therefore needs no database. The connection comes from the `DB_*`
     * constants; a failure triggers `E_USER_ERROR`. With DEBUG on, statements are created as DebugStatement so every
     * query execution is timed.
     *
     * Prepared statements are native, so integer columns come back as `int`; the charset is utf8mb4 and the timezone
     * is the server's.
     *
     * @return PDO The shared connection.
     */
    final protected static function db(): PDO
    {
        if (self::$db === null) {
            try {
                self::$db = new PDO(self::dsn(DB_HOST, DB_PORT, DB_NAME), DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                if (DEBUG) {
                    self::$db->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DebugStatement::class]);
                }
            } catch (PDOException $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }

        return self::$db;
    }

    /**
     * Build a MySQL DSN, for the app's connection and for the CLI scripts that open their own.
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
}
