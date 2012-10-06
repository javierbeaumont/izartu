<?php
#  Izartu
#
#  Copyright © 2011-2012 Javier Beaumont <contact@javierbeaumont.org>
#
#  This file is part of Izartu.
#
#  Izartu is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as
#  published by the Free Software Foundation, either version 3 of the
#  License, or (at your option) any later version.
#
#  Izartu is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
#  GNU Affero General Public License for more details.
#
#  You should have received a copy of the GNU Affero General Public License
#  along with Izartu. If not, see <http://www.gnu.org/licenses/>.

/**
 * @file user.php
 * @brief The user class.
 *
 * User methods.
 *
 * @class User
 * @brief User related methods.
**/

trait User {

/**
* @fn search
* @brief search for an user.
*/

  static function search($email, $password) {
    $query = $db->prepare('SELECT `id` FROM `user` WHERE `email` = :email AND `hash` = :hash');
    $query->bindParam(':email', $email, PDO::PARAM_STR, 12);
    $hash = hash('sha512', $password);
    $query->bindParam(':hash', $hash, PDO::PARAM_STR, 128);
    $query->execute();
    return $query->rowCount();
  }

/**
* @fn ask
* @brief ask for a email.
*/

  static function ask($email) {
    $query = $db->prepare('SELECT `id` FROM `user` WHERE `email` = :email');
    $query->bindParam(':email', $email, PDO::PARAM_STR, 12);
    $query->execute();
    return $query->rowCount();
  }

}