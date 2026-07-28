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

/**
 * Render the tag cloud.
 */
final class ShowTag extends Crud
{
    use Tag;

    /**
     * Build the tag-cloud text: the most-used tags with their visible-bookmark
     * count, alphabetically.
     *
     * @param int|null $viewer The viewer's user id (`Auth::id()`), or null for
     *   anonymous.
     * @return string|false Comma-separated `name (count)` pairs, or false if there
     *   are no tags.
     */
    final public function tagCloud(?int $viewer = null): string|false
    {
        return $this->links($this->getCloud($viewer));
    }

    /**
     * Build one page of the tag index or search results: the matching tags
     * with their visible-bookmark count, alphabetically. An empty term
     * matches every tag.
     *
     * @param int|null $viewer The viewer's user id (`Auth::id()`), or null for
     *   anonymous.
     * @param string $term The search term (matched as a substring).
     * @param int $page 1-based page number.
     * @return array{tags: string|false, pages: int} The rendered links (false
     *   if nothing matches) and the total number of pages (at least 1).
     */
    final public function tagSearch(?int $viewer, string $term, int $page = 1): array
    {
        return [
            'tags' => $this->links($this->searchTags($viewer, $term, $page)),
            'pages' => max(1, (int) ceil($this->countTags($viewer, $term) / TAGS_PAGE_SIZE)),
        ];
    }

    /**
     * Render tag rows as linked `name (count)` pairs.
     *
     * @param list<array<string, mixed>> $table Tag rows (columns: `name`, `value`).
     * @return string|false The links, or false if there are no rows.
     */
    private function links(array $table): string|false
    {
        $tag = false;
        foreach ($table as $value) {
            $tag .= '<a href="tag/' . rawurlencode($value['name']) . '">'
                . htmlspecialchars($value['name']) . '</a> (' . $value['value'] . '), ';
        }
        return $tag;
    }

}
