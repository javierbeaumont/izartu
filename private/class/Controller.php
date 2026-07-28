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
        return self::feed(self::page(), null, null, '');
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

        return self::feed(self::page(), $name, null, 'tag/' . rawurlencode($name));
    }

    /**
     * User page: the feed filtered by one username (`/user/USERNAME`), paginated.
     *
     * `/user/me` is a reserved alias (see `User::RESERVED`) that redirects to
     * the logged-in user's own page. An unknown username renders an empty
     * page rather than a 404, so the response does not reveal whether an
     * account exists.
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

        if ($name === 'me') {
            Auth::guard();
            self::redirect(BASE . '/' . self::ownPage());
        }

        return self::feed(self::page(), null, $name, 'user/' . rawurlencode($name));
    }

    /**
     * The variables every list view shares. Inline form state comes from the
     * query string: `?edit=ID` renders that row as an in-place edit form (the
     * template still gates it with `Auth::canManage`) and `?add` renders an
     * empty form on top of the list. Both are view states of the list, so they
     * live in the query string; mutations POST to the `/add`, `/edit/ID` and
     * `/delete/ID` action routes.
     *
     * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
     */
    private static function feed(
        int $page,
        ?string $tagName,
        ?string $userName,
        string $route,
    ): array {
        return ['home', [
            'page' => $page,
            'tagName' => $tagName,
            'userName' => $userName,
            'mine' => $userName !== null && Auth::check() && $userName === Auth::user()['username'],
            'route' => $route,
            'editId' => Auth::check() ? (int) ($_GET['edit'] ?? 0) : 0,
            'adding' => Auth::check() && isset($_GET['add']),
        ]];
    }

    /**
     * The requested list page number (`?page=N`, 1-based).
     */
    private static function page(): int
    {
        return max(1, (int) ($_GET['page'] ?? 1));
    }

    /**
     * Login: show the form (GET) or process it (POST).
     *
     * On a valid POST (CSRF token plus credentials) the user is logged in and
     * redirected to their own page. Otherwise the form is shown, with an
     * `error` flag after a failed attempt.
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
                self::redirect(BASE . '/' . self::ownPage());
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
     * Add bookmark (POST action): validate and create it, then return to the
     * list the form was on. A GET redirects to the user's own page with the
     * inline add form open (`?add`).
     *
     * Requires a logged-in user. On invalid input the origin list is
     * re-rendered with the inline form, the typed values and the errors.
     *
     * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
     */
    public static function add(): array
    {
        Auth::guard();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::redirect(BASE . '/' . self::ownPage() . '?add');
        }

        $bookmark = new Bookmark();
        $tags = trim($_POST['tags'] ?? '');
        $errors = self::apply($bookmark, $_POST);

        if (!$errors) {
            $bookmark->user = Auth::user()['id'];
            $bookmark->save();
            $bookmark->saveTags($tags);
            Flash::set('Bookmark added.');
            self::redirect(BASE . '/' . self::returnPath());
        }

        [$template, $vars] = self::listFromReturn();

        return [$template, array_merge($vars, [
            'adding' => true, 'formBookmark' => $bookmark, 'formTags' => $tags, 'formErrors' => $errors,
        ])];
    }

    /**
     * Edit bookmark (POST action): validate and save it, then return to the
     * list the form was on. A GET redirects to the user's own page with that
     * row's inline edit form open (`?edit=ID`).
     *
     * Requires a logged-in user who may manage the bookmark; a missing id or a
     * foreign bookmark renders the 404 view (existence is not revealed). On
     * invalid input the origin list is re-rendered with the inline form, the
     * typed values and the errors.
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

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::redirect(BASE . '/' . self::ownPage() . '?edit=' . $bookmark->id);
        }

        $tags = trim($_POST['tags'] ?? '');
        $errors = self::apply($bookmark, $_POST);

        if (!$errors) {
            $bookmark->save();
            $bookmark->saveTags($tags);
            Flash::set('Bookmark saved.');
            self::redirect(BASE . '/' . self::returnPath());
        }

        [$template, $vars] = self::listFromReturn();

        return [$template, array_merge($vars, [
            'editId' => $bookmark->id, 'formBookmark' => $bookmark, 'formTags' => $tags, 'formErrors' => $errors,
        ])];
    }

    /**
     * Delete bookmark (POST action, the Delete button of the inline edit
     * form), then return to the list the form was on.
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
        self::redirect(BASE . '/' . self::returnPath());
    }

    /**
     * The list the submitted form was on, as a safe relative path: the form's
     * hidden `return` field, accepted only when it matches a known list route
     * plus an optional page (never an absolute URL, so it cannot be turned
     * into an open redirect). Falls back to the user's own page.
     */
    private static function returnPath(): string
    {
        $return = $_POST['return'] ?? '';

        return preg_match('#\A(?:tag/[^/?\#\s]+|user/[^/?\#\s]+)?(?:\?page=[1-9][0-9]*)?\z#', $return)
            ? $return
            : self::ownPage();
    }

    /**
     * The logged-in user's own list page (`user/USERNAME`).
     */
    private static function ownPage(): string
    {
        return 'user/' . rawurlencode(Auth::user()['username']);
    }

    /**
     * Rebuild the list view the submitted form was on (see `returnPath()`),
     * to re-render it with the inline form when validation fails.
     *
     * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
     */
    private static function listFromReturn(): array
    {
        [$path, $query] = array_pad(explode('?', self::returnPath(), 2), 2, '');
        parse_str($query, $params);
        $page = max(1, (int) ($params['page'] ?? 1));
        $segments = explode('/', $path);

        return match ($segments[0]) {
            'tag' => self::feed($page, rawurldecode($segments[1]), null, $path),
            'user' => self::feed($page, null, rawurldecode($segments[1]), $path),
            default => self::feed($page, null, null, ''),
        };
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
