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

$show = new ShowTag();
$tag = $show->tagCloud(Auth::id(), $tagNames, $userName);

$model = new Bookmark();
['bookmarks' => $bookmarks, 'pages' => $pages] = $model->orderByDate(Auth::id(), $page, $tagNames, $userName);
$tagsByBookmark = $model->tagsFor(array_column($bookmarks, 'id'));
?>
      <div class="feed-layout">
        <section class="feed">
<?php if ($mine): ?>
          <div class="filter-head">
            <span class="section-title">Your bookmarks</span>
            <a class="all" href=".">all bookmarks</a>
          </div>
<?php elseif ($tagNames): ?>
          <div class="filter-head">
            <span class="section-title">Tagged</span>
<?php foreach ($tagNames as $name): ?>
<?php $rest = array_values(array_diff($tagNames, [$name])); ?>
            <span class="chip filter-chip"><?php echo htmlspecialchars($name); ?><a class="x"
              href="<?php echo $rest ? 'tag/' . implode(',', array_map('rawurlencode', $rest)) : '.'; ?>"
              title="Remove this tag" aria-label="Remove this tag">&times;</a></span>
<?php endforeach; ?>
            <a class="all" href=".">all bookmarks</a>
          </div>
<?php elseif ($userName !== null): ?>
          <div class="filter-head">
            <span class="section-title">Added by <?php echo htmlspecialchars($userName); ?></span>
            <a class="all" href=".">all bookmarks</a>
          </div>
<?php else: ?>
          <h2 class="section-title">Latest bookmarks</h2>
<?php endif; ?>
<?php if ($adding): ?>
<?php
    $formAction = 'add';
    if (!isset($formBookmark)) {
        $formBookmark = new Bookmark();
        $formTags = '';
        $formErrors = [];
    }
    include PRIVATE_DIR . 'template/bookmarkform.php';
    ?>
<?php endif; ?>
<?php foreach ($bookmarks as $bookmark): ?>
<?php include PRIVATE_DIR . 'template/contentlist.php'; ?>
<?php endforeach; ?>
<?php if (!$adding && !$bookmarks): ?>
          <p class="empty">No bookmarks here yet.<?php if (Auth::check()): ?>
            <a href="<?php echo 'user/' . rawurlencode(Auth::user()['username']); ?>?add">Add the first one</a>.<?php
          endif; ?></p>
<?php endif; ?>
<?php include PRIVATE_DIR . 'template/pagination.php'; ?>
        </section>
<?php include PRIVATE_DIR . 'template/option.php'; ?>
      </div>
