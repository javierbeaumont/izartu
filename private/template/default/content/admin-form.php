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
        <form method="post" action="<?php echo $page ; ?>">
          <fieldset>
            <legend><?php echo __('Bookmark') ; ?></legend>
            <div class="bookmark">
              <h2 title="<?php echo __('Bookmark\'s title (maximun 255 caracters).') ; ?>">
                <label for="title"><?php echo __('Title') ; ?></label>
                <input type="text" id="title" name="title" value="<?php echo $bookmark['title'] ; ?>" maxlength="255" />
              </h2>
              <small>
                <label for="link"><?php echo __('Link') ; ?></label>
                <input type="text" id="link" name="link" value="<?php echo $bookmark['link'] ; ?>" maxlength="255" />
              </small>
              <p title="<?php echo __('Bookmark\'s short description (maximun 511 caracters)') ; ?>">
                <label for="description"><?php echo __('Description') ; ?></label>
                <textarea id="description" name="description" ><?php echo $bookmark['description'] ; ?></textarea>
              </p>
              <p title="<?php echo __('Bookmark\'s tags, separated by colons (maximun 255 caracters).') ; ?>">
                <label for="tags"><?php echo __('Tags') ; ?></label>
                <input type="text" id="tags" name="tags" value="<?php echo $bookmark['tags'] ; ?>" maxlength="255" />
              </p>
              <p>Created by <?php echo $bookmark['author'] ; ?> <?php echo $bookmark['modified'] ; ?></p>
            </div>
            <p id="buttons">
              <input type="submit" value="<?php echo __('Submit') ; ?>" />
              <input type="reset" value="<?php echo __('Resett') ; ?>" />
            </p>
          </fieldset>
        </form>
?>