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
 * How a failed request is answered: 503 when the database is unreachable or a query fails on a database behind the
 * code, both temporary, and 500 for anything else.
 */
final class Failure
{
    /**
     * Log a failure and decide how to answer it.
     *
     * @param Throwable $failure What went wrong.
     * @param callable(): bool $behind Whether the database lacks migrations; asked only when a query failed.
     * @param callable(string): mixed $log Writes a message to the log.
     * @return array{status: int, reason: 'unavailable'|'upgrading'|'error'} The HTTP status, and why.
     */
    public static function answer(Throwable $failure, callable $behind, callable $log): array
    {
        $log((string) $failure);

        if ($failure instanceof DatabaseUnavailable) {
            return ['status' => 503, 'reason' => 'unavailable'];
        }

        if ($failure instanceof PDOException) {
            try {
                if ($behind()) {
                    $log('The database lacks migrations: run php private/cli/migrate.php.');

                    return ['status' => 503, 'reason' => 'upgrading'];
                }
            } catch (Throwable $check) {
                $log((string) $check);
            }
        }

        return ['status' => 500, 'reason' => 'error'];
    }
}
