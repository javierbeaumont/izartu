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

?>
        <div id="list">
          <div class="data">
            <div class="head">
              <h2><a href="<?php echo $data['hlink'] ; ?>"><?php echo $data['title'] ; ?></a></h2>
              <p class="link-page"><a href="<?php echo $data['hlink'] ; ?>"><?php echo $data['hlink'] ; ?></a></p>
              <p class="link-info">
                <span class="linker">Added by <?php echo $data['user'] ; ?></span>
                <span class="added">on <?php echo $data['add'] ; ?>.</span>
                <span class="modified">Last modified: <?php echo $data['mod'] ; ?></span>
              </p>
            </div>
            <div class="info">
              <p class="text"><?php echo $data['text'] ; ?></p>
<?php if ($tag): ?>
              <p class="tag">Tags: <?php echo $tag; ?></p>
<?php endif; ?>
            </div>
          </div>
        </div>