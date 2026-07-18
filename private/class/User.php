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
 * User records: look up accounts for authentication.
 */
class User extends Crud {

  /**
   * Find a user by email address.
   *
   * @param string $email Normalised (lower-case) email to look up.
   * @return array<string, mixed>|null The user row (`id`, `username`, `email`,
   *   `hash`, `role`), or null if no user has that email.
   */
  public function findByEmail(string $email): ?array {
    $param = [[':email', $email, PDO::PARAM_STR, 255]];

    $rows = $this->read('
      SELECT `id`, `username`, `email`, `hash`, `role`
      FROM `'.PREFIX.'user`
      WHERE `email` = :email', $param);

    return $rows[0] ?? null;
  }

}
