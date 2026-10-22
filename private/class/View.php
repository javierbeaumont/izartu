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
 * Render a view template inside the page layout.
 */
final class View
{
    /**
     * The HTML of a page: the layout, with the view template inside it.
     *
     * Each variable becomes a local variable of the templates, and the layout includes the view through `$view`. The
     * templates use `include`, never `include_once`, so one process can render several pages, as the tests do.
     *
     * @param string $template The view's name in `private/template/`, without `.php`.
     * @param array<string, mixed> $vars The variables the templates read.
     * @return string The page's HTML.
     */
    public static function render(string $template, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        $view = PRIVATE_DIR . 'template/' . $template . '.php';

        ob_start();
        require PRIVATE_DIR . 'template/layout.php';

        return (string) ob_get_clean();
    }
}
