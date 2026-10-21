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
 * Application bootstrap: fixed paths, class autoloading and configuration.
 * Every entry point (`public/index.php`, `private/cli/*`, the test suite)
 * requires this file; `config.php` holds the operator-editable settings.
 */

define('PRIVATE_DIR', __DIR__ . '/');
define('PUBLIC_DIR', dirname(__DIR__) . '/public/');

spl_autoload_register(function (string $class): void {
    $file = PRIVATE_DIR . 'class/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/**
 * An environment variable as a string, or a default when unset/empty.
 *
 * @param string $name The variable name.
 * @param string $default Value when the variable is unset or empty.
 * @return string
 */
function env(string $name, string $default = ''): string
{
    $value = getenv($name);

    return is_string($value) && $value !== '' ? $value : $default;
}

/**
 * The base URL path the request came in on: '' at a domain root, '/sub' in a
 * sub-directory install. Off the web there is no request, so the CLI and the
 * test suite get ''.
 *
 * @return string
 */
function basePath(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';

    return PHP_SAPI === 'cli' || !is_string($script) ? '' : rtrim(dirname($script), '/');
}

/**
 * A web-root-relative asset path carrying a cache-busting query string.
 *
 * The file's modification time gives every deployed version its own URL, so a browser never keeps an old copy against
 * new markup. A leading slash is dropped, so the path stays relative for `<base href>` (sub-directory installs). A
 * missing file, a directory and a path with a `..` segment (which may name a file outside the web root) get the plain
 * path.
 *
 * @param string $path Path under the web root, for example 'style/main.css'.
 * @return string The relative path, with '?v=<mtime>' appended when it names a readable file under the web root.
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $inside = !in_array('..', explode('/', $path), true);
    $mtime = $inside && is_file(PUBLIC_DIR . $path) ? filemtime(PUBLIC_DIR . $path) : false;

    return $mtime === false ? $path : $path . '?v=' . $mtime;
}

require_once __DIR__ . '/config.php';
