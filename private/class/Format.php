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
 * Present a timestamp for the view.
 *
 * Lists show relative time ("2 days ago"); the exact moment goes in the element's `title` and `datetime`, shown on
 * hover with no JavaScript.
 *
 * Every method takes epoch seconds, as SQL's `UNIX_TIMESTAMP` returns them: nothing here parses a rendered date.
 */
final class Format
{
    /**
     * Units for `ago()`, largest first. The length in seconds is both the threshold for using the unit and its divisor.
     */
    private const UNITS = [
        ['name' => 'year', 'seconds' => 31536000],
        ['name' => 'month', 'seconds' => 2592000],
        ['name' => 'week', 'seconds' => 604800],
        ['name' => 'day', 'seconds' => 86400],
        ['name' => 'hour', 'seconds' => 3600],
        ['name' => 'minute', 'seconds' => 60],
    ];

    /**
     * How long ago a moment was, in words.
     *
     * @param int $timestamp The moment, as epoch seconds.
     * @param int|null $now Reference point, as epoch seconds; the current time by default (the tests pass their own).
     * @return string For example 'just now', '5 minutes ago', '2 days ago'; a future moment (clock skew between app and
     *   database) reads 'just now'.
     */
    public static function ago(int $timestamp, ?int $now = null): string
    {
        $diff = ($now ?? time()) - $timestamp;

        if ($diff < 60) {
            return 'just now';
        }

        foreach (self::UNITS as $unit) {
            if ($diff >= $unit['seconds']) {
                $count = (int) ($diff / $unit['seconds']);

                return $count . ' ' . $unit['name'] . ($count === 1 ? '' : 's') . ' ago';
            }
        }

        return 'just now';
    }

    /**
     * The full date and time, for the tooltip that backs the relative text.
     *
     * @param int $timestamp The moment, as epoch seconds.
     * @return string For example '1 August 2026 at 14:14'.
     */
    public static function absolute(int $timestamp): string
    {
        return date('j F Y \a\t H:i', $timestamp);
    }

    /**
     * The moment as an ISO 8601 string, for a `<time datetime>` attribute.
     *
     * @param int $timestamp The moment, as epoch seconds.
     * @return string For example '2026-08-01T14:14:41+00:00'.
     */
    public static function iso(int $timestamp): string
    {
        return date('c', $timestamp);
    }
}
