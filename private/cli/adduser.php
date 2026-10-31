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
 * CLI: create a user account.
 *
 * Every user is created here. Type the `owner` role for the first one: an empty answer gives `user`.
 *
 * Run it:
 *   docker compose exec app php /var/www/izartu/private/cli/adduser.php   (Docker)
 *   php private/cli/adduser.php                                           (bare PHP)
 *
 * Prompts for email, username, password and role, the password twice and without showing it on a terminal; hashes it
 * with `password_hash()` and inserts the user.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

require dirname(__DIR__) . '/bootstrap.php';

$email    = strtolower(Cli::prompt('Email: '));
$username = Cli::prompt('Username: ');
$password = Cli::secret('Password: ');
$again    = Cli::secret('Repeat it: ');
$role     = Cli::prompt('Role (owner/admin/user) [user]: ') ?: 'user';

$errors = User::validate($email, $username, $password, $role);
if ($password !== $again) {
    $errors['password'] = 'The two passwords differ.';
}
if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

try {
    $pdo = Cli::connect();

    $query = $pdo->prepare(
        <<<'SQL'
        INSERT INTO `user`
            (`username`, `email`, `hash`, `role`)
        VALUES
            (:username, :email, :hash, :role)
        SQL,
    );

    $query->execute([
        ':username' => $username,
        ':email'    => $email,
        ':hash'     => password_hash($password, PASSWORD_DEFAULT),
        ':role'     => $role,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Could not create the user: ' . $e->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "Created user #{$pdo->lastInsertId()}: {$email} ({$role}).\n");
