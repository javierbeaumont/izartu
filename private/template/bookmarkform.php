<?php
//  izartu: web bookmark manager based on tags
//
//  Copyright (C) 2011-2026 Javier Beaumont <javierbeaumont@users.noreply.github.com>
//
//  This file is part of izartu.
//
//  izartu is free software: you can redistribute it and/or modify
//  it under the terms of the GNU Affero General Public License as
//  published by the Free Software Foundation, either version 3 of the
//  License, or (at your option) any later version.
//
//  izartu is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
//  GNU Affero General Public License for more details.
//
//  You should have received a copy of the GNU Affero General Public License
//  along with izartu. If not, see <https://www.gnu.org/licenses/>.

// Inline bookmark form, rendered in place of a list row (edit) or on top of
// the list (add). Expects: $formAction, $formBookmark, $formTags, $formErrors,
// plus the list context ($route, $page) for the return field and Cancel link.
$listUrl = $route . ($page > 1 ? '?page=' . $page : '');
?>
          <form method="post" action="<?php echo htmlspecialchars($formAction); ?>" class="bookmark bookmarkform">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(Auth::csrfToken()); ?>">
            <input type="hidden" name="return" value="<?php echo htmlspecialchars($listUrl); ?>">
<?php foreach ($formErrors as $error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endforeach; ?>
            <div class="bm-head">
              <input class="f-title" type="text" name="title" placeholder="Title" maxlength="255"
                aria-label="Title" value="<?php echo htmlspecialchars($formBookmark->title); ?>" required>
            </div>
            <input class="f-url" type="url" name="link" placeholder="https://" maxlength="2048"
              aria-label="URL" value="<?php echo htmlspecialchars($formBookmark->hlink); ?>" required>
            <textarea class="f-desc" name="description" rows="2" maxlength="1024" aria-label="Description"
              placeholder="Description (optional)"><?php echo htmlspecialchars($formBookmark->text); ?></textarea>
            <div class="form-foot">
              <input class="f-tags" type="text" name="tags" placeholder="tags, comma, separated" maxlength="255"
                aria-label="Tags" value="<?php echo htmlspecialchars($formTags); ?>">
              <span class="form-actions">
                <label class="form-visibility" title="Visible to anyone on this instance">
                  <input type="checkbox" name="visibility" value="public"
                    <?php echo $formBookmark->visibility === Visibility::Public ? 'checked' : ''; ?>> Public</label>
                <button class="btn-save" type="submit" name="save">Save</button>
                <a class="btn-link" href="<?php echo $listUrl === '' ? '.' : htmlspecialchars($listUrl); ?>">Cancel</a>
<?php if ($formBookmark->id): ?>
                <button class="btn-link btn-danger" type="submit"
                  formaction="delete/<?php echo $formBookmark->id; ?>">Delete</button>
<?php endif; ?>
              </span>
            </div>
          </form>
