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
 * A redirect: thrown by the code that decides it, and sent as a `Location` header by the front controller.
 *
 * Throwing, instead of sending the header and calling `exit` on the spot, ends every request in one place and lets the
 * tests follow a successful login, add, edit or delete: an `exit` would end the test run with them.
 */
final class Redirect extends RuntimeException
{
    /**
     * @param string $location Absolute URL path to redirect to.
     */
    public function __construct(public readonly string $location)
    {
        parent::__construct('Redirect to ' . $location);
    }
}
