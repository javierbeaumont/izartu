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
 * Request diagnostics, collected while DEBUG is on: every executed query with
 * its wall time (fed by DebugStatement), reported as a `Server-Timing` header
 * (browser devtools, curl) and as the query panel at the bottom of the page.
 */
class Debug
{
    /** @var list<array{sql: string, ms: float}> */
    private static array $queries = [];

    public static function query(string $sql, float $ms): void
    {
        self::$queries[] = ['sql' => preg_replace('/\s+/', ' ', trim($sql)), 'ms' => $ms];
    }

    /**
     * Executed queries in order, each with how many times its SQL ran in the
     * request (`runs` > 1 flags repeated queries, the N+1 smell).
     *
     * @return list<array{sql: string, ms: float, runs: int}>
     */
    public static function queries(): array
    {
        $runs = array_count_values(array_column(self::$queries, 'sql'));

        return array_map(
            static fn(array $query): array => $query + ['runs' => $runs[$query['sql']]],
            self::$queries,
        );
    }

    /**
     * Request metrics so far: db time (ms), query count, app time (ms) and
     * peak memory (MB). Shared by the header and the query panel. Memory is
     * the peak the script itself used, not the 2 MB blocks the allocator
     * reserves, which would flatline at 2.0 for an app this size.
     *
     * @return array{db: float, queries: int, app: float, mem: float}
     */
    public static function metrics(): array
    {
        return [
            'db' => array_sum(array_column(self::$queries, 'ms')),
            'queries' => count(self::$queries),
            'app' => (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000,
            'mem' => memory_get_peak_usage() / 1048576,
        ];
    }

    public static function serverTiming(): string
    {
        $metrics = self::metrics();

        return sprintf(
            'db;dur=%.1f;desc="%d queries", app;dur=%.1f, mem;desc="peak %.2f MB"',
            $metrics['db'],
            $metrics['queries'],
            $metrics['app'],
            $metrics['mem'],
        );
    }

    public static function reset(): void
    {
        self::$queries = [];
    }
}
