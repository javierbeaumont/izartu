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

final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        // The lifecycle tests below destroy the session; the rest of the suite
        // needs one open (see tests/bootstrap.php).
        Auth::start();
        $_SESSION = [];
    }

    public function testStartOpensASessionAndASecondCallLeavesItAlone(): void
    {
        Auth::logout();

        Auth::start();
        $id = session_id();

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        Auth::start();
        $this->assertSame($id, session_id());
    }

    public function testLogoutEmptiesAndDestroysTheSession(): void
    {
        $_SESSION['user'] = ['id' => 7, 'role' => 'user'];

        Auth::logout();

        $this->assertSame([], $_SESSION);
        $this->assertFalse(Auth::check());
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testTheSessionCookieIsHardened(): void
    {
        Auth::logout();
        Auth::start();

        $cookie = session_get_cookie_params();

        $this->assertSame([true, 'Lax', '/', false], [
            $cookie['httponly'], $cookie['samesite'], $cookie['path'], $cookie['secure'],
        ]);
    }

    public function testOverHttpsTheSessionCookieIsSecure(): void
    {
        Auth::logout();
        $_SERVER['HTTPS'] = 'on';

        try {
            Auth::start();

            $this->assertTrue(session_get_cookie_params()['secure']);
        } finally {
            unset($_SERVER['HTTPS']);
        }
    }

    public function testIdIsNullForAnonymousAndSetWhenLoggedIn(): void
    {
        $this->assertNull(Auth::id());

        $_SESSION['user'] = ['id' => 7, 'role' => 'user'];
        $this->assertSame(7, Auth::id());
    }

    public function testAnonymousCannotManage(): void
    {
        $this->assertFalse(Auth::canManage(self::bookmark(Visibility::Public)));
        $this->assertFalse(Auth::canManage(self::bookmark(Visibility::Private)));
    }

    public function testTheOwnerOfTheBookmarkCanManageIt(): void
    {
        $_SESSION['user'] = ['id' => 1, 'role' => 'user'];

        $this->assertTrue(Auth::canManage(self::bookmark(Visibility::Public)));
        $this->assertTrue(Auth::canManage(self::bookmark(Visibility::Private)));
    }

    public function testARegularUserCannotManageForeignBookmarks(): void
    {
        $_SESSION['user'] = ['id' => 2, 'role' => 'user'];

        $this->assertFalse(Auth::canManage(self::bookmark(Visibility::Public)));
        $this->assertFalse(Auth::canManage(self::bookmark(Visibility::Private)));
    }

    public function testAdminAndOwnerRolesCanManageForeignPublicBookmarks(): void
    {
        foreach (['admin', 'owner'] as $role) {
            $_SESSION['user'] = ['id' => 2, 'role' => $role];

            $this->assertTrue(Auth::canManage(self::bookmark(Visibility::Public)), $role);
        }
    }

    public function testNoRoleManagesSomeoneElsesPrivateBookmark(): void
    {
        foreach (['admin', 'owner'] as $role) {
            $_SESSION['user'] = ['id' => 2, 'role' => $role];

            $this->assertFalse(Auth::canManage(self::bookmark(Visibility::Private)), $role);
        }
    }

    public function testCsrfTokenIsCreatedOnceAndValidates(): void
    {
        $token = Auth::csrfToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, Auth::csrfToken());
        $this->assertTrue(Auth::csrfCheck($token));
    }

    public function testCsrfCheckRejectsWrongOrMissingTokens(): void
    {
        $this->assertFalse(Auth::csrfCheck('anything'), 'no token in the session yet');

        Auth::csrfToken();

        $this->assertFalse(Auth::csrfCheck(str_repeat('0', 64)));
        $this->assertFalse(Auth::csrfCheck(null));
    }

    public function testVerifyAcceptsTheRightPassword(): void
    {
        $this->assertTrue(self::probe()::probeVerify(self::user(), 'secret123'));
    }

    public function testVerifyRejectsAWrongPassword(): void
    {
        $this->assertFalse(self::probe()::probeVerify(self::user(), 'wrong'));
    }

    public function testVerifyRejectsAnUnknownUser(): void
    {
        $this->assertFalse(self::probe()::probeVerify(null, 'secret123'));
    }

    public function testVerifyRejectsACorruptHash(): void
    {
        $this->assertFalse(self::probe()::probeVerify(['hash' => 'not a hash'] + self::user(), 'secret123'));
    }

    /**
     * Regression: the dummy hash was fixed at bcrypt cost 12 while `password_hash()` uses 10 on PHP 8.2 and 8.3, so an
     * unknown email took about four times as long as a real one, and the response time told which accounts exist.
     */
    public function testTheDummyHashCostsWhatARealHashDoes(): void
    {
        $real = password_get_info(password_hash('secret123', PASSWORD_DEFAULT));
        $dummy = password_get_info(self::probe()::probeDummyHash());

        $this->assertSame([$real['algo'], $real['options']], [$dummy['algo'], $dummy['options']]);
    }

    public function testLoginRegeneratesTheSessionAndStoresTheUser(): void
    {
        $before = session_id();

        self::probe()::probeLogin(self::user());

        $this->assertNotSame($before, session_id());
        $this->assertSame(['id' => 1, 'username' => 'owner', 'role' => 'owner'], Auth::user());
    }

    private static function user(): array
    {
        return [
            'id' => 1,
            'username' => 'owner',
            'email' => 'owner@izartu.test',
            'hash' => password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 4]),
            'role' => 'owner',
        ];
    }

    private static function bookmark(Visibility $visibility): Bookmark
    {
        $bookmark = new Bookmark();
        $bookmark->user = 1;
        $bookmark->visibility = $visibility;

        return $bookmark;
    }

    private static function probe(): Auth
    {
        return new class extends Auth {
            public static function probeVerify(?array $user, string $password): bool
            {
                return self::verify($user, $password);
            }

            public static function probeLogin(array $user): void
            {
                self::login($user);
            }

            public static function probeDummyHash(): string
            {
                return self::dummyHash();
            }
        };
    }
}
