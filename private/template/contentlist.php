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
<?php $hlink = htmlspecialchars($bookmark->hlink); ?>
        <div class="bookmark">
          <div class="head">
            <h2><a href="<?php echo $hlink; ?>"><?php echo htmlspecialchars($bookmark->title); ?></a><?php
                if ($bookmark->visibility === Visibility::Private): ?> <span class="private">private</span><?php
                endif; ?></h2>
            <p class="link-page"><a href="<?php echo $hlink; ?>"><?php echo $hlink; ?></a></p>
            <p class="link-info">
              <span class="linker">Added by <a
                  href="user/<?php echo rawurlencode($bookmark->username); ?>"><?php
                  echo htmlspecialchars($bookmark->username); ?></a></span>
              <span class="added">on <?php echo $bookmark->add ; ?>.</span>
              <span class="modified">Last modified: <?php echo $bookmark->mod ; ?></span>
            </p>
          </div>
          <div class="info">
            <p class="text"><?php echo htmlspecialchars($bookmark->text) ; ?></p>
<?php $names = $tagsByBookmark[$bookmark->id] ?? []; ?>
<?php if ($names): ?>
<?php
    $links = [];
    foreach ($names as $name) {
        $links[] = '<a href="tag/' . rawurlencode($name) . '">' . htmlspecialchars($name) . '</a>';
    }
    ?>
            <p class="tag">Tags: <?php echo implode(', ', $links); ?></p>
<?php endif; ?>
<?php if (Auth::canManage($bookmark->user)): ?>
<?php $editUrl = $route . '?' . ($page > 1 ? 'page=' . $page . '&amp;' : '') . 'edit=' . $bookmark->id; ?>
            <div class="manage">
              <a href="<?php echo $editUrl; ?>">Edit</a>
            </div>
<?php endif; ?>
          </div>
        </div>
<?php endif; ?>
