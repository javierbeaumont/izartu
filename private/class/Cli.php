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
 * What the command-line scripts in `private/cli/` share: their prompts.
 */
final class Cli
{
    /**
     * Ask a question on the terminal and read the answer.
     *
     * @param string $label The question, printed as is.
     * @return string The answer, without surrounding whitespace.
     */
    public static function prompt(string $label): string
    {
        fwrite(STDOUT, $label);

        return trim((string) fgets(STDIN));
    }

    /**
     * Ask for a password without showing what is typed, when the input is a terminal.
     *
     * @param string $label The question, printed as is.
     * @return string The answer, without surrounding whitespace.
     */
    public static function secret(string $label): string
    {
        // STDIN may hold input read ahead, which stream_isatty() warns is lost; a handle of its own holds none.
        $input = fopen('php://stdin', 'r');
        $terminal = $input !== false && stream_isatty($input);
        if ($input !== false) {
            fclose($input);
        }

        if ($terminal) {
            system('stty -echo');
        }

        try {
            return self::prompt($label);
        } finally {
            if ($terminal) {
                system('stty echo');
                fwrite(STDOUT, "\n");
            }
        }
    }

    /**
     * A count and its noun, in the plural unless the count is 1: `2 sessions`.
     *
     * @param int $count The count.
     * @param string $noun The noun in the singular, whose plural adds an `s`.
     * @return string The count, a space and the noun.
     */
    public static function plural(int $count, string $noun): string
    {
        return $count . ' ' . $noun . ($count === 1 ? '' : 's');
    }
}
