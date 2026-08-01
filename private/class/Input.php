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
 * Typed access to request input. Superglobals carry no type guarantees (a
 * crafted request can turn any field into an array), so every read narrows
 * to a string here and the rest of the code never touches them raw.
 */
class Input
{
    /**
     * A POST field as a string ('' when absent or not a string).
     *
     * @param string $key The field name.
     * @return string
     */
    public static function post(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * A query-string field as a string ('' when absent or not a string).
     *
     * @param string $key The field name.
     * @return string
     */
    public static function query(string $key): string
    {
        $value = $_GET[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * Whether a query-string field is present at all (e.g. a bare `?add`).
     *
     * @param string $key The field name.
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset($_GET[$key]);
    }
}
