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
<?php if ($edit): ?>
          <form method="post" action="<?php echo $page ; ?>">
            <fieldset class="data">
              <legend><?php echo __('Bookmark') ; ?></legend>
              <div class="head">
                <div>
                  <label for="title"><?php echo __('Title') ; ?></label>
                  <input type="text" id="title" name="title" title="<?php echo __('Bookmark\'s title (maximun 255 caracters).') ; ?>" value="<?php echo $data['title'] ; ?>" maxlength="255" />
                </div>
                <div>
                  <label for="link"><?php echo __('Link') ; ?></label>
                  <input type="text" id="link" name="link" value="<?php echo $data['hlink'] ; ?>" maxlength="255" />
                </div>
              </div>
              <div class="info">
                <p title="<?php echo __('Bookmark\'s short description (maximun 511 caracters)') ; ?>">
                  <label for="description"><?php echo __('Text') ; ?></label>
                  <textarea id="description" name="description" ><?php echo $data['text'] ; ?></textarea>
                </p>
                <p title="<?php echo __('Bookmark\'s tags, separated by colons (maximun 255 caracters).') ; ?>">
                  <label for="tags"><?php echo __('Tags') ; ?></label>
                  <input type="text" id="tags" name="tags" value="<?php echo $tag ; ?>" maxlength="255" />
                </p>
              </div>
              <div id="buttons">
                <button type="submit" name="save"><img src="theme/default/image/save.png" alt="<?php echo __('Save') ; ?>" /></button>
                <button type="reset"><img src="theme/default/image/reset.png" alt="<?php echo __('Reset') ; ?>" /></button>
                <button type="submit" name="delete"><img src="theme/default/image/trash.png" alt="<?php echo __('Trash') ; ?>" /></button>
              </div>
            </fieldset>
          </form>
<?php else: ?>
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
<?php endif; ?>