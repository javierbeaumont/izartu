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

final class VersionTest extends TestCase
{
    private const A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const C = 'cccccccccccccccccccccccccccccccccccccccc';
    private const D = 'dddddddddddddddddddddddddddddddddddddddd';

    public function testATagLinksToItsRelease(): void
    {
        $this->assertSame('releases/tag/v0.1', Version::sourcePath('v0.1'));
    }

    public function testACommitPastATagLinksToTheCommit(): void
    {
        $this->assertSame('commit/abcdef0', Version::sourcePath('v0.1-7-gabcdef0'));
    }

    public function testABareHashLinksToTheCommit(): void
    {
        $this->assertSame('commit/abcdef0', Version::sourcePath('abcdef0'));
        $this->assertSame('commit/' . self::A, Version::sourcePath(self::A));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function noVersion(): array
    {
        return [['unknown'], ['']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('noVersion')]
    public function testWithoutAVersionTheLinkFallsBackToTheRepository(string $version): void
    {
        $this->assertSame('', Version::sourcePath($version));
    }

    public function testAVersionIsNeverPastedIntoTheUrlUnescaped(): void
    {
        $this->assertSame('releases/tag/v1%20%2F..%2Fevil', Version::sourcePath('v1 /../evil'));
    }

    public function testTheEnvironmentWinsOverTheTree(): void
    {
        // VERSION is a constant, so the precedence is asserted on env() itself.
        putenv('IZARTU_TEST_VERSION=v9.9');
        $this->assertSame('v9.9', env('IZARTU_TEST_VERSION', 'from-the-tree'));

        putenv('IZARTU_TEST_VERSION');
        $this->assertSame('from-the-tree', env('IZARTU_TEST_VERSION', 'from-the-tree'));
    }

    public function testAStampedArchiveGivesItsFields(): void
    {
        $stamp = "# Stamped by git when this tree is exported.\ndescribe: v0.1-2-gabcdef0\ncommit: " . self::A . "\n";

        $this->assertSame('v0.1-2-gabcdef0', Version::stampField($stamp, 'describe'));
        $this->assertSame(self::A, Version::stampField($stamp, 'commit'));
    }

    public function testAnArchiveWithNoTagHasAnEmptyDescribe(): void
    {
        $this->assertSame('', Version::stampField("describe: \ncommit: " . self::A . "\n", 'describe'));
    }

    public function testAnUnexportedStampHoldsOnlyPlaceholders(): void
    {
        $stamp = "describe: \$Format:%(describe:tags=true)\$\ncommit: \$Format:%H\$\n";

        $this->assertSame('', Version::stampField($stamp, 'describe'));
        $this->assertSame('', Version::stampField($stamp, 'commit'));
        $this->assertSame('', Version::stampField($stamp, 'missing'));
    }

    public function testADetachedHeadHoldsTheCommit(): void
    {
        $this->assertSame(self::A, Version::head(self::A . "\n"));
    }

    public function testABranchHeadNamesItsRef(): void
    {
        $this->assertSame('refs/heads/main', Version::head("ref: refs/heads/main\n"));
    }

    public function testAnUnreadableHeadNamesNothing(): void
    {
        $this->assertSame('', Version::head(''));
        $this->assertSame('', Version::head('ref: HEAD'));
        $this->assertSame('', Version::head('abc123'));
    }

    /**
     * As `git pack-refs` writes it: a header, a ref that is a prefix of another, and an annotated tag followed by the
     * commit it peels to.
     */
    public function testAPackedRefIsFoundByItsExactName(): void
    {
        $packed = "# pack-refs with: peeled fully-peeled sorted \n"
            . self::A . " refs/heads/main\n"
            . self::B . " refs/heads/main-old\n"
            . self::C . " refs/tags/v0.1\n"
            . '^' . self::D . "\n";

        $this->assertSame(self::A, Version::packedRef($packed, 'refs/heads/main'));
        $this->assertSame(self::B, Version::packedRef($packed, 'refs/heads/main-old'));
        $this->assertSame(self::C, Version::packedRef($packed, 'refs/tags/v0.1'));
        $this->assertSame('', Version::packedRef($packed, 'refs/heads/missing'));
    }

    public function testAGitdirIsResolvedAgainstTheCheckout(): void
    {
        $this->assertSame('/repo/.git/worktrees/wt', Version::gitdir("gitdir: /repo/.git/worktrees/wt\n", '/wt'));
        $this->assertSame('/sub/../.git/modules/sub', Version::gitdir("gitdir: ../.git/modules/sub\n", '/sub'));
        $this->assertSame('', Version::gitdir('not a gitdir line', '/sub'));
    }

    /**
     * A checkout with `core.autocrlf` (or edited on Windows) ends every line of these files in `\r\n`.
     */
    public function testWindowsLineEndingsAreNotPartOfTheValue(): void
    {
        $this->assertSame('v0.1', Version::stampField("describe: v0.1\r\ncommit: " . self::A . "\r\n", 'describe'));
        $this->assertSame('refs/heads/main', Version::head("ref: refs/heads/main\r\n"));
        $this->assertSame('/repo/.git', Version::gitdir("gitdir: /repo/.git\r\n", '/wt'));
    }

    public function testACommentIsNeverReadAsAField(): void
    {
        $this->assertSame('', Version::stampField("# describe: v9.9\ncommit: \$Format:%H\$\n", 'describe'));
    }

    public function testEmptyFilesNameNothing(): void
    {
        $this->assertSame('', Version::stampField('', 'describe'));
        $this->assertSame('', Version::packedRef('', 'refs/heads/main'));
        $this->assertSame('', Version::gitdir('', '/wt'));
    }

    public function testABranchNameWithSlashesIsKeptWhole(): void
    {
        $this->assertSame('refs/heads/feature/redesign', Version::head("ref: refs/heads/feature/redesign\n"));
    }

    /**
     * A repository in git's SHA-256 object format writes 64-character hashes. Not supported: it reads as nothing (and
     * so 'unknown') rather than as a truncated hash that would link to the wrong commit.
     */
    public function testASha256HashIsNotTakenForACommit(): void
    {
        $this->assertSame('', Version::head(str_repeat('a', 64)));
        $this->assertSame('', Version::packedRef(str_repeat('a', 64) . " refs/heads/main\n", 'refs/heads/main'));
    }

    /**
     * The ref name goes into a pattern: a dot in it must match a dot, not any character.
     */
    public function testARefNameIsMatchedLiterally(): void
    {
        $packed = self::A . " refs/tags/v1x0\n";

        $this->assertSame('', Version::packedRef($packed, 'refs/tags/v1.0'));
        $this->assertSame(self::B, Version::packedRef(self::B . " refs/tags/v1.0\n", 'refs/tags/v1.0'));
    }
}
