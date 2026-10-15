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
<?php if ($tag): ?>
        <aside class="sidebar">
          <h2 class="section-title"><a href="tags">Tags</a></h2>
<?php if ($tagNames): ?>
          <p class="hint">Add another tag to narrow down</p>
<?php endif; ?>
          <form class="tag-search" method="get" action="tags">
            <input type="search" name="q" placeholder="Find a tag" maxlength="255" aria-label="Find a tag">
          </form>
<?php
// Log scale, so one very popular tag does not flatten every other to the smallest step.
$max = max(array_column($tag, 'count'));
    ?>
          <div class="cloud">
<?php foreach ($tag as $item): ?>
<?php $step = $max > 1 ? 1 + (int) round(4 * log($item['count']) / log($max)) : 1; ?>
            <a class="s<?php echo $step; ?>" href="<?php echo htmlspecialchars($item['url']); ?>"
              title="<?php echo $item['count']; ?> bookmark<?php echo $item['count'] === 1 ? '' : 's'; ?>"><?php
                  echo htmlspecialchars($item['name']); ?></a>
<?php endforeach; ?>
          </div>
        </aside>
<?php endif; ?>
