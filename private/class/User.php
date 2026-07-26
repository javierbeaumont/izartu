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
 * User records: look up accounts for authentication.
 */
class User extends Crud
{
    /** The roles a user account may hold. */
    public const ROLES = ['owner', 'admin', 'user'];

    /**
     * Validate the fields needed to create a user account.
     *
     * @param string $email Email address (identity + login).
     * @param string $username Public display name.
     * @param string $password Plain password (checked before hashing).
     * @param string $role One of `self::ROLES`.
     * @return array<string, string> Error message per invalid field; empty if valid.
     */
    public static function validate(string $email, string $username, string $password, string $role): array
    {
        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $errors['email'] = 'A valid email address (max 255 characters) is required.';
        }
        if (!preg_match('/^[A-Za-z0-9_-]{3,32}$/', $username)) {
            $errors['username'] = 'Username must be 3-32 characters of letters, digits, "_" or "-".';
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if (!in_array($role, self::ROLES, true)) {
            $errors['role'] = 'Role must be one of: ' . implode(', ', self::ROLES) . '.';
        }

        return $errors;
    }

    /**
     * Find a user by email address.
     *
     * @param string $email Normalised (lower-case) email to look up.
     * @return array<string, mixed>|null The user row (`id`, `username`, `email`,
     *   `hash`, `role`), or null if no user has that email.
     */
    public function findByEmail(string $email): ?array
    {
        $param = [[':email', $email, PDO::PARAM_STR, 255]];

        $rows = $this->read(
            <<<'SQL'
            SELECT
                `id`, `username`, `email`, `hash`, `role`
            FROM `user`
            WHERE
                `email` = :email
            SQL,
            $param,
        );

        return $rows[0] ?? null;
    }

}
