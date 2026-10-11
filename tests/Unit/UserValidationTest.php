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

final class UserValidationTest extends TestCase
{
    public function testAValidUserHasNoErrors(): void
    {
        $this->assertSame([], User::validate('a@b.test', 'javi_1', 'secret123', 'owner'));
    }

    public function testEachRoleIsAccepted(): void
    {
        foreach (User::ROLES as $role) {
            $this->assertSame([], User::validate('a@b.test', 'javi', 'secret123', $role));
        }
    }

    public function testAnUnknownRoleIsRejected(): void
    {
        $this->assertArrayHasKey('role', User::validate('a@b.test', 'javi', 'secret123', 'root'));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function badEmails(): array
    {
        return [['notanemail'], ['a@'], ['@b.test'], [''], ['a b@c.test']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badEmails')]
    public function testInvalidEmailsAreRejected(string $email): void
    {
        $this->assertArrayHasKey('email', User::validate($email, 'javi', 'secret123', 'user'));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function badUsernames(): array
    {
        return [['ab'], [str_repeat('a', 33)], ['has space'], ['bob!'], ['café'], ['']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badUsernames')]
    public function testInvalidUsernamesAreRejected(string $username): void
    {
        $this->assertArrayHasKey('username', User::validate('a@b.test', $username, 'secret123', 'user'));
    }

    public function testReservedUsernamesAreRejectedWhateverTheCase(): void
    {
        foreach (['me', 'ME', 'Me'] as $reserved) {
            $errors = User::validate('a@b.test', $reserved, 'secret123', 'user');

            $this->assertSame('That username is reserved.', $errors['username']);
        }
    }

    public function testShortPasswordsAreRejected(): void
    {
        $this->assertArrayHasKey('password', User::validate('a@b.test', 'javi', 'short', 'user'));
    }
}
