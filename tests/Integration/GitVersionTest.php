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

use PHPUnit\Framework\TestCase;

/**
 * Version read from real repositories, built by git in a scratch directory, so the files are the ones git writes.
 */
final class GitVersionTest extends TestCase
{
    private string $root;

    private string $repo;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/izartu-version-' . bin2hex(random_bytes(4));
        $this->repo = $this->root . '/repo';
        mkdir($this->repo, 0700, true);

        $this->git($this->repo, 'init', '-q', '-b', 'main');
        // The project's own stamp and attributes, so an archive here is shaped like a real release download.
        copy(dirname(__DIR__, 2) . '/VERSION', $this->repo . '/VERSION');
        copy(dirname(__DIR__, 2) . '/.gitattributes', $this->repo . '/.gitattributes');
        $this->git($this->repo, 'add', '.');
        $this->git($this->repo, 'commit', '-q', '-m', 'first');
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function testALooseRefNamesItsCommit(): void
    {
        $this->assertSame($this->head($this->repo), Version::fromGit($this->repo));
    }

    public function testAPackedRefNamesItsCommit(): void
    {
        $this->git($this->repo, 'pack-refs', '--all');

        $this->assertFileDoesNotExist($this->repo . '/.git/refs/heads/main', 'the ref is only in packed-refs now');
        $this->assertSame($this->head($this->repo), Version::fromGit($this->repo));
    }

    public function testADetachedHeadNamesItsCommit(): void
    {
        $first = $this->head($this->repo);
        $this->git($this->repo, 'commit', '-q', '--allow-empty', '-m', 'second');
        $this->git($this->repo, 'checkout', '-q', '--detach', 'HEAD~1');

        $this->assertSame($first, Version::fromGit($this->repo));
    }

    public function testAWorktreeOnABranchReadsTheRefsItShares(): void
    {
        $tree = $this->root . '/tree';
        $this->git($this->repo, 'worktree', 'add', '-q', '-b', 'feature', $tree);
        $this->git($tree, 'commit', '-q', '--allow-empty', '-m', 'on the branch');

        $this->assertSame($this->head($tree), Version::fromGit($tree));
        $this->assertNotSame($this->head($this->repo), Version::fromGit($tree));
    }

    public function testAnUnexportedCheckoutFallsBackToItsGitDirectory(): void
    {
        $this->assertSame($this->head($this->repo), Version::running($this->repo));
    }

    public function testWithoutAStampOrGitTheVersionIsUnknown(): void
    {
        mkdir($this->root . '/empty');

        $this->assertSame('unknown', Version::fromGit($this->root . '/empty'));
        $this->assertSame('unknown', Version::running($this->root . '/empty'));
    }

    public function testAnArchiveOfATagNamesTheTag(): void
    {
        $this->git($this->repo, 'tag', '-a', 'v0.1', '-m', 'v0.1');

        $this->assertSame('v0.1', Version::running($this->export('v0.1')));
    }

    public function testAnArchivePastATagNamesTheCommitPastIt(): void
    {
        $this->git($this->repo, 'tag', '-a', 'v0.1', '-m', 'v0.1');
        $this->git($this->repo, 'commit', '-q', '--allow-empty', '-m', 'second');

        $this->assertMatchesRegularExpression('/^v0\.1-1-g[0-9a-f]{7,}$/', Version::running($this->export('HEAD')));
    }

    public function testAnArchiveWithNoTagNamesItsCommit(): void
    {
        $this->assertSame($this->head($this->repo), Version::running($this->export('HEAD')));
    }

    public function testAnEmptyRepositoryIsUnknown(): void
    {
        $this->git($this->root, 'init', '-q', '-b', 'main', 'empty');

        // HEAD names a branch with no commit yet.
        $this->assertSame('unknown', Version::fromGit($this->root . '/empty'));
    }

    /**
     * `pack-refs` leaves the value of the moment in `packed-refs`; a later commit writes the branch as a loose ref
     * again, and only that one is current.
     */
    public function testALooseRefWinsOverItsStalePackedCopy(): void
    {
        $this->git($this->repo, 'pack-refs', '--all');
        $this->git($this->repo, 'commit', '-q', '--allow-empty', '-m', 'second');

        $this->assertSame($this->head($this->repo), Version::fromGit($this->repo));
    }

    /**
     * Regression: a corrupt loose ref fell back to the packed copy, which may name an older commit, so the footer
     * linked to the wrong source.
     */
    public function testACorruptLooseRefIsUnknownNotItsPackedCopy(): void
    {
        $this->git($this->repo, 'pack-refs', '--all');
        file_put_contents($this->repo . '/.git/refs/heads/main', "not a hash\n");

        $this->assertSame('unknown', Version::fromGit($this->repo));
    }

    public function testAWorktreeFindsItsBranchAmongThePackedRefs(): void
    {
        $tree = $this->root . '/tree';
        $this->git($this->repo, 'worktree', 'add', '-q', '-b', 'feature', $tree);
        $this->git($this->repo, 'pack-refs', '--all');

        $this->assertFileDoesNotExist($this->repo . '/.git/refs/heads/feature');
        $this->assertSame($this->head($tree), Version::fromGit($tree));
    }

    public function testADetachedWorktreeNamesItsCommit(): void
    {
        $tree = $this->root . '/tree';
        $this->git($this->repo, 'worktree', 'add', '-q', '--detach', $tree);

        $this->assertSame($this->head($tree), Version::fromGit($tree));
    }

    public function testASubmoduleNamesItsOwnCommit(): void
    {
        $source = $this->root . '/source';
        $this->git($this->root, 'init', '-q', '-b', 'main', 'source');
        $this->git($source, 'commit', '-q', '--allow-empty', '-m', 'in the submodule');
        $this->git($this->repo, '-c', 'protocol.file.allow=always', 'submodule', 'add', '-q', $source, 'sub');

        $this->assertFileExists($this->repo . '/sub/.git', 'a submodule checkout has a .git file, not a directory');
        $this->assertSame($this->head($this->repo . '/sub'), Version::fromGit($this->repo . '/sub'));
        $this->assertNotSame($this->head($this->repo), Version::fromGit($this->repo . '/sub'));
    }

    public function testAGitFileThatPointsNowhereIsUnknown(): void
    {
        mkdir($this->root . '/broken');

        file_put_contents($this->root . '/broken/.git', "gitdir: /nonexistent\n");
        $this->assertSame('unknown', Version::fromGit($this->root . '/broken'));

        file_put_contents($this->root . '/broken/.git', "not a gitdir line\n");
        $this->assertSame('unknown', Version::fromGit($this->root . '/broken'));
    }

    /**
     * A download unpacked into a directory that is itself a git checkout: the stamp names what was downloaded.
     */
    public function testTheStampWinsOverGitMetadata(): void
    {
        $downloaded = $this->head($this->repo);
        $out = $this->export('HEAD');
        $this->git($out, 'init', '-q', '-b', 'main');
        $this->git($out, 'commit', '-q', '--allow-empty', '-m', 'unrelated');

        $this->assertSame($downloaded, Version::running($out));
    }

    /**
     * Regression for the two traps of `.gitattributes`: a directory written with a trailing slash never matches, and
     * `*` instead of `/*` also matches every file under `private/` and `public/`. Either one ships a broken download
     * without any error.
     */
    public function testAReleaseArchiveCarriesTheDeployableTreeOnly(): void
    {
        $files = [
            '.github/workflows/tests.yml', 'Dockerfile', 'LICENSE', 'Makefile', 'README.md', 'composer.json',
            'private/class/Kept.php', 'private/migration/0001-baseline.sql', 'public/.htaccess', 'public/index.php',
            'tests/Unit/LeftTest.php',
        ];
        foreach ($files as $file) {
            is_dir(dirname($this->repo . '/' . $file)) || mkdir(dirname($this->repo . '/' . $file), 0700, true);
            file_put_contents($this->repo . '/' . $file, "x\n");
        }
        $this->git($this->repo, 'add', '.');
        $this->git($this->repo, 'commit', '-q', '-m', 'the project layout');

        $out = $this->export('HEAD');

        foreach (['LICENSE', 'README.md', 'VERSION', 'private/class/Kept.php', 'private/migration/0001-baseline.sql',
            'public/.htaccess', 'public/index.php'] as $kept) {
            $this->assertFileExists($out . '/' . $kept);
        }
        foreach (['.gitattributes', '.github', 'Dockerfile', 'Makefile', 'composer.json', 'tests'] as $left) {
            $this->assertFileDoesNotExist($out . '/' . $left);
        }
    }

    /**
     * Extract `git archive` of a revision, as a release download would arrive: no `.git`, the stamp substituted.
     */
    private function export(string $revision): string
    {
        $out = $this->root . '/export';
        mkdir($out);
        $this->git($this->repo, 'archive', '--format=tar', '-o', $this->root . '/export.tar', $revision);
        $this->command(['tar', '-xf', $this->root . '/export.tar', '-C', $out]);

        $this->assertDirectoryDoesNotExist($out . '/.git');

        return $out;
    }

    /**
     * The abbreviated commit a checkout is on, as git reports it.
     */
    private function head(string $dir): string
    {
        return substr($this->git($dir, 'rev-parse', 'HEAD'), 0, 7);
    }

    private function git(string $dir, string ...$args): string
    {
        return $this->command(['git', '-C', $dir, '-c', 'user.name=izartu', '-c', 'user.email=izartu@example.test',
            '-c', 'commit.gpgsign=false', '-c', 'tag.gpgsign=false', ...$args]);
    }

    /**
     * @param list<string> $command The program and its arguments, run with no shell in between.
     */
    private function command(array $command): string
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);

        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);

        $this->assertSame(0, $status, implode(' ', $command) . ': ' . $err);

        return trim($out);
    }
}
