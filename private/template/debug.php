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

$queries = Debug::queries();
$metrics = Debug::metrics();
?>
    <details id="debug">
      <summary><?php
        printf(
            'app %.1f ms, db %.1f ms (%d queries), mem peak %.2f MB',
            $metrics['app'],
            $metrics['db'],
            $metrics['queries'],
            $metrics['mem'],
        );
?></summary>
      <ol>
<?php foreach ($queries as $query): ?>
        <li<?php if ($query['runs'] > 1): ?> class="repeat"<?php endif; ?>>
          <span class="ms"><?php printf('%.1f ms', $query['ms']); ?></span>
<?php if ($query['runs'] > 1): ?>
          <span class="runs">x<?php echo $query['runs']; ?></span>
<?php endif; ?>
          <code><?php echo htmlspecialchars($query['sql']); ?></code>
        </li>
<?php endforeach; ?>
      </ol>
    </details>
