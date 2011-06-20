<?php

#  Izartu
#
#  Copyright © 2011 Javier Beaumont <contact@javierbeaumont.org>
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

class I18n {
  private $content;
  private $value;
  private $locale = FALSE;
  private $lang;
  private $browser = FALSE;
  private $array;

  public function __construct() {
    // Translation list
    $content = scandir(PRI_DIR.'locale/');
    foreach ($content as $value) {
      if ($value != '.' AND $value != '..' AND is_dir(PRI_DIR.'locale/'.$value))
        $locale[] = $value;
    }
    // Browser language list
    if ($_SERVER AND  $_SERVER['HTTP_ACCEPT_LANGUAGE']) {
      $lang = explode (",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
      for ($i = 0; $i < count($lang); $i++) {
        if (preg_match ("/([a-z]{2})(-[a-z]{2})?(;q=[0-9.]+)?/i", trim ($lang[$i]), $browser)) {
          (array_key_exists(3, $browser))? $browser[3] = (str_replace (';q=', FALSE, $browser[3]) * 10): $browser[3] = 10;
          (array_key_exists(2, $browser))?: $browser[2] = FALSE;
          $array[$browser[3]] = array ($browser[1].$browser[2], $browser[1]);
        }
      }
      krsort ($array);
      $browser = array ();
      foreach ($array as $value) {
        $browser[] = $value[0];
        $browser[] = $value[1];
      }
      $browser = array_unique ($browser);
    }
    // Database data language (TODO)
    if (FALSE) {
      $lang = $db->query();
    }
    $this->lang = 'en'; // TODO: Default language defined in database
  }

  public static function getCode() {// TODO
    return 'en';
  }

  public static function getId() { // TODO
    return 1;
  }

}

?>