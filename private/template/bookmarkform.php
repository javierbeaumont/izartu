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

# Inline bookmark form, rendered in place of a list row (edit) or on top of
# the list (add). Expects: $formAction, $formBookmark, $formTags, $formErrors,
# plus the list context ($route, $page) for the return field and Cancel link.
$listUrl = $route . ($page > 1 ? '?page=' . $page : '');
?>
        <form method="post" action="<?php echo htmlspecialchars($formAction); ?>" class="bookmark bookmarkform">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(Auth::csrfToken()); ?>">
          <input type="hidden" name="return" value="<?php echo htmlspecialchars($listUrl); ?>">
<?php foreach ($formErrors as $error): ?>
          <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endforeach; ?>
          <div class="head">
            <input class="title" type="text" name="title" value="<?php echo htmlspecialchars($formBookmark->title); ?>" placeholder="Title" maxlength="255" required>
            <input class="link" type="url" name="link" value="<?php echo htmlspecialchars($formBookmark->hlink); ?>" placeholder="https://" maxlength="2048" required>
          </div>
          <div class="info">
            <textarea name="description" rows="3" maxlength="1024" placeholder="Description"><?php echo htmlspecialchars($formBookmark->text); ?></textarea>
            <input class="tags" type="text" name="tags" value="<?php echo htmlspecialchars($formTags); ?>" placeholder="Tags (comma-separated)" maxlength="255">
            <p class="check">
              <input type="checkbox" id="visibility" name="visibility" value="public"<?php echo $formBookmark->visibility === Visibility::Public ? ' checked' : ''; ?>>
              <label for="visibility">Public (visible to anyone on this instance)</label>
            </p>
            <div class="manage">
              <input type="submit" name="save" value="Save">
              <input type="reset" value="Reset">
<?php if ($formBookmark->id): ?>
              <button type="submit" formaction="delete/<?php echo $formBookmark->id; ?>">Delete</button>
<?php endif; ?>
              <a href="<?php echo $listUrl === '' ? '.' : htmlspecialchars($listUrl); ?>">Cancel</a>
            </div>
          </div>
        </form>
