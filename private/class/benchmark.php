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

/**
 * @file benchmark.php
 * @brief Simple class for time and memory use control.
 *
 * Execution time and memory usage statistics.
 *
 * @class Benchmark
 * @brief Execution time and memory usage statistics.
**/

class Benchmark {
  private static $timeStart;
  private static $timeEnd;
  private static $timeTotal;
  private static $memoryStart;
  private static $memoryEnd;
  private static $memoryMax;
  private static $memoryTotal;
  private static $usage;

/**
 * @fn __construct
 * @brief To initialize some class variables.
 */


  function __construct() {
    self::$timeStart = microtime(TRUE);
    self::$memoryStart = memory_get_peak_usage();
  }

/**
 * @fn get
 * @brief To get execution time and memory usage statistics.
 * @return XHTML formated text to add to the template.
 */


  static function get() {
    self::$timeEnd = microtime(TRUE);
    self::$memoryEnd = memory_get_peak_usage();
    self::$memoryMax = memory_get_peak_usage(TRUE);

    self::$timeTotal = number_format(round((self::$timeEnd - self::$timeStart)*1000, 2), 2);
    self::$memoryTotal = number_format(round((self::$memoryEnd - self::$memoryStart)/1024, 2), 2);
    self::$memoryEnd = number_format(round(self::$memoryEnd/1024, 2), 2);
    self::$memoryMax = number_format(round(self::$memoryMax/1024, 2), 2);

    return '
      <div id="debug">
        <p class="section">Execution time:</p>
        <p class="subsection">
          <span class="title">Time:</span>
          <span class="value">'.self::$timeTotal.' ms.</span>
        </p>
        <p class="section">Memory usage:</p>
        <p class="subsection">
          <span class="title">Max:</span>
          <span class="value">'.self::$memoryMax.' KB.</span>
          <span class="title">Used:</span>
          <span class="value">'.self::$memoryEnd.' KB.</span>
          <span class="title">Script:</span>
          <span class="value">'.self::$memoryTotal.' KB.</span>
        </p>
      </div>';
  }
}
?>