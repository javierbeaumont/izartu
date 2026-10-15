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
      <div class="login-page">
        <form method="post" action="login" class="login">
          <h2 class="section-title">Log in</h2>
<?php if (!empty($error)): ?>
          <p class="login-error">Wrong email or password.</p>
<?php endif; ?>
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(Auth::csrfToken()); ?>">
          <div class="field-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" autocomplete="username" required>
          </div>
          <div class="field-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
          </div>
          <button class="button btn-block" type="submit" name="login">Log in</button>
        </form>
      </div>
