<?php

#  Izartu
#
#  Copyright © 2011 Javier Beaumont <contact@javierbeaumont.org>
#
#  This file is part of Izartu.
#
#  Izartu is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as
#  published by the Free Software Foundation, either version 3 of the
#  License, or (at your option) any later version.
#
#  Izartu is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
#  GNU Affero General Public License for more details.
#
#  You should have received a copy of the GNU Affero General Public License
#  along with Izartu. If not, see <http://www.gnu.org/licenses/>.

$template = new Template;
$theme = new Theme;

?>
<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php //echo $i18n->getLang(); ?>" lang="<?php //echo $i18n->getLang(); ?>">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title><?php echo $template->getTitle(); ?></title>
    <?php echo $theme->getFavicon(); ?>
    <?php echo $theme->getStyle(); ?>
  </head>
  <body>
    <div id="header">
      <p><a href="admin">Login</a></p>
      <h1><a href="<?php echo ''; ?>"><?php echo $template->getHeader(); ?></a></h1>
    </div>
    <div id="content">
      <?php echo $template->getOption(); ?>
      <div class="body">
        <?php echo $template->getAdvice(); ?>
        <?php echo $template->getContent(); ?>
      </div>
    </div>
    <div id="footer">
      <p class="power">Powered by <a href="http://javierbeaumont.github.com/Izartu/">Izartu</a></p>
      <p class="source">Get the source code <a href="http://github.com/javierbeaumont/Izartu">on GitHub</a> or <a href="http://gitorious.org/izartu/izartu">on Gitorious</a></p>
      <p class="standard"><a href="http://validator.w3.org/check?uri=referer">XHTML 1.1</a> · <a href="http://jigsaw.w3.org/css-validator/check/referer">CSS 2.1</a></p>
    </div>
    <?php echo $template->getDebug(); ?>
  </body>
</html>