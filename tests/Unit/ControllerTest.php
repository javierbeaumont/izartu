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
        unset($_SERVER['QUERY_STRING']);
        // Logging out destroys the session; the rest of the suite needs one open.
        Auth::start();
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

    /**
     * Regression: a tag list from the URL kept inner whitespace that saving a tag collapses, so `web  dev` named no tag
     * and `/tag/web%20%20dev` showed an empty list instead of redirecting to `/tag/web%20dev`.
     */
    public function testATagListCollapsesInnerWhitespaceAsSavingATagDoes(): void
    {
        $_GET = ['tag' => "web  dev, Web\tDev"];

        [, $vars] = Controller::tags();

        $this->assertSame(['web dev'], $vars['tagNames']);
        $this->assertSame('tags?tag=web+dev', $vars['route']);
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

    /**
     * @return list<array{string, string}> A request URI and the template it renders.
     */
    public static function routes(): array
    {
        return [
            ['/', 'home'],
            ['/?page=2', 'home'],
            ['/tags', 'tags'],
            ['/tags?q=php', 'tags'],
            ['/tag/php', 'home'],
            ['/tag/php/', 'home'],
            ['/user/javi', 'home'],
            ['/login', 'login'],
            ['/tag/', 'notfound'],
            ['/user/', 'notfound'],
            ['/nope', 'notfound'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routes')]
    public function testEachRouteGoesToItsTemplate(string $uri, string $template): void
    {
        $this->assertSame($template, Controller::dispatch($uri, '')[0]);
    }

    public function testThePathSegmentReachesTheController(): void
    {
        $this->assertSame(['docs', 'php'], Controller::dispatch('/tag/docs,php', '')[1]['tagNames']);
        $this->assertSame(['ci/cd'], Controller::dispatch('/tag/ci%2Fcd', '')[1]['tagNames'], 'an encoded slash');
        $this->assertSame('javi', Controller::dispatch('/user/javi', '')[1]['userName']);
    }

    public function testASubDirectoryInstallRoutesBelowItsBase(): void
    {
        $this->assertSame('tags', Controller::dispatch('/sub/tags', '/sub')[0]);
        $this->assertSame('home', Controller::dispatch('/sub', '/sub')[0]);
        $this->assertSame('home', Controller::dispatch('/sub/', '/sub')[0]);
    }

    /**
     * Regression: the base was stripped as a bare prefix, so with the app in `/sub` a request for `/subtags` was served
     * as `/tags`.
     */
    public function testThePathMustStartWithTheWholeBase(): void
    {
        $this->assertSame('notfound', Controller::dispatch('/subtags', '/sub')[0]);
    }

    public function testARouteThatRedirectsThrowsFromDispatch(): void
    {
        $this->assertSame('/login', self::redirectOf(fn() => Controller::dispatch('/edit/1', '')));
        $this->assertSame('/', self::redirectOf(fn() => Controller::dispatch('/logout', '')));
    }

    public function testANonCanonicalTagListRedirectsToItsCanonicalUrl(): void
    {
        $this->assertSame('/tag/docs,php', self::redirectOf(fn() => Controller::tag('php,docs')), 'sorted');
        $this->assertSame('/tag/php', self::redirectOf(fn() => Controller::tag('PHP')), 'lower-cased');
        $this->assertSame('/tag/php', self::redirectOf(fn() => Controller::tag('php,%20php')), 'deduplicated');
        $this->assertSame(
            '/tag/web%20dev',
            self::redirectOf(fn() => Controller::tag('web%20%20dev')),
            'inner whitespace collapsed',
        );
    }

    public function testTheCanonicalRedirectKeepsTheQueryString(): void
    {
        $_SERVER['QUERY_STRING'] = 'page=2';

        $this->assertSame('/tag/docs,php?page=2', self::redirectOf(fn() => Controller::tag('php,docs')));
    }

    public function testMeRedirectsToTheOwnPage(): void
    {
        $_SESSION['user'] = ['id' => 7, 'username' => 'javi', 'role' => 'user'];

        $this->assertSame('/user/javi', self::redirectOf(fn() => Controller::user('me')));
    }

    public function testMeAndTheActionsSendAnAnonymousVisitorToLogin(): void
    {
        $this->assertSame('/login', self::redirectOf(fn() => Controller::user('me')), 'me');
        $this->assertSame('/login', self::redirectOf(fn() => Controller::add()), 'add');
        $this->assertSame('/login', self::redirectOf(fn() => Controller::edit('1')), 'edit');
        $this->assertSame('/login', self::redirectOf(fn() => Controller::delete('1')), 'delete');
    }

    public function testLogoutEndsTheSessionAndGoesHome(): void
    {
        $_SESSION['user'] = ['id' => 7, 'username' => 'javi', 'role' => 'user'];

        $this->assertSame('/', self::redirectOf(fn() => Controller::logout()));
        $this->assertFalse(Auth::check());
    }

    public function testAGetOnAddOpensTheInlineFormOnTheOwnPage(): void
    {
        $_SESSION['user'] = ['id' => 7, 'username' => 'javi', 'role' => 'user'];

        $this->assertSame('/user/javi?add', self::redirectOf(fn() => Controller::add()));
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

    public function testTooManyTagsAndAStaleTokenAreReportedTogether(): void
    {
        $_SESSION['user'] = ['id' => 1, 'username' => 'goodname', 'role' => 'user'];
        $_SESSION['csrf'] = 'token';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'csrf' => 'stale',
            'title' => 'x',
            'link' => 'https://s.test',
            'tags' => implode(',', array_map(static fn(int $n): string => 'tag' . $n, range(1, 26))),
        ];

        [, $vars] = Controller::add();

        $this->assertSame('Too many tags (max 25).', $vars['formErrors']['tags']);
        $this->assertArrayHasKey('csrf', $vars['formErrors']);
    }

    public function testAddRerendersInlineAndRejectsAForeignReturn(): void
    {
        $_SESSION['user'] = ['id' => 1, 'username' => 'goodname', 'role' => 'user'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'token';
        $_POST = ['csrf' => 'token', 'title' => 'x', 'link' => 'nope', 'return' => 'https://evil.test/'];

        [$template, $vars] = Controller::add();

        $this->assertSame('home', $template);
        $this->assertTrue($vars['adding']);
        $this->assertSame('user/goodname', $vars['route'], 'a foreign return falls back to the own page');
        $this->assertArrayHasKey('link', $vars['formErrors']);
    }

    /**
     * @return list<array{string, string, list<string>, int}> A return value, and the route, tags and page it rebuilds.
     */
    public static function returnsInsideTheApp(): array
    {
        return [
            ['', '', [], 1],
            ['?page=2', '', [], 2],
            ['tag/php', 'tag/php', ['php'], 1],
            ['tag/docs,php?page=2', 'tag/docs,php', ['docs', 'php'], 2],
            ['user/javi', 'user/javi', [], 1],
            ['user/javi?page=3', 'user/javi', [], 3],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('returnsInsideTheApp')]
    public function testAReturnInsideTheAppRebuildsThatList(string $return, string $route, array $tags, int $page): void
    {
        [, $vars] = self::rerenderAdd($return);

        $this->assertSame([$route, $tags, $page], [$vars['route'], $vars['tagNames'], $vars['page']]);
    }

    /**
     * @return list<array{string}> Return values that must not be followed.
     */
    public static function returnsOutsideTheApp(): array
    {
        return [
            ['https://evil.test/'],
            ['//evil.test'],
            ['\\\\evil.test'],
            ['javascript:alert(1)'],
            ['/tag/php'],
            ['tag/php/extra'],
            ['tag/'],
            ['other/x'],
            ['tag/a b'],
            ["tag/php\n"],
            ['tag/php#top'],
            ['tag/php?x=1'],
            ['tag/php?page=2&x=1'],
            ['tag/php?page=0'],
            ['tag/php?page=02'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('returnsOutsideTheApp')]
    public function testAnyOtherReturnFallsBackToTheOwnPage(string $return): void
    {
        [, $vars] = self::rerenderAdd($return);

        $this->assertSame(['user/goodname', 1], [$vars['route'], $vars['page']]);
    }

    /**
     * Post an invalid bookmark, so `add()` re-renders the list its `return` names instead of redirecting.
     */
    private static function rerenderAdd(string $return): array
    {
        $_SESSION['user'] = ['id' => 1, 'username' => 'goodname', 'role' => 'user'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'token';
        $_POST = ['csrf' => 'token', 'title' => '', 'link' => 'nope', 'return' => $return];

        return Controller::add();
    }

    /**
     * Where an action redirects to; the test fails if it does not redirect.
     */
    private static function redirectOf(callable $action): string
    {
        try {
            $action();
        } catch (Redirect $redirect) {
            return $redirect->location;
        }

        self::fail('No redirect');
    }
}
