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
      <div class="body tags">
<?php if ($tagNames): ?>
        <p class="filter">Tags of bookmarks tagged <strong><?php echo htmlspecialchars(implode(' + ', $tagNames)); ?></strong>
          (<a href="tags">all tags</a>)</p>
<?php elseif ($userName !== null): ?>
        <p class="filter">Tags of <strong><?php echo htmlspecialchars($userName); ?></strong>'s bookmarks
          (<a href="tags">all tags</a>)</p>
<?php else: ?>
        <p class="filter">Tags
          (<a href=".">all bookmarks</a>)</p>
<?php endif; ?>
        <form method="get" action="tags" class="tagsearch">
<?php if ($tagNames): ?>
          <input type="hidden" name="tag" value="<?php echo htmlspecialchars(implode(',', $tagNames)); ?>">
<?php endif; ?>
<?php if ($userName !== null): ?>
          <input type="hidden" name="user" value="<?php echo htmlspecialchars($userName); ?>">
<?php endif; ?>
          <input type="search" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Find a tag" maxlength="255">
          <input type="submit" value="Search">
        </form>
<?php ['tags' => $results, 'pages' => $pages] = (new ShowTag())->tagSearch(Auth::id(), $q, $page, $tagNames, $userName); ?>
<?php if ($results): ?>
        <p class="tag"><?php echo $results; ?></p>
<?php elseif ($q !== ''): ?>
        <p class="notag">No tags match <strong><?php echo htmlspecialchars($q); ?></strong>.</p>
<?php endif; ?>
<?php include PRIVATE_DIR . 'template/pagination.php'; ?>
      </div>
