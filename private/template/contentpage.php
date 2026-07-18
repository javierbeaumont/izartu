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
?>
        <div id="bookmark">
          <div class="bookmark">
            <h2><a href="<?php echo $bookmark['link'] ; ?>"><?php echo $bookmark['title'] ; ?></a></h2>
            <small><a href="<?php echo $bookmark['link'] ; ?>"><?php echo $bookmark['link'] ; ?></a></small> Vote: 3/10
            <p><?php echo $bookmark['description'] ; ?></p>
            <p>Created by <?php echo $bookmark['author'] ; ?> <?php echo $bookmark['modified'] ; ?></p>
          </div>
        </div>