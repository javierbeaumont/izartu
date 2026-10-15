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
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <base href="<?php echo htmlspecialchars(BASE); ?>/">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>izartu</title>
    <link rel="apple-touch-icon" href="image/apple-touch-icon.png">
    <link rel="icon" href="image/favicon.png" type="image/png" sizes="48x48">
    <link rel="icon" href="image/favicon.svg" type="image/svg+xml">
    <link rel="preload" href="font/inter.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?php echo asset('style/main.css'); ?>">
  </head>
  <body>
    <header class="site-header">
      <div class="wrap">
        <a class="brand" href=".">izartu</a>
        <nav>
<?php if (Auth::check()): ?>
<?php $own = 'user/' . rawurlencode(Auth::user()['username']); ?>
          <a href="<?php echo $own; ?>">My bookmarks</a>
          <a href="logout">Logout</a>
          <a class="button" href="<?php echo $own; ?>?add">Add bookmark</a>
<?php else: ?>
          <a href="login">Login</a>
<?php endif; ?>
        </nav>
      </div>
    </header>
    <main class="wrap">
<?php $flash = Flash::pull(); ?>
<?php if ($flash !== null): ?>
      <p class="flash"><?php echo htmlspecialchars($flash); ?></p>
<?php endif; ?>
<?php include $view; ?>
    </main>
    <footer class="site-footer">
      <div class="wrap">
        <p>Tagged with <a href="https://izartu.org">izartu</a>
          <a href="https://github.com/javierbeaumont/izartu/<?php
            echo Version::sourcePath(VERSION); ?>"><?php echo htmlspecialchars(VERSION); ?></a></p>
      </div>
    </footer>
<?php if (DEBUG) {
    include PRIVATE_DIR . 'template/debug.php';
} ?>
  </body>
</html>
