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
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <base href="<?php echo htmlspecialchars(BASE); ?>/">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>izartu</title>
    <link rel="apple-touch-icon" href="image/apple-touch-icon.png">
    <link rel="icon" href="image/favicon.png" type="image/png" sizes="48x48">
    <link rel="icon" href="image/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="style/main.css">
  </head>
  <body>
    <div id="header">
<?php if (Auth::check()): ?>
      <p><a href="dashboard">Dashboard</a> <a href="add">Add bookmark</a> <a href="logout">Logout</a></p>
<?php else: ?>
      <p><a href="login">Login</a></p>
<?php endif; ?>
      <h1><a href=".">izartu</a></h1>
    </div>
    <div id="content">
<?php $flash = Flash::pull(); ?>
<?php if ($flash !== null): ?>
      <p class="flash"><?php echo htmlspecialchars($flash); ?></p>
<?php endif; ?>
<?php include $view; ?>
    </div>
    <div id="footer">
      <p class="power">Powered by <a href="https://izartu.org">izartu</a></p>
      <p class="source">Get the source code <a href="https://github.com/javierbeaumont/izartu">on GitHub</a></p>
    </div>
<?php if (DEBUG) {
    include PRIVATE_DIR . 'template/debug.php';
} ?>
  </body>
</html>
