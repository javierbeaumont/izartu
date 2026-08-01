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
 * The shared PDO connection.
 */
class Database
{
    /** @var PDO Shared PDO connection, created once on first construction. */
    protected static PDO $db;

    /**
     * Open the shared database connection, once.
     *
     * The first instantiation builds it from the `DB_*` constants and the rest
     * reuse it; a failure triggers `E_USER_ERROR`. With DEBUG on, statements
     * are created as DebugStatement so every query execution is timed.
     *
     * Prepared statements are native, so integer columns come back as `int`;
     * the charset is utf8mb4 and the timezone is the server's.
     */
    final public function __construct()
    {
        if (!isset(static::$db)) {
            try {
                static::$db = new PDO($this->pdoMySQL(), DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                if (DEBUG) {
                    static::$db->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DebugStatement::class]);
                }
            } catch (PDOException $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }
    }

    /**
     * Build the MySQL DSN string from the configured `DB_*` constants.
     *
     * @return string PDO DSN (TCP host/port or Unix socket, plus database name).
     */
    private function pdoMySQL(): string
    {
        $dsn = 'mysql:';

        if (strncmp(DB_HOST, '/', 1)) {
            $dsn .= 'host=' . DB_HOST;
            if (DB_PORT !== 3306) {
                $dsn .= ';port=' . DB_PORT;
            }
        } else {
            $dsn .= 'unix_socket=' . DB_HOST;
        }

        return $dsn . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    }
}
