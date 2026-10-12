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

use PHPUnit\Framework\TestCase;

final class ControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_SESSION = [];
        $_POST = [];
    }

    public function testTheHomeFeedIsTheUnfilteredList(): void
    {
        [$template, $vars] = Controller::home();

        $this->assertSame('home', $template);
        $this->assertSame([], $vars['tagNames']);
        $this->assertNull($vars['userName']);
        $this->assertSame('', $vars['route']);
        $this->assertSame(1, $vars['page']);
    }

    public function testTheTagIndexNormalisesItsScopeAndCarriesItInTheRoute(): void
    {
        $_GET = ['q' => ' ph ', 'tag' => 'PHP, docs, php', 'user' => ' javi ', 'page' => '3'];

        [$template, $vars] = Controller::tags();

        $this->assertSame('tags', $template);
        $this->assertSame('ph', $vars['q']);
        $this->assertSame(['docs', 'php'], $vars['tagNames']);
        $this->assertSame('javi', $vars['userName']);
        $this->assertSame(3, $vars['page']);
        $this->assertSame('tags?q=ph&tag=docs%2Cphp&user=javi', $vars['route']);
    }

    public function testTheTagIndexWithoutScopeHasABareRoute(): void
    {
        [, $vars] = Controller::tags();

        $this->assertSame('tags', $vars['route']);
    }

    public function testATagListThatNormalisesToNothingIsNotFound(): void
    {
        [$template] = Controller::tag(', ,');

        $this->assertSame('notfound', $template);
    }

    public function testAnEmptyUsernameIsNotFound(): void
    {
        [$template] = Controller::user('');

        $this->assertSame('notfound', $template);
    }

    public function testTheLoginFormIsShownOnAGet(): void
    {
        [$template, $vars] = Controller::login();

        $this->assertSame('login', $template);
        $this->assertSame([], $vars);
    }

    public function testDeleteRefusesAnythingButAPostWithAValidToken(): void
    {
        // This tier has no database, so any query would fail the test: passing proves nothing was looked up or deleted.
        $_SESSION['user'] = ['id' => 1, 'username' => 'goodname', 'role' => 'user'];
        $_SESSION['csrf'] = 'token';

        [$onGet] = Controller::delete('1');
        $this->assertSame('notfound', $onGet, 'a GET cannot delete');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf' => 'wrong'];
        [$onBadToken] = Controller::delete('1');
        $this->assertSame('notfound', $onBadToken);
    }

    public function testTheOwnUserPageIsMarkedAsMine(): void
    {
        $_SESSION['user'] = ['id' => 7, 'username' => 'javi', 'role' => 'user'];

        [$template, $vars] = Controller::user('javi');

        $this->assertSame('home', $template);
        $this->assertTrue($vars['mine']);
        $this->assertSame('javi', $vars['userName']);
        $this->assertSame('user/javi', $vars['route']);
    }

    public function testATagRouteAcceptsSeveralCommaSeparatedTags(): void
    {
        // The canonical form (sorted, lower-case, deduplicated); any other
        // spelling redirects to it.
        [$template, $vars] = Controller::tag('docs,php');

        $this->assertSame('home', $template);
        $this->assertSame(['docs', 'php'], $vars['tagNames']);
        $this->assertSame('tag/docs,php', $vars['route']);
    }

    public function testAForeignUserPageIsNotMine(): void
    {
        $_SESSION['user'] = ['id' => 7, 'username' => 'javi', 'role' => 'user'];

        [, $vars] = Controller::user('other');

        $this->assertFalse($vars['mine']);
    }

    public function testLoginRejectsAMalformedEmailWithoutTouchingTheDatabase(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'token';
        $_POST = ['csrf' => 'token', 'email' => 'not an email', 'password' => 'secret123'];

        [$template, $vars] = Controller::login();

        $this->assertSame('login', $template);
        $this->assertTrue($vars['error']);
        $this->assertFalse(Auth::check());

        $_POST = [];
    }
}
