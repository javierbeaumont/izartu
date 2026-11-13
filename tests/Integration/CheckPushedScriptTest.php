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

final class CheckPushedScriptTest extends ScriptTestCase
{
    private const IMAGE = 'izartu-e2e';

    private const PUSHED = 'ghcr.io/javierbeaumont/izartu@sha256:'
        . 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    private const LAYERS = ['sha256:aaaa', 'sha256:bbbb'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeDocker();
        file_put_contents($this->root . '/layers.json', json_encode(self::LAYERS, JSON_THROW_ON_ERROR));
    }

    public function testPassesAPushedImageWithTheSameLayers(): void
    {
        $this->push([['layers' => self::LAYERS]]);

        $this->assertSame([0, "PUSHED OK\n", ''], $this->check());
    }

    /**
     * @return array<string, array{list<string>, string}>
     */
    public static function otherLayers(): array
    {
        return [
            'another one' => [['sha256:aaaa', 'sha256:cccc'], "2c2\n< sha256:bbbb\n---\n> sha256:cccc\n"],
            'one more' => [[...self::LAYERS, 'sha256:cccc'], "2a3\n> sha256:cccc\n"],
            'in another order' => [['sha256:bbbb', 'sha256:aaaa'], "1d0\n< sha256:aaaa\n2a2\n> sha256:aaaa\n"],
        ];
    }

    /**
     * @param list<string> $layers The pushed image's.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('otherLayers')]
    public function testRefusesAPushedImageWithOtherLayers(array $layers, string $diff): void
    {
        $this->push([['layers' => $layers]]);

        $this->assertSame(
            [1, '', 'PUSHED FAIL: ' . self::PUSHED . ' has other layers than ' . self::IMAGE . "\n" . $diff],
            $this->check(),
        );
    }

    public function testRefusesAPushedIndex(): void
    {
        $this->push([['layers' => self::LAYERS], ['layers' => self::LAYERS]]);

        $this->assertSame([1, '', 'PUSHED FAIL: ' . self::PUSHED . " is not one platform's image\n"], $this->check());
    }

    public function testRefusesALocalImageWithoutLayers(): void
    {
        file_put_contents($this->root . '/layers.json', 'null');
        $this->push([['layers' => self::LAYERS]]);

        $this->assertSame([1, '', 'PUSHED FAIL: ' . self::IMAGE . " has no layers\n"], $this->check());
    }

    public function testForwardsTheErrorOfAMissingLocalImage(): void
    {
        $this->fake('docker', 'echo "Error response from daemon: No such image: $5" >&2; exit 1');

        $this->assertSame([1, '', 'Error response from daemon: No such image: ' . self::IMAGE . "\n"], $this->check());
    }

    public function testForwardsAnErrorOfTheRegistry(): void
    {
        $this->fake('docker', 'case "$1" in image) cat ' . escapeshellarg($this->root . '/layers.json') . ' ;; '
            . '*) echo "ERROR: failed to fetch anonymous token: 403 Forbidden" >&2; exit 1 ;; esac');

        $this->assertSame([1, '', "ERROR: failed to fetch anonymous token: 403 Forbidden\n"], $this->check());
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function wrongArguments(): array
    {
        return [
            'one' => [[self::IMAGE]],
            'three' => [[self::IMAGE, self::PUSHED, 'x']],
            'an empty one' => [['', self::PUSHED]],
        ];
    }

    /**
     * @param list<string> $arguments
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('wrongArguments')]
    public function testPrintsItsUsageOnWrongArguments(array $arguments): void
    {
        [$status, $out, $error] = $this->script([self::docker('check-pushed.sh'), ...$arguments]);

        $this->assertSame([2, ''], [$status, $out]);
        $this->assertStringStartsWith('usage: ' . self::docker('check-pushed.sh') . ' IMAGE PUSHED', $error);
    }

    /**
     * @param list<array{layers: list<string>}> $platforms
     */
    private function push(array $platforms): void
    {
        file_put_contents($this->root . '/image.json', self::image($platforms));
    }

    /**
     * @return array{int, string, string}
     */
    private function check(): array
    {
        return $this->script([self::docker('check-pushed.sh'), self::IMAGE, self::PUSHED]);
    }
}
