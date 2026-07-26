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
        <div class="bookmark">
          <div class="head">
            <h2><a href="<?php echo htmlspecialchars($bookmark->hlink) ; ?>"><?php echo htmlspecialchars($bookmark->title) ; ?></a></h2>
            <p class="link-page"><a href="<?php echo htmlspecialchars($bookmark->hlink) ; ?>"><?php echo htmlspecialchars($bookmark->hlink) ; ?></a></p>
            <p class="link-info">
              <span class="linker">Added by <a href="user/<?php echo rawurlencode($bookmark->username); ?>"><?php echo htmlspecialchars($bookmark->username) ; ?></a></span>
              <span class="added">on <?php echo $bookmark->add ; ?>.</span>
              <span class="modified">Last modified: <?php echo $bookmark->mod ; ?></span>
            </p>
          </div>
          <div class="info">
            <p class="text"><?php echo htmlspecialchars($bookmark->text) ; ?></p>
<?php if ($tags): ?>
            <p class="tag">Tags: <?php echo $tags; ?></p>
<?php endif; ?>
<?php if ($edit && Auth::canManage($bookmark->user)): ?>
            <div class="manage">
              <a href="edit/<?php echo $bookmark->id; ?>">Edit</a>
              <form method="post" action="delete/<?php echo $bookmark->id; ?>">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(Auth::csrfToken()); ?>">
                <input type="submit" value="Delete">
              </form>
            </div>
<?php endif; ?>
          </div>
        </div>
