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
 * Route handlers for the front controller (index.php). Each handler deals with
 * one route and returns a `[template, variables]` pair to render, or redirects
 * and exits directly.
 */
class Controller
{
    /**
     * Home page: one page of the bookmark feed (`?page=N`) and the tag cloud.
     *
     * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
     */
    public static function home(): array
    {
        return ['home', [
            'page' => max(1, (int) ($_GET['page'] ?? 1)),
            'tagName' => null,
            'userName' => null,
            'route' => '',
        ]];
    }

    /**
     * Tag page: the feed filtered by one tag name (`/tag/NAME`), paginated.
     *
     * An unknown tag renders an empty page rather than a 404, so the response
     * does not reveal whether a (possibly private) tag exists.
     *
     * @param string $name Tag name from the URL (second path segment, encoded).
     * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
     */
    public static function tag(string $name): array
    {
        $name = rawurldecode($name);
        if ($name === '') {
            return self::notFound();
        }

        return ['home', [
            'page' => max(1, (int) ($_GET['page'] ?? 1)),
            'tagName' => $name,
            'userName' => null,
            'route' => 'tag/' . rawurlencode($name),
        ]];
    }

    /**
     * User page: the feed filtered by one username (`/user/USERNAME`), paginated.
     *
     * An unknown username renders an empty page rather than a 404, so the
     * response does not reveal whether an account exists.
     *
     * @param string $name Username from the URL (second path segment, encoded).
     * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
     */
    public static function user(string $name): array
    {
        $name = rawurldecode($name);
        if ($name === '') {
            return self::notFound();
        }

        return ['home', [
            'page' => max(1, (int) ($_GET['page'] ?? 1)),
            'tagName' => null,
            'userName' => $name,
            'route' => 'user/' . rawurlencode($name),
        ]];
    }

    /**
     * Login: show the form (GET) or process it (POST).
     *
     * On a valid POST (CSRF token plus credentials) the user is logged in and
     * redirected to the home page. Otherwise the form is shown, with an `error`
     * flag after a failed attempt.
     *
     * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
     */
    public static function login(): array
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            if (Auth::csrfCheck($_POST['csrf'] ?? null)
                && filter_var($email, FILTER_VALIDATE_EMAIL)
                && Auth::attempt($email, $_POST['password'] ?? '')) {
                self::redirect(BASE . '/');
            }
            return ['login', ['error' => true]];
        }
        return ['login', []];
    }

    /**
     * Logout: destroy the session and redirect to the home page.
     *
     * @return never
     */
    public static function logout(): never
    {
        Auth::logout();
        self::redirect(BASE . '/');
    }

    /**
     * Add bookmark: show the empty form (GET) or validate and create it (POST).
     *
     * Requires a logged-in user. On a valid POST the bookmark and its tags are
     * saved and the request redirects home; otherwise the form is re-rendered
     * with the errors and the typed values.
     *
     * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
     */
    public static function add(): array
    {
        Auth::guard();

        $bookmark = new Bookmark();
        $tags = '';
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tags = trim($_POST['tags'] ?? '');
            $errors = self::apply($bookmark, $_POST);

            if (!$errors) {
                $bookmark->user = Auth::user()['id'];
                $bookmark->save();
                $bookmark->saveTags($tags);
                Flash::set('Bookmark added.');
                self::redirect(BASE . '/');
            }
        }

        return ['bookmarkform', [
            'bookmark' => $bookmark, 'tags' => $tags, 'errors' => $errors, 'action' => 'add',
        ]];
    }

    /**
     * Edit bookmark: show the pre-filled form (GET) or validate and save (POST).
     *
     * Requires a logged-in user who may manage the bookmark; a missing id or a
     * foreign bookmark renders the 404 view (existence is not revealed).
     *
     * @param string $id Bookmark id from the URL (second path segment).
     * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
     */
    public static function edit(string $id): array
    {
        Auth::guard();

        $bookmark = Bookmark::find((int) $id);
        if (!$bookmark || !$bookmark->visibleTo(Auth::id()) || !Auth::canManage($bookmark->user)) {
            return self::notFound();
        }

        $tags = implode(', ', $bookmark->tags());
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tags = trim($_POST['tags'] ?? '');
            $errors = self::apply($bookmark, $_POST);

            if (!$errors) {
                $bookmark->save();
                $bookmark->saveTags($tags);
                Flash::set('Bookmark saved.');
                self::redirect(BASE . '/');
            }
        }

        return ['bookmarkform', [
            'bookmark' => $bookmark, 'tags' => $tags, 'errors' => $errors, 'action' => 'edit/' . $bookmark->id,
        ]];
    }

    /**
     * Delete bookmark (POST only), then redirect home.
     *
     * Requires a logged-in user who may manage the bookmark and a valid CSRF
     * token; anything else (including GET requests) renders the 404 view.
     *
     * @param string $id Bookmark id from the URL (second path segment).
     * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
     */
    public static function delete(string $id): array
    {
        Auth::guard();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::csrfCheck($_POST['csrf'] ?? null)) {
            return self::notFound();
        }

        $bookmark = Bookmark::find((int) $id);
        if (!$bookmark || !$bookmark->visibleTo(Auth::id()) || !Auth::canManage($bookmark->user)) {
            return self::notFound();
        }

        $bookmark->delete();
        Flash::set('Bookmark deleted.');
        self::redirect(BASE . '/');
    }

    /**
     * Validate a bookmark's user-supplied fields.
     *
     * @param Bookmark $bookmark The bookmark to check (already filled).
     * @return array<string, string> Error message per invalid field; empty if valid.
     */
    public static function validate(Bookmark $bookmark): array
    {
        $errors = [];

        if ($bookmark->title === '') {
            $errors['title'] = 'A title is required.';
        } elseif (mb_strlen($bookmark->title) > 255) {
            $errors['title'] = 'The title is too long (max 255 characters).';
        }

        if ($bookmark->hlink === '') {
            $errors['link'] = 'A URL is required.';
        } elseif (!self::isValidLink($bookmark->hlink)) {
            $errors['link'] = 'Enter a valid http:// or https:// URL.';
        } elseif (mb_strlen($bookmark->hlink) > 2048) {
            $errors['link'] = 'The URL is too long (max 2048 characters).';
        }

        if (mb_strlen($bookmark->text) > 1024) {
            $errors['description'] = 'The description is too long (max 1024 characters).';
        }

        return $errors;
    }

    /**
     * Validate a bookmark's normalised tag names.
     *
     * @param list<string> $names Parsed tag names (see `Bookmark::parseTags()`).
     * @return string|null An error message, or null when the tags are fine.
     */
    public static function tagError(array $names): ?string
    {
        if (count($names) > 25) {
            return 'Too many tags (max 25).';
        }
        if (array_filter($names, static fn(string $name): bool => mb_strlen($name) > 255)) {
            return 'A tag is too long (max 255 characters).';
        }

        return null;
    }

    /**
     * Whether a string is an acceptable bookmark URL.
     *
     * Accepts only `http`/`https`, requires a host, and rejects embedded
     * whitespace/control characters and userinfo (`user:pass@`, a phishing
     * vector). Host shape is left permissive on purpose (IPs, `localhost` and
     * intranet hosts are valid targets for a self-hosted instance).
     *
     * @param string $url The URL to check.
     * @return bool
     */
    private static function isValidLink(string $url): bool
    {
        if (preg_match('/[\s\x00-\x1f\x7f]/', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ($parts['host'] ?? '') !== ''
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    /**
     * Fill a bookmark from form input and validate it (CSRF included).
     *
     * @param Bookmark $bookmark The bookmark to fill.
     * @param array<string, mixed> $input The submitted form fields (`$_POST`).
     * @return array<string, string> Error message per invalid field; empty if valid.
     */
    private static function apply(Bookmark $bookmark, array $input): array
    {
        $bookmark->title = trim($input['title'] ?? '');
        $bookmark->hlink = trim($input['link'] ?? '');
        $bookmark->text = trim($input['description'] ?? '');
        $bookmark->visibility = isset($input['visibility']) ? Visibility::Public : Visibility::Private;

        $errors = self::validate($bookmark);

        $tagError = self::tagError(Bookmark::parseTags($input['tags'] ?? ''));
        if ($tagError !== null) {
            $errors['tags'] = $tagError;
        }

        if (!Auth::csrfCheck($input['csrf'] ?? null)) {
            $errors['csrf'] = 'The session expired; please try again.';
        }

        return $errors;
    }

    /**
     * Not found: send a 404 status and render the not-found view.
     *
     * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
     */
    public static function notFound(): array
    {
        http_response_code(404);
        return ['notfound', []];
    }

    /**
     * Send a redirect to the given path and stop the request.
     *
     * @param string $path Absolute URL path to redirect to.
     * @return never
     */
    public static function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

}
