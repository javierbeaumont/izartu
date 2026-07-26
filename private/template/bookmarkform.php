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
      <div class="body">
        <form method="post" action="<?php echo htmlspecialchars($action); ?>" class="login bookmarkform">
          <fieldset>
            <legend><?php echo $action === 'add' ? 'Add bookmark' : 'Edit bookmark'; ?></legend>
<?php foreach ($errors as $error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endforeach; ?>
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(Auth::csrfToken()); ?>">
            <div>
              <label for="title">Title</label>
              <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($bookmark->title); ?>" maxlength="255" required>
            </div>
            <div>
              <label for="link">URL</label>
              <input type="url" id="link" name="link" value="<?php echo htmlspecialchars($bookmark->hlink); ?>" maxlength="2048" required>
            </div>
            <div>
              <label for="description">Description</label>
              <textarea id="description" name="description" rows="5" maxlength="1024"><?php echo htmlspecialchars($bookmark->text); ?></textarea>
            </div>
            <div>
              <label for="tags">Tags (comma-separated)</label>
              <input type="text" id="tags" name="tags" value="<?php echo htmlspecialchars($tags); ?>" maxlength="255">
            </div>
            <div class="check">
              <input type="checkbox" id="visibility" name="visibility" value="public"<?php echo $bookmark->visibility === Visibility::Public ? ' checked' : ''; ?>>
              <label for="visibility">Public (visible to anyone on this instance)</label>
            </div>
            <div><input type="submit" name="save" value="Save"></div>
          </fieldset>
        </form>
      </div>
