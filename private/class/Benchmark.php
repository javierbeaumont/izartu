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
 * Execution time and memory usage statistics.
 */
class Benchmark {

  /** @var float Wall-clock time at construction (seconds). */
  private $timeStart;
  /** @var float Wall-clock time when get() is called (seconds). */
  private $timeEnd;
  /** @var string Elapsed time, formatted (ms). */
  private $timeTotal;
  /** @var int Peak memory at construction (bytes). */
  private $memoryStart;
  /** @var int|string Peak memory when get() is called (bytes, then formatted KB). */
  private $memoryEnd;
  /** @var int|string Real peak memory (bytes, then formatted KB). */
  private $memoryMax;
  /** @var string Script memory delta, formatted (KB). */
  private $memoryTotal;
  /** @var mixed Unused. */
  private $usage;

  /**
   * Record the start time and peak memory usage.
   */
  final public function __construct() {
    $this->timeStart = microtime(TRUE);
    $this->memoryStart = memory_get_peak_usage();
  }

  /**
   * Return an XHTML block with elapsed time and memory usage.
   *
   * @return string XHTML `<dl>` fragment for the debug template.
   */
  final public function get(): string {
    $this->timeEnd = microtime(TRUE);
    $this->memoryEnd = memory_get_peak_usage();
    $this->memoryMax = memory_get_peak_usage(TRUE);

    $this->timeTotal = number_format(round(($this->timeEnd - $this->timeStart)*1000, 2), 2);
    $this->memoryTotal = number_format(round(($this->memoryEnd - $this->memoryStart)/1024, 2), 2);
    $this->memoryEnd = number_format(round($this->memoryEnd/1024, 2), 2);
    $this->memoryMax = number_format(round($this->memoryMax/1024, 2), 2);

    return '
      <dl id="debug">
        <dt class="time">Execution time:</dt>
        <dd>Time: ' . $this->timeTotal . ' ms.</dd>
        <dt class="memory">Memory usage:</dt>
        <dd>Max: ' . $this->memoryMax . ' KB.</dd>
        <dd>Used: ' . $this->memoryEnd . ' KB.</dd>
        <dd>Script: ' . $this->memoryTotal . ' KB.</dd>
      </dl>';
  }

}
