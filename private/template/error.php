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
      <div class="body">
<?php if ($reason === 'unavailable'): ?>
        <h2>Not available</h2>
        <p>The database cannot be reached right now. Try again in a minute.</p>
<?php elseif ($reason === 'upgrading'): ?>
        <h2>Upgrading</h2>
        <p>izartu is being upgraded. Try again in a minute.</p>
<?php else: ?>
        <h2>Something went wrong</h2>
        <p>The page could not be shown. <a href=".">Back to the list</a>.</p>
<?php endif; ?>
<?php if ($cause !== ''): ?>
        <pre><?php echo htmlspecialchars($cause); ?></pre>
<?php endif; ?>
      </div>
