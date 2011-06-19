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

class Template {
  private $id;

  function __construct() {
    $this->id = 'default';
  }

  private function setId ($id) {
    $this->id = $id;
  }

  private function getId () {
    return $this->id;
  }

  public function getTitle() {
    return 'Izartu';
  }

  public function getHeader() {
    return 'Izartu';
  }

  public function getOption() {
    $show = new TagShow;
    return $show->tagCloud();
  }

  public function getAdvice() {
    return FALSE;
  }

  public function getContent() {
    $show = new DataShow;
    return $show->listOrderByDate();
  }

  public function getDebug() {
    return Benchmark::get();
  }

  protected function getFile ($file) {
    if (!file_exists(PRI_DIR.'template/'.$this->id.'/'.$file.'.php'))
      $this->id = 'default';
    require_once PRI_DIR.'template/'.$this->id.'/'.$file.'.php';
  }

  public function show () {
    self::getFile('layout');
  }
}
?>