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
?>
      <div class="tag-index-page">
        <div class="filter-head">
<?php if ($tagNames): ?>
          <span class="section-title">Tags of bookmarks tagged
            <?php echo htmlspecialchars(implode(' + ', $tagNames)); ?></span>
          <a class="all" href="tags">all tags</a>
<?php elseif ($userName !== null): ?>
          <span class="section-title">Tags of <?php echo htmlspecialchars($userName); ?>'s bookmarks</span>
          <a class="all" href="tags">all tags</a>
<?php else: ?>
          <span class="section-title">Tags</span>
          <a class="all" href=".">all bookmarks</a>
<?php endif; ?>
        </div>
        <form method="get" action="tags" class="tag-search-page">
<?php if ($tagNames): ?>
          <input type="hidden" name="tag" value="<?php echo htmlspecialchars(implode(',', $tagNames)); ?>">
<?php endif; ?>
<?php if ($userName !== null): ?>
          <input type="hidden" name="user" value="<?php echo htmlspecialchars($userName); ?>">
<?php endif; ?>
          <input type="search" name="q" value="<?php echo htmlspecialchars($q); ?>"
            placeholder="Find a tag" maxlength="255" aria-label="Find a tag">
          <button class="button" type="submit">Search</button>
        </form>
<?php
['tags' => $results, 'pages' => $pages] = (new ShowTag())
    ->tagSearch(Auth::id(), $q, $page, $tagNames, $userName);
?>
<?php if ($results): ?>
        <div class="tag-index">
<?php foreach ($results as $item): ?>
          <a class="chip" href="<?php echo htmlspecialchars($item['url']); ?>"><?php
            echo htmlspecialchars($item['name']); ?> <span class="count"><?php echo $item['count']; ?></span></a>
<?php endforeach; ?>
        </div>
<?php elseif ($q !== ''): ?>
        <p class="empty">No tags match <strong><?php echo htmlspecialchars($q); ?></strong>.</p>
<?php else: ?>
        <p class="empty">No tags yet.</p>
<?php endif; ?>
<?php include PRIVATE_DIR . 'template/pagination.php'; ?>
      </div>
