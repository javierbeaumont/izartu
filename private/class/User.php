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
 * User lookup queries (authentication helpers).
 */
trait User {

  /**
   * Count users matching an email and password.
   *
   * @param string $email Email to match.
   * @param string $password Plain password; hashed with SHA-512 before matching.
   * @return int Number of matching rows.
   */
  static function search(string $email, string $password): int {
    $query = $db->prepare('SELECT `id` FROM `user` WHERE `email` = :email AND `hash` = :hash');
    $query->bindParam(':email', $email, PDO::PARAM_STR, 12);
    $hash = hash('sha512', $password);
    $query->bindParam(':hash', $hash, PDO::PARAM_STR, 128);
    $query->execute();
    return $query->rowCount();
  }

  /**
   * Count users registered with a given email.
   *
   * @param string $email Email to look up.
   * @return int Number of matching rows.
   */
  static function ask(string $email): int {
    $query = $db->prepare('SELECT `id` FROM `user` WHERE `email` = :email');
    $query->bindParam(':email', $email, PDO::PARAM_STR, 12);
    $query->execute();
    return $query->rowCount();
  }

}
