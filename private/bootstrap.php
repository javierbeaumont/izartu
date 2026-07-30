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
 * Application bootstrap: fixed paths, class autoloading and configuration.
 * Every entry point (`public/index.php`, `private/cli/*`, the test suite)
 * requires this file; `config.php` holds the operator-editable settings.
 */

define('PRIVATE_DIR', __DIR__ . '/');

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

require_once __DIR__ . '/config.php';
