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
 * Session-based authentication: login, logout, session state, route guarding
 * and CSRF tokens. All methods are static; `Auth::start()` must run before any
 * output.
 */
class Auth
{
    /**
     * Start the session with hardened cookie parameters. Safe to call more than
     * once. Must run before any output is sent.
     *
     * @return void
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'path'     => BASE === '' ? '/' : BASE,
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);

        session_start();
    }

    /**
     * Verify an email/password pair and, on success, log the user in.
     *
     * On success the session id is regenerated (to prevent fixation) and the
     * user's id and role are stored in the session.
     *
     * @param string $email Email typed at login (normalised to lower-case here).
     * @param string $password Plain password typed at login.
     * @return bool True if the credentials are valid and the user is now logged in.
     */
    public static function attempt(string $email, string $password): bool
    {
        $user = (new User())->findByEmail(strtolower(trim($email)));

        // Verify even when the email is unknown, against a dummy hash, so response
        // time does not reveal whether an account exists.
        $hash = $user['hash'] ?? '$2y$12$' . str_repeat('.', 53);
        if (!password_verify($password, $hash) || $user === null) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
        ];

        return true;
    }

    /**
     * Log the current user out: clear and destroy the session and its cookie.
     *
     * @return void
     */
    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $c = session_get_cookie_params();

            setcookie(
                (string) session_name(),
                '',
                time() - 42000,
                $c['path'],
                $c['domain'],
                $c['secure'],
                $c['httponly'],
            );
        }

        session_destroy();
    }

    /**
     * Whether a user is currently logged in.
     *
     * @return bool
     */
    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    /**
     * The logged-in user's session data, or null if the request is anonymous.
     *
     * @return array{id: int, username: string, role: string}|null
     */
    public static function user(): ?array
    {
        // The only writer is attempt(), so the shape is trusted from here on.
        /** @var array{id: int, username: string, role: string}|null $user */
        $user = $_SESSION['user'] ?? null;

        return $user;
    }

    /**
     * Require an authenticated user; redirect anonymous requests to `/login`.
     *
     * @return void
     */
    public static function guard(): void
    {
        if (!self::check()) {
            header('Location: ' . BASE . '/login');

            exit;
        }
    }

    /**
     * The logged-in user's id, or null if the request is anonymous.
     *
     * @return int|null
     */
    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    /**
     * Whether the logged-in user may manage (edit/delete) a bookmark.
     *
     * True for the bookmark's owner and for `owner`/`admin` roles; false for
     * anonymous requests.
     *
     * @param int $owner User id of the bookmark's owner.
     * @return bool
     */
    public static function canManage(int $owner): bool
    {
        $user = self::user();

        return $user !== null
          && ($user['id'] === $owner || in_array($user['role'], ['owner', 'admin'], true));
    }

    /**
     * The per-session CSRF token, created on first use.
     *
     * @return string A 64-character hex token.
     */
    public static function csrfToken(): string
    {
        $token = $_SESSION['csrf'] ?? null;

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION['csrf'] = $token;
        }

        return $token;
    }

    /**
     * Validate a submitted CSRF token against the session token.
     *
     * @param string|null $token Token from the request (e.g. `$_POST['csrf']`).
     * @return bool True if it matches the current session token.
     */
    public static function csrfCheck(?string $token): bool
    {
        $known = $_SESSION['csrf'] ?? null;

        return is_string($known)
          && $known !== ''
          && is_string($token)
          && hash_equals($known, $token);
    }

}
