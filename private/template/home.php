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

$show = new ShowTag();
$tag = $show->tagCloud(Auth::id());

include_once PRIVATE_DIR . 'template/option.php';
?>
      <div class="body">
<?php if ($dashboard): ?>
        <p class="filter">Your bookmarks</p>
<?php elseif ($tagName !== null): ?>
        <p class="filter">Bookmarks tagged <strong><?php echo htmlspecialchars($tagName); ?></strong>
          (<a href=".">all bookmarks</a>)</p>
<?php elseif ($userName !== null): ?>
        <p class="filter">Bookmarks added by <strong><?php echo htmlspecialchars($userName); ?></strong>
          (<a href=".">all bookmarks</a>)</p>
<?php endif; ?>
<?php
['bookmarks' => $bookmarks, 'pages' => $pages] = (new Bookmark())->orderByDate(Auth::id(), $page, $tagName, $userName);
?>
<?php if ($adding || $bookmarks): ?>
        <div id="list">
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
        </div>
<?php endif; ?>
<?php include PRIVATE_DIR . 'template/pagination.php'; ?>
      </div>
