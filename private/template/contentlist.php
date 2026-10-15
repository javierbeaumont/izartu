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
<?php if ($editId === $bookmark->id && Auth::canManage($bookmark->user)): ?>
<?php
    $formAction = 'edit/' . $bookmark->id;
    if (!isset($formBookmark) || $formBookmark->id !== $bookmark->id) {
        $formBookmark = $bookmark;
        $formTags = implode(', ', $tagsByBookmark[$bookmark->id] ?? []);
        $formErrors = [];
    }
    include PRIVATE_DIR . 'template/bookmarkform.php';
    ?>
<?php else: ?>
<?php
        $hlink = htmlspecialchars($bookmark->hlink);
        // The host only: a full URL can run to dozens of lines.
        $host = parse_url($bookmark->hlink, PHP_URL_HOST) ?: $bookmark->hlink;
        // The wording must match the moment it shows: an edited bookmark gains a clause for the modification.
        $edited = $bookmark->mod !== null && $bookmark->add !== null && $bookmark->mod !== $bookmark->add;
        $when = $bookmark->mod ?? $bookmark->add;
        $stamp = $bookmark->add === null ? '' : 'Added ' . Format::absolute($bookmark->add);
        // Seconds apart, the two moments render the same.
        if ($edited && Format::absolute($bookmark->mod) !== Format::absolute($bookmark->add)) {
            $stamp .= ', modified ' . Format::absolute($bookmark->mod);
        }
        ?>
          <article class="bookmark">
            <div class="bm-head">
              <a class="bm-title" href="<?php echo $hlink; ?>"><?php echo htmlspecialchars($bookmark->title); ?></a>
<?php if ($bookmark->visibility === Visibility::Private): ?>
              <span class="badge" title="Private: only you can see this"><span class="sr-only">Private</span></span>
<?php endif; ?>
            </div>
            <p class="bm-meta">By <a
                href="user/<?php echo rawurlencode($bookmark->username); ?>"><?php
                    echo htmlspecialchars($bookmark->username); ?></a> from <span
                class="host" title="<?php echo $hlink; ?>"><?php
                    echo htmlspecialchars($host); ?></span><?php if ($when !== null): ?><?php
                        echo $edited ? ', edited' : ''; ?> <time datetime="<?php echo Format::iso($when); ?>"
                title="<?php echo htmlspecialchars($stamp); ?>"><?php
                        echo Format::ago($when); ?></time><?php endif; ?>.</p>
<?php if ($bookmark->text !== ''): ?>
            <p class="bm-desc"><?php echo htmlspecialchars($bookmark->text); ?></p>
<?php endif; ?>
            <div class="bm-foot">
<?php $names = $tagsByBookmark[$bookmark->id] ?? []; ?>
              <span class="bm-tags">
<?php foreach ($names as $name): ?>
                <a class="chip<?php echo in_array($name, $tagNames, true) ? ' active' : ''; ?>"
                  href="tag/<?php echo rawurlencode($name); ?>"><?php echo htmlspecialchars($name); ?></a>
<?php endforeach; ?>
              </span>
<?php if (Auth::canManage($bookmark->user)): ?>
<?php $editUrl = $route . '?' . ($page > 1 ? 'page=' . $page . '&amp;' : '') . 'edit=' . $bookmark->id; ?>
              <a class="edit" href="<?php echo $editUrl; ?>">Edit</a>
<?php endif; ?>
            </div>
          </article>
<?php endif; ?>
