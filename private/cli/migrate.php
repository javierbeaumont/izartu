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
 * CLI: bring the database schema up to date.
 *
 * Applies, in order, every migration the database lacks (all of them on a fresh install), then reports its version. It
 * refuses a database it cannot continue from and stops at the first migration that fails. Before connecting, it warns
 * of any `DB_SSL_*` setting another one leaves without effect.
 *
 * Run it:
 *   docker compose exec app php /var/www/izartu/private/cli/migrate.php   (Docker)
 *   php private/cli/migrate.php                                           (bare PHP)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

require dirname(__DIR__) . '/bootstrap.php';

$ignored = Database::sslConflict(DB_HOST, DB_SSL_ENABLED, DB_SSL_VERIFY_CERT, DB_SSL_CA_FILE);
if ($ignored !== '') {
    fwrite(STDERR, "Warning: {$ignored}\n");
}

try {
    $migration = new Migration(Database::connect(), PRIVATE_DIR . 'migration');

    while (($applied = $migration->applyNext()) !== null) {
        fwrite(STDOUT, "Applied {$applied}.\n");
    }

    fwrite(STDOUT, "The database is at version {$migration->version()}.\n");
} catch (PDOException|RuntimeException $e) {
    fwrite(STDERR, 'Could not migrate: ' . $e->getMessage() . "\n");
    exit(1);
}
