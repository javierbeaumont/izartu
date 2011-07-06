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

class Update {

  private $version = '0.0.00';
  private $notice = FALSE;

  function notice() {
    $file = file('http://javierbeaumont.github.com/izartu/version.list');
    foreach ($file as $value) {
      list($version, $level) = explode('/', $value);
      if ($this->version < $version) {
        switch($level) {
          case 0: // Minor bugs
            $this->notice .= '<br />'.sprintf(__('%s version available. Minor bugs update.'), $version);
            break;
          case 1: // New features
            $this->notice .= '<br />'.sprintf(__('%s version availabe. New features version.'), $version);
            break;
          case 2: // Critical bugs
          default:
            $this->notice .= '<br />'.sprintf(__('%s version availabe. Critical version.'), $version);
            break;
        }
      }
    }
    return $this->notice;
  }

}

?>