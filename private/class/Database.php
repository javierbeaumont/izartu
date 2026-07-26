<?php
#  izartu: web bookmark manager based on tags
#
#  Copyright (C) 2011-2026 Javier Beaumont <javierbeaumont@users.noreply.github.com>
#
#  This file is part of izartu.
#
#  izartu is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as
#  published by the Free Software Foundation, either version 3 of the
#  License, or (at your option) any later version.
#
#  izartu is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
#  GNU Affero General Public License for more details.
#
#  You should have received a copy of the GNU Affero General Public License
#  along with izartu. If not, see <https://www.gnu.org/licenses/>.

/**
 * Configuration checking and the shared PDO instance.
 *
 * @todo PostgreSQL and SQLite support.
 */
class Database
{
    /** @var PDO|null Shared PDO connection, created once on first construction. */
    protected static ?PDO $db = null;

    /**
     * Open the shared database connection (once).
     *
     * On the first instantiation it builds the PDO instance from the configured
     * `DB_*` constants; later instantiations reuse it. Triggers `E_USER_ERROR`
     * if the connection fails. With DEBUG on, statements are created as
     * DebugStatement so every query execution is timed.
     */
    final public function __construct()
    {
        if (!static::$db) {
            try {
                static::$db = new PDO($this->pdoMySQL(), DB_USER, DB_PASS);
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
            $dsn .= 'host=' . (DB_HOST ?: 'localhost');
            if (DB_PORT && is_int(DB_PORT) && DB_PORT != 3306) {
                $dsn .= ';port=' . DB_PORT;
            }
        } else {
            $dsn .= 'unix_socket=' . DB_HOST;
        }

        if (DB_NAME) {
            $dsn .= ';dbname=' . DB_NAME;
        }

        return $dsn;
    }
}
