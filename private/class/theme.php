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

class Theme {
  private $theme;
  private $file;

  public function __construct() {
    ///Cargar configuración;
  }

  private function getTheme () {
/** @TODO Obtain Theme from database **/
    $this->theme = 'default';
    return 'default';
  }

  protected function getFile ($data, $file) {
    $theme = trim($this->getTheme());
    if (!file_exists(PRI_DIR.'template/'.$theme.'/'.$file))
      $theme = 'default';
    require_once (PRI_DIR.'template/'.$theme.'/'.$file);
  }

  public function getFavicon() {
/*      $favicon = is_file ('theme/'.$theme.'/favicon.*');
    if ()
      return '<link rel="shortcut icon"'.
                  ' type="image/png, image/gif"'.
                  ' href="'.$favicon.'" />';
    else*/
      return '<link rel="shortcut icon"'.
                  ' type="image/png, image/gif"'.
                  ' href="favicon" />';
  }


  public function getStyle() {

//      var_dump(json_decode($json, true));
/*
    foreach ($this->style as $value) {
      if (file_exists('theme/'.$this->theme.'/style/'.$value.'.css'))
        $link .= '<link rel="stylesheet"'.
                      ' type="text/css"'.
                      ' href="theme/'.$this->theme.'/style/'.$value.'.css'.
                      ' media="all" />'."\n";
    }

    if ($link)
      return $link;
    else
*/
      return '<link rel="stylesheet"'.
                  ' type="text/css"'.
                  ' href="theme/default/style/main.css"'.
                  ' media="all" />'."\n";
  }
}
?>