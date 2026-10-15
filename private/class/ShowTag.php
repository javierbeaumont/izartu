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
 * Supply the tag cloud and the tag index to the view, as data for the template to render.
 */
final class ShowTag extends Crud
{
    use Tag;

    /**
     * The tag cloud for the current list: its most-used tags with their counts,
     * alphabetically. With `$tags` (the list's active filter), each URL adds its tag to
     * that filter.
     *
     * @param int|null $viewer The viewer's user id (`Auth::id()`), or null for
     *   anonymous.
     * @param list<string> $tags The tag names the list is filtered by; empty for none.
     * @param string|null $username The username the list is filtered by, or null.
     * @return list<array{name: string, count: int, url: string}> One entry per
     *   tag.
     */
    final public function tagCloud(?int $viewer = null, array $tags = [], ?string $username = null): array
    {
        return self::rows($this->getCloud($viewer, $tags, $username), $tags);
    }

    /**
     * Build one page of the tag index or search results: the matching tags
     * with their visible-bookmark count, alphabetically. An empty term
     * matches every tag. With `$tags`/`$username` the search is scoped to
     * that list, and each URL adds its tag to the filter.
     *
     * @param int|null $viewer The viewer's user id (`Auth::id()`), or null for
     *   anonymous.
     * @param string $term The search term (matched as a substring).
     * @param int $page 1-based page number.
     * @param list<string> $tags The tag names the list is filtered by; empty for none.
     * @param string|null $username The username the list is filtered by, or null.
     * @return array{tags: list<array{name: string, count: int, url: string}>, pages: int}
     *   The matching tags and the number of pages (at least 1).
     */
    final public function tagSearch(
        ?int $viewer,
        string $term,
        int $page = 1,
        array $tags = [],
        ?string $username = null,
    ): array {
        return [
            'tags' => self::rows($this->searchTags($viewer, $term, $page, $tags, $username), $tags),
            'pages' => max(1, (int) ceil($this->countTags($viewer, $term, $tags, $username) / TAGS_PAGE_SIZE)),
        ];
    }

    /**
     * Tag rows as name, count and URL.
     *
     * The names in a URL are sorted, so one combination of tags has one address.
     *
     * @param list<array{id: int, name: string, value: int}> $table Tag rows.
     * @param list<string> $base Tag names each URL keeps (the active filter).
     * @return list<array{name: string, count: int, url: string}> One entry per row.
     */
    public static function rows(array $table, array $base = []): array
    {
        $tags = [];
        foreach ($table as $value) {
            $names = array_merge($base, [$value['name']]);
            sort($names);
            $tags[] = [
                'name' => $value['name'],
                'count' => (int) $value['value'],
                'url' => 'tag/' . implode(',', array_map('rawurlencode', $names)),
            ];
        }

        return $tags;
    }

}
