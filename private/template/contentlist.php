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
<?php if ($editId === $bookmark->id && Auth::canManage($bookmark->user)): ?>
<?php
$formAction = 'edit/' . $bookmark->id;
if (!isset($formBookmark) || $formBookmark->id !== $bookmark->id) {
    $formBookmark = $bookmark;
    $formTags = implode(', ', $tagsByBookmark[$bookmark->id] ?? []);
    $formErrors = [];
}
include PRIVATE_DIR . 'template/bookmarkform.php';
?>
<?php else: ?>
        <div class="bookmark">
          <div class="head">
            <h2><a href="<?php echo htmlspecialchars($bookmark->hlink) ; ?>"><?php echo htmlspecialchars($bookmark->title) ; ?></a><?php if ($bookmark->visibility === Visibility::Private): ?> <span class="private">private</span><?php endif; ?></h2>
            <p class="link-page"><a href="<?php echo htmlspecialchars($bookmark->hlink) ; ?>"><?php echo htmlspecialchars($bookmark->hlink) ; ?></a></p>
            <p class="link-info">
              <span class="linker">Added by <a href="user/<?php echo rawurlencode($bookmark->username); ?>"><?php echo htmlspecialchars($bookmark->username) ; ?></a></span>
              <span class="added">on <?php echo $bookmark->add ; ?>.</span>
              <span class="modified">Last modified: <?php echo $bookmark->mod ; ?></span>
            </p>
          </div>
          <div class="info">
            <p class="text"><?php echo htmlspecialchars($bookmark->text) ; ?></p>
<?php $names = $tagsByBookmark[$bookmark->id] ?? []; ?>
<?php if ($names): ?>
            <p class="tag">Tags: <?php foreach ($names as $i => $name): ?><?php echo $i ? ', ' : ''; ?><a href="tag/<?php echo rawurlencode($name); ?>"><?php echo htmlspecialchars($name); ?></a><?php endforeach; ?></p>
<?php endif; ?>
<?php if (Auth::canManage($bookmark->user)): ?>
            <div class="manage">
              <a href="<?php echo $route; ?>?<?php echo $page > 1 ? 'page=' . $page . '&amp;' : ''; ?>edit=<?php echo $bookmark->id; ?>">Edit</a>
            </div>
<?php endif; ?>
          </div>
        </div>
<?php endif; ?>
