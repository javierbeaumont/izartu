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
<?php $sep = str_contains($route, '?') ? '&amp;' : '?'; ?>
<?php if ($pages > 1): ?>
        <div class="pagination">
<?php if ($page > 1): ?>
          <a class="previous" href="<?php echo $route . $sep; ?>page=<?php echo $page - 1; ?>">&lsaquo; Previous</a>
<?php endif; ?>
<?php foreach (Bookmark::pageWindow($page, $pages) as $number): ?>
<?php if ($number === null): ?>
          <span class="gap">&hellip;</span>
<?php elseif ($number === $page): ?>
          <strong><?php echo $number; ?></strong>
<?php else: ?>
          <a href="<?php echo $route . $sep; ?>page=<?php echo $number; ?>"><?php echo $number; ?></a>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($page < $pages): ?>
          <a class="next" href="<?php echo $route . $sep; ?>page=<?php echo $page + 1; ?>">Next &rsaquo;</a>
<?php endif; ?>
        </div>
<?php endif; ?>
