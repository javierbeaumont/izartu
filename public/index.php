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
 * Front controller: every request enters here (see `public/.htaccess`, which rewrites non-file requests to this script)
 * and is dispatched by URL path to a view template, which is rendered inside `layout.php`. The routes are listed in
 * Controller::dispatch().
 */

require_once dirname(__DIR__) . '/private/bootstrap.php';

if (DEBUG) {
    ini_set('display_errors', 'stdout');
    error_reporting(E_ALL);
}

Auth::start();

$uri = $_SERVER['REQUEST_URI'] ?? '/';

try {
    [$template, $vars] = Controller::dispatch(is_string($uri) ? $uri : '/');
} catch (Redirect $redirect) {
    header('Location: ' . $redirect->location);
    exit;
}

// Rendered to a string first: the Server-Timing header, which times the render too, must go out before any output.
$page = View::render($template, $vars);

if (DEBUG) {
    header('Server-Timing: ' . Debug::serverTiming());
}

echo $page;
