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
     * Verify an email/password pair and, on success, bring the user's hash to PHP's default and log the user in.
     *
     * @param string $email Email typed at login (normalised to lower-case here).
     * @param string $password Plain password typed at login.
     * @return bool True if the credentials are valid, and still are once the user is logged in.
     */
    public static function attempt(string $email, string $password): bool
    {
        $users = new User();
        $user = $users->findByEmail(strtolower(trim($email)));

        if (!self::verify($user, $password)) {
            return false;
        }

        $hash = $user['hash'];

        // At the dummy hash's cost, so this email's login takes as long as an unknown one's.
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $new = password_hash($password, PASSWORD_DEFAULT);
            if ($users->replaceHash($user['id'], $hash, $new)) {
                $hash = $new;
            }
        }

        self::login($user);

        // endSessions() runs after the password changes or the user is deleted, and misses a session not yet created.
        // This one exists now: a change that lands from here on ends it, and one that landed before shows in the hash.
        if ($users->hashOf($user['id']) !== $hash) {
            unset($_SESSION['user']);
            session_regenerate_id(true);

            return false;
        }

        return true;
    }

    /**
     * Whether a password matches a user's hash.
     *
     * An unknown user is checked against a dummy hash, so the response time does not reveal whether an account exists.
     *
     * @param array{id: int, username: string, email: string, hash: string, role: string}|null $user The user row, or
     *   null if no user has the email typed.
     * @param string $password Plain password typed at login.
     * @return bool True only for an existing user whose hash the password matches.
     * @phpstan-assert-if-true !null $user
     */
    protected static function verify(?array $user, string $password): bool
    {
        $hash = $user['hash'] ?? self::dummyHash();

        return password_verify($password, $hash) && $user !== null;
    }

    /**
     * A well-formed bcrypt hash for an unknown user, at the cost `password_hash()` uses on this PHP, so checking it
     * takes as long as checking a real user's hash.
     *
     * @return string A bcrypt hash at `PASSWORD_BCRYPT_DEFAULT_COST`.
     */
    protected static function dummyHash(): string
    {
        return sprintf('$2y$%02d$', PASSWORD_BCRYPT_DEFAULT_COST) . str_repeat('.', 53);
    }

    /**
     * Log a user in: move the session to a new id that starts with the user's id, `<id>-<random>`, and store the user's
     * id, username and role.
     *
     * The new id prevents session fixation, and its prefix lets `endSessions()` find the user's sessions. The data
     * moves to the new id, and the old session is deleted.
     *
     * @param array{id: int, username: string, email: string, hash: string, role: string} $user The user row.
     * @return void
     * @throws RuntimeException When PHP cannot create a session id.
     */
    protected static function login(array $user): void
    {
        $id = session_create_id($user['id'] . '-');
        if ($id === false) {
            throw new RuntimeException('PHP could not create a session id.');
        }

        $data = $_SESSION;
        session_destroy();
        session_id($id);
        session_start();
        $_SESSION = $data;

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
        ];
    }

    /**
     * End every open session of a user by deleting their files; the next request of each finds an empty session.
     *
     * The sessions are found by their id's prefix (see `login()`). It works with PHP's files session handler only, run
     * with the web server's `session.save_path` by a user who may read and delete those files.
     *
     * @param int $user The user's id.
     * @return int How many sessions were ended.
     * @throws RuntimeException When the session directory cannot be read or a session file cannot be deleted.
     */
    public static function endSessions(int $user): int
    {
        [$dir, $pattern] = self::sessionFiles($user, (string) session_save_path());
        if (!is_dir($dir) || !is_readable($dir)) {
            throw new RuntimeException("Cannot read the session directory {$dir}.");
        }

        $ended = 0;
        foreach (glob($pattern) ?: [] as $file) {
            if (@unlink($file)) {
                $ended++;
            } elseif (is_file($file)) {
                throw new RuntimeException("Cannot delete the session file {$file}.");
            }
        }

        return $ended;
    }

    /**
     * Where PHP's files session handler keeps a user's sessions.
     *
     * `session.save_path` is a directory, empty for the system's temporary one, or `N;[MODE;]directory`, which spreads
     * the files over N levels of subdirectories named after the first characters of the session id.
     *
     * @param int $user The user's id.
     * @param string $savePath The value of `session.save_path`.
     * @return array{string, string} The session directory, and a `glob()` pattern for the user's session files.
     */
    protected static function sessionFiles(int $user, string $savePath): array
    {
        $parts = explode(';', $savePath);
        $dir = rtrim((string) end($parts), '/');
        $dir = $dir === '' ? sys_get_temp_dir() : $dir;
        $depth = count($parts) > 1 ? (int) $parts[0] : 0;

        $prefix = $user . '-';
        $levels = '';
        for ($level = 0; $level < $depth; $level++) {
            $levels .= '/' . ($prefix[$level] ?? '*');
        }

        return [$dir, $dir . $levels . '/sess_' . $prefix . '*'];
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
     * @throws Redirect For an anonymous request.
     */
    public static function guard(): void
    {
        if (!self::check()) {
            throw new Redirect(BASE . '/login');
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
     * They must see it and either own it or hold the `owner`/`admin` role, so nobody but its owner manages a private
     * bookmark; false for anonymous requests.
     *
     * @param Bookmark $bookmark The bookmark to manage.
     * @return bool
     */
    public static function canManage(Bookmark $bookmark): bool
    {
        $user = self::user();

        return $user !== null
          && $bookmark->visibleTo($user['id'])
          && ($user['id'] === $bookmark->user || in_array($user['role'], ['owner', 'admin'], true));
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
