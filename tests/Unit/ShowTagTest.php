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

final class ShowTagTest extends TestCase
{
    public function testEachRowBecomesItsNameCountAndUrl(): void
    {
        $this->assertSame(
            [['name' => 'php', 'count' => 3, 'url' => 'tag/php'], ['name' => 'sql', 'count' => 1, 'url' => 'tag/sql']],
            ShowTag::rows([['id' => 1, 'name' => 'php', 'value' => 3], ['id' => 2, 'name' => 'sql', 'value' => 1]]),
        );
    }

    public function testTheCountIsAnInteger(): void
    {
        $this->assertSame(3, ShowTag::rows([['id' => 1, 'name' => 'php', 'value' => '3']])[0]['count']);
    }

    public function testTheUrlAddsTheTagToTheActiveFilterInOrder(): void
    {
        $rows = [['id' => 1, 'name' => 'docs', 'value' => 1], ['id' => 2, 'name' => 'sql', 'value' => 1]];

        $urls = array_column(ShowTag::rows($rows, ['php']), 'url');

        $this->assertSame(['tag/docs,php', 'tag/php,sql'], $urls);
    }

    /**
     * @return list<array{string, string}> A tag name and its URL.
     */
    public static function names(): array
    {
        return [
            ['c++', 'tag/c%2B%2B'],
            ['web dev', 'tag/web%20dev'],
            ['ci/cd', 'tag/ci%2Fcd'],
            ['c#', 'tag/c%23'],
            ['why?', 'tag/why%3F'],
            ['r&d', 'tag/r%26d'],
            ['año', 'tag/a%C3%B1o'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('names')]
    public function testANameIsEncodedInItsUrl(string $name, string $url): void
    {
        $this->assertSame($url, ShowTag::rows([['id' => 1, 'name' => $name, 'value' => 1]])[0]['url']);
    }

    public function testTheNameIsLeftForTheTemplateToEscape(): void
    {
        $this->assertSame('<b>&', ShowTag::rows([['id' => 1, 'name' => '<b>&', 'value' => 1]])[0]['name']);
    }

    public function testNoRowsGiveNoTags(): void
    {
        $this->assertSame([], ShowTag::rows([], ['php']));
    }
}
