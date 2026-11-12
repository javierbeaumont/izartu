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
 * Checks that the PHP running it has the modules izartu expects and, unless `--at-least`, no others: PHP's core as
 * `composer-require-checker.json` lists it, each `ext-*` that `composer.json` requires, mysqlnd and OPcache.
 *
 * The image carries neither file nor this script, so `make check-extensions` passes the files' contents as arguments
 * and the script on stdin:
 *   php -- "$(cat composer.json)" "$(cat composer-require-checker.json)" [--at-least] < docker/check-extensions.php
 */

$moreAllowed = $argc === 4 && $argv[3] === '--at-least';
if ($argc !== 3 && !$moreAllowed) {
    fwrite(STDERR, "usage: php -- COMPOSER_JSON REQUIRE_CHECKER_JSON [--at-least] < check-extensions.php\n");
    exit(2);
}

$composer = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$checker = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);

$expected = $checker['php-core-extensions'];
foreach (array_keys($composer['require']) as $package) {
    if (str_starts_with($package, 'ext-')) {
        $expected[] = substr($package, 4);
    }
}
// The code calls neither: pdo_mysql's driver and the opcode cache.
$expected[] = 'mysqlnd';
$expected[] = 'Zend OPcache';

// Module names differ in case from their packages: `PDO` is `ext-pdo`.
$loaded = get_loaded_extensions();
$missing = array_udiff($expected, $loaded, strcasecmp(...));
$unexpected = array_udiff($loaded, $expected, strcasecmp(...));

$problems = [];
if ($missing !== []) {
    $problems[] = 'missing ' . implode(', ', $missing);
}
if ($unexpected !== [] && !$moreAllowed) {
    $problems[] = 'unexpected ' . implode(', ', $unexpected);
}
if ($problems !== []) {
    fwrite(STDERR, 'EXTENSIONS FAIL: ' . implode('; ', $problems) . "\n");
    exit(1);
}

echo "EXTENSIONS OK\n";
