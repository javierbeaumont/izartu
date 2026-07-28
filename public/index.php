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
 * Front controller: every request enters here (see `public/.htaccess`, which
 * rewrites non-file requests to this script) and is dispatched by URL path to a
 * view template, which is rendered inside `layout.php`. The base path is derived
 * at runtime (`BASE`) so the app works at a domain root or in a sub-directory.
 *
 * Routes:
 * - `/`          Home: public bookmark feed + tag cloud.
 * - `/tag/NAME`  The feed filtered by one tag.
 * - `/tags`      Tag index: every visible tag with its count, paginated;
 *                `?q=TERM` narrows it to the matching tags.
 * - `/user/NAME` The feed filtered by one user (your own page shows your
 *                private bookmarks too when logged in). `/user/me` is a
 *                reserved alias for the logged-in user's own page.
 * - `/login`     Login form (GET) + handler (POST).
 * - `/logout`    Destroy the session, redirect home.
 * - `/add`       Create bookmark (POST action). Login required.
 * - `/edit/ID`   Save bookmark (POST action). Owner/admin only.
 * - `/delete/ID` Delete bookmark (POST action). Owner/admin only.
 *
 * Editing is inline: on any list, `?edit=ID` renders that row as an in-place
 * form and `?add` renders an empty one on top (a GET to `/add` or `/edit/ID`
 * redirects to the user's own page with that state). The forms POST to the
 * action routes above and return to the list they were on.
 *
 * Later features add their own routes here (`/bookmark/ID`, ...); any
 * unmatched path renders the 404 view.
 */

require_once dirname(__DIR__) . '/private/bootstrap.php';

if (DEBUG) {
    ini_set('display_errors', 'stdout');
    error_reporting(E_ALL);
}

// Base path: empty at the domain root, "/sub" in a sub-directory install.
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
define('BASE', $base);

Auth::start();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}

$path = trim($path, '/');
$segments = $path === '' ? [] : explode('/', $path);

[$tpl, $vars] = match ($segments[0] ?? '') {
    ''       => Controller::home(),
    'tag'    => Controller::tag($segments[1] ?? ''),
    'tags'   => Controller::tags(),
    'user'   => Controller::user($segments[1] ?? ''),
    'login'  => Controller::login(),
    'logout' => Controller::logout(),
    'add'    => Controller::add(),
    'edit'   => Controller::edit($segments[1] ?? ''),
    'delete' => Controller::delete($segments[1] ?? ''),
    default  => Controller::notFound(),
};

extract($vars, EXTR_SKIP);
$view = PRIVATE_DIR . 'template/' . $tpl . '.php';

// Buffer the render so the Server-Timing header can be set after it.
ob_start();
require_once PRIVATE_DIR . 'template/layout.php';

if (DEBUG) {
    header('Server-Timing: ' . Debug::serverTiming());
}
