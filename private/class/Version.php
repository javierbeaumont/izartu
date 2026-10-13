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

/**
 * What is running, and where its source lives.
 *
 * Read from files, since the runtime image ships no git binary: a release archive's own stamp (written by git on
 * export, see `.gitattributes`), else the commit in `.git`. The parsers take contents, not paths, so they are tested
 * without a repository.
 */
final class Version
{
    /** A full SHA-1 commit hash. */
    private const HASH = '/^[0-9a-f]{40}$/';

    /**
     * The running version, read from the tree.
     *
     * @param string|null $root The installation root; the one holding this file by default.
     * @return string A version as `git describe` writes it, an abbreviated commit hash, or 'unknown'.
     */
    public static function running(?string $root = null): string
    {
        $root ??= dirname(__DIR__, 2);
        $stamp = self::read($root . '/VERSION');

        $describe = self::stampField($stamp, 'describe');
        if ($describe !== '') {
            return $describe;
        }

        $commit = self::stampField($stamp, 'commit');

        return $commit !== '' ? substr($commit, 0, 7) : self::fromGit($root);
    }

    /**
     * The commit a checkout is on, read from its `.git`.
     *
     * Tags are not resolved: the hash already names the exact source, and matching tags would mean peeling annotated
     * ones.
     *
     * @param string $root The checkout root.
     * @return string An abbreviated commit hash, or 'unknown'.
     */
    public static function fromGit(string $root): string
    {
        $dir = $root . '/.git';
        if (is_file($dir)) {
            $dir = self::gitdir(self::read($dir), $root);
        }

        $target = $dir === '' ? '' : self::head(self::read($dir . '/HEAD'));

        if (str_starts_with($target, 'refs/')) {
            // A worktree keeps its own HEAD but shares the refs of the repository it was added from.
            $common = trim(self::read($dir . '/commondir'));
            $refs = $common === '' ? $dir : self::path($common, $dir);

            // A loose ref overrides its packed copy, which keeps the value from when the refs were packed.
            $loose = $refs . '/' . $target;
            $target = is_file($loose)
                ? trim(self::read($loose))
                : self::packedRef(self::read($refs . '/packed-refs'), $target);
        }

        return preg_match(self::HASH, $target) === 1 ? substr($target, 0, 7) : 'unknown';
    }

    /**
     * Where `HEAD` points: the commit itself when detached, else the ref it names.
     *
     * @param string $head The contents of `HEAD`.
     * @return string A full commit hash, a ref such as 'refs/heads/main', or '' when it is neither.
     */
    public static function head(string $head): string
    {
        $head = trim($head);
        if (preg_match(self::HASH, $head) === 1) {
            return $head;
        }

        return preg_match('#^ref: (refs/\S+)$#', $head, $match) === 1 ? $match[1] : '';
    }

    /**
     * The commit a ref points to in `packed-refs`, which lists one `<hash> <ref>` per line.
     *
     * @param string $packed The contents of `packed-refs`.
     * @param string $ref A full ref name, for example 'refs/heads/main'.
     * @return string The full commit hash, or '' when the ref is not listed.
     */
    public static function packedRef(string $packed, string $ref): string
    {
        $line = '/^([0-9a-f]{40}) ' . preg_quote($ref, '/') . '$/m';

        return preg_match($line, $packed, $match) === 1 ? $match[1] : '';
    }

    /**
     * The git directory that the `.git` file of a worktree or a submodule points to.
     *
     * @param string $link The contents of that `.git` file, a `gitdir: <path>` line.
     * @param string $root The checkout root, which a relative path is resolved against.
     * @return string The git directory, or '' when the file names none.
     */
    public static function gitdir(string $link, string $root): string
    {
        return preg_match('/^gitdir:\s*(\S.*)$/m', $link, $match) === 1 ? self::path(rtrim($match[1]), $root) : '';
    }

    /**
     * One field of a `VERSION` stamp.
     *
     * @param string $stamp The contents of `VERSION`.
     * @param string $field The field name, 'describe' or 'commit'.
     * @return string The stamped value, or '' when the field is missing or still holds git's `$Format:...$`
     *   placeholder.
     */
    public static function stampField(string $stamp, string $field): string
    {
        if (preg_match('/^' . preg_quote($field, '/') . ': *(\S.*)$/m', $stamp, $match) !== 1) {
            return '';
        }

        $value = rtrim($match[1]);

        return str_starts_with($value, '$Format:') ? '' : $value;
    }

    /**
     * The repository path holding the running source.
     *
     * The AGPL asks for the source of the running version: a tag links to its release, but a version past a tag
     * (`v0.1-7-gabcdef0`) or without one links to its commit.
     *
     * @param string $version A version string as `VERSION` carries it, or 'unknown'.
     * @return string A path under the repository: 'releases/tag/v0.1', 'commit/abcdef0', or '' (the repository root)
     *   when the version is unknown.
     */
    public static function sourcePath(string $version): string
    {
        // "<tag>-<commits since>-g<hash>": past the tag, so the release is not this code.
        if (preg_match('/-[0-9]+-g([0-9a-f]+)$/', $version, $match) === 1) {
            return 'commit/' . rawurlencode($match[1]);
        }

        // A clone, or a download made with no tag, reports a bare commit hash.
        if (preg_match('/^[0-9a-f]{7,40}$/', $version) === 1) {
            return 'commit/' . rawurlencode($version);
        }

        return $version === '' || $version === 'unknown' ? '' : 'releases/tag/' . rawurlencode($version);
    }

    /**
     * A path as written in git's own files: absolute, or relative to the directory holding that file.
     *
     * @param string $path The path as written.
     * @param string $base The directory a relative path starts from.
     * @return string The absolute path as is, a relative one joined to `$base`.
     */
    private static function path(string $path, string $base): string
    {
        return str_starts_with($path, '/') ? $path : $base . '/' . $path;
    }

    /**
     * A file's contents, or '' when it is not a readable file.
     *
     * @param string $file Path to the file.
     * @return string Its contents, or ''.
     */
    private static function read(string $file): string
    {
        $contents = is_file($file) ? file_get_contents($file) : false;

        return $contents === false ? '' : $contents;
    }
}
