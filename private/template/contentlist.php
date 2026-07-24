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
        <form method="post" action="<?php echo $page ?? '' ; ?>">
          <fieldset class="bookmark">
            <legend>Bookmark</legend>
            <div class="head">
              <div>
                <label for="title">Title</label>
                <input type="text" id="title" name="title" title="Bookmark's title (maximun 255 caracters)." value="<?php echo $bookmark->title ; ?>" maxlength="255" />
              </div>
              <div>
                <label for="link">Link</label>
                <input type="text" id="link" name="link" value="<?php echo $bookmark->hlink ; ?>" maxlength="255" />
              </div>
            </div>
            <div class="info">
              <p title="Bookmark's short description (maximun 511 caracters">
                <label for="description">Text</label>
                <textarea id="description" name="description" rows="5" cols="50"><?php echo $bookmark->text ; ?></textarea>
              </p>
              <p title="Bookmark's tags, separated by colons (maximun 255 caracters).">
                <label for="tags">Tags</label>
                <input type="text" id="tags" name="tags" value="<?php echo $tag ; ?>" maxlength="255" />
              </p>
            </div>
            <div class="buttons">
              <input type="image" class="save" name="save" src="image/save.png" alt="Save" />
              <input type="image" class="trash" name="trash" src="image/trash.png" alt="Trash" />
            </div>
          </fieldset>
        </form>

        <div class="form">
          <div class="bookmark">
            <div class="head">
              <h2><a href="<?php echo $bookmark->hlink ; ?>"><?php echo $bookmark->title ; ?></a></h2>
              <p class="link-page"><a href="<?php echo $bookmark->hlink ; ?>"><?php echo $bookmark->hlink ; ?></a></p>
              <p class="link-info">
                <span class="linker">Added by <?php echo $bookmark->user ; ?></span>
                <span class="added">on <?php echo $bookmark->add ; ?>.</span>
                <span class="modified">Last modified: <?php echo $bookmark->mod ; ?></span>
              </p>
            </div>
            <div class="info">
              <p class="text"><?php echo $bookmark->text ; ?></p>
  <?php if ($tag): ?>
              <p class="tag">Tags: <?php echo $tag; ?></p>
  <?php endif; ?>
            </div>
          </div>
        </div>