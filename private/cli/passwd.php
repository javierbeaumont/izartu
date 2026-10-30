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
 * CLI: change a user's password.
 *
 * Prompts for the user's email and the new password, twice, without showing it on a terminal. Stores its hash and ends
 * every session the user has open, so a leaked password stops working at once.
 *
 * Run it:
 *   docker compose exec app php /var/www/izartu/private/cli/passwd.php   (Docker)
 *   php private/cli/passwd.php                                           (bare PHP, as root or the web server's user)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

require dirname(__DIR__) . '/bootstrap.php';

$email = strtolower(Cli::prompt('Email: '));
$password = Cli::secret('New password: ');
$again = Cli::secret('Repeat it: ');

$error = $password === $again ? User::passwordError($password) : 'The two passwords differ.';
if ($error !== null) {
    fwrite(STDERR, $error . "\n");
    exit(1);
}

try {
    $pdo = Cli::connect();

    $find = $pdo->prepare(
        <<<'SQL'
        SELECT
            `id`
        FROM `user`
        WHERE
            `email` = :email
        SQL,
    );
    $find->execute([':email' => $email]);
    $id = $find->fetchColumn();

    if (!is_int($id)) {
        fwrite(STDERR, "No user has the email {$email}.\n");
        exit(1);
    }

    $update = $pdo->prepare(
        <<<'SQL'
        UPDATE `user`
        SET
            `hash` = :hash
        WHERE
            `id` = :id
        SQL,
    );
    $update->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Could not change the password: ' . $e->getMessage() . "\n");
    exit(1);
}

try {
    $ended = Auth::endSessions($id);
} catch (RuntimeException $e) {
    fwrite(STDERR, "The password changed, but the user's open sessions could not be ended: " . $e->getMessage()
        . " Run this again as a user who can delete PHP's session files.\n");
    exit(1);
}

fwrite(STDOUT, "Changed the password of {$email} and ended {$ended} open " . ($ended === 1 ? 'session' : 'sessions')
    . ".\n");
