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
?>
        <div id="bookmark">'
          <div class="bookmark">
            <h2><a href="<?php echo $bookmark['link'] ; ?>"><?php echo $bookmark['title'] ; ?></a></h2>
            <small><a href="<?php echo $bookmark['link'] ; ?>"><?php echo $bookmark['link'] ; ?></a></small> Vote: 3/10
            <p><?php echo $bookmark['description'] ; ?></p>
            <p>Created by <?php echo $bookmark['author'] ; ?> <?php echo $bookmark['modified'] ; ?></p>
          </div>
        </div>