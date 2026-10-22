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
 * The database schema's migrations: SQL files applied in order, each recorded in the `migration` table.
 *
 * A file is named `NNNN-name.sql`, where `NNNN` is its version. Each statement ends with `;` at the end of a line, and
 * lines starting with `--` are comments.
 *
 * The first migration builds the whole schema, the `migration` table included. Every later one is either one DDL
 * statement or DML only: MySQL commits DDL implicitly, so no transaction can cover it, but each DDL statement is
 * atomic; DML runs in one transaction with the migration's record.
 */
final class Migration
{
    /** @var array<int, string> Migration names by version, in ascending order. */
    private array $migrations;

    /**
     * Read a directory's migrations.
     *
     * @param PDO $db A connection to the database to migrate, in exception error mode.
     * @param string $dir The directory of migration files.
     * @throws RuntimeException When the directory cannot be read or holds a misnamed or repeated migration.
     */
    public function __construct(private readonly PDO $db, private readonly string $dir)
    {
        $names = is_dir($dir) ? scandir($dir) : false;
        if ($names === false) {
            throw new RuntimeException('Cannot read the migrations in ' . $dir . '.');
        }

        $this->migrations = self::parse($names);
    }

    /**
     * The migrations among a directory's entries, by version.
     *
     * @param list<string> $names The directory's entries; anything that is not a `.sql` file is ignored.
     * @return array<int, string> Each migration's name (the file name without its number and extension) by version, in
     *   ascending order.
     * @throws RuntimeException When a `.sql` file is not named `NNNN-name.sql` with a version of 0001 or higher, or two
     *   files share a version.
     */
    public static function parse(array $names): array
    {
        $migrations = [];

        foreach ($names as $name) {
            if (!str_ends_with($name, '.sql')) {
                continue;
            }

            $named = preg_match('/^(\d{4})-([a-z0-9]+(?:-[a-z0-9]+)*)\.sql$/', $name, $match) === 1;
            if (!$named || $match[1] === '0000') {
                throw new RuntimeException('Misnamed migration ' . $name . ': expected NNNN-name.sql, from 0001.');
            }

            $version = (int) $match[1];
            if (isset($migrations[$version])) {
                throw new RuntimeException('Two migrations share version ' . $match[1] . '.');
            }

            $migrations[$version] = $match[2];
        }

        ksort($migrations);

        return $migrations;
    }

    /**
     * Split a migration file into its statements.
     *
     * @param string $sql The file's contents.
     * @return list<string> The statements, trimmed and without their final `;`; comment lines and blank statements are
     *   dropped.
     */
    public static function statements(string $sql): array
    {
        $lines = array_filter(
            preg_split('/\r?\n/', $sql) ?: [],
            fn(string $line): bool => !str_starts_with(ltrim($line), '--'),
        );

        $statements = preg_split('/;[ \t]*(?:\n|$)/', implode("\n", $lines)) ?: [];

        return array_values(array_filter(array_map('trim', $statements), fn(string $sql): bool => $sql !== ''));
    }

    /**
     * Whether a migration is DML only, and so can run inside a transaction.
     *
     * @param list<string> $statements The migration's statements.
     * @return bool True when there is at least one statement and every one is an INSERT, UPDATE, DELETE or REPLACE.
     */
    public static function transactional(array $statements): bool
    {
        foreach ($statements as $statement) {
            if (preg_match('/^(INSERT|UPDATE|DELETE|REPLACE)\b/i', $statement) !== 1) {
                return false;
            }
        }

        return $statements !== [];
    }

    /**
     * The database's current version, after checking these migrations can continue from it.
     *
     * @return int The highest recorded version, or 0 when none is recorded (as in an empty database).
     * @throws RuntimeException When the database has tables but no `migration` table, or is at a version newer than the
     *   latest migration.
     */
    public function version(): int
    {
        $query = $this->db->prepare('SHOW TABLES');
        $query->execute();
        $tables = $query->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('migration', $tables, true)) {
            if ($tables !== []) {
                throw new RuntimeException('The database has tables but no migration history: refusing to touch it.');
            }

            return 0;
        }

        $query = $this->db->prepare('SELECT MAX(`version`) FROM `migration`');
        $query->execute();
        $version = (int) $query->fetchColumn();
        $latest = (int) array_key_last($this->migrations);

        if ($version > $latest) {
            throw new RuntimeException(
                'The database is at version ' . $version . ', newer than the latest migration (' . $latest
                . '): refusing to touch it.',
            );
        }

        return $version;
    }

    /**
     * Apply the next migration the database lacks, and record it.
     *
     * @return string|null The migration applied, as `NNNN-name`, or null when the database is up to date.
     * @throws RuntimeException When `version()` refuses the database, the migration is empty or mixes DDL with other
     *   statements, or a statement fails. A failed migration is not recorded.
     */
    public function applyNext(): ?string
    {
        $current = $this->version();

        foreach ($this->migrations as $version => $name) {
            if ($version > $current) {
                $this->apply($version, $name);

                return sprintf('%04d-%s', $version, $name);
            }
        }

        return null;
    }

    /**
     * Run one migration's statements and record it.
     *
     * @param int $version The migration's version.
     * @param string $name The migration's name.
     * @return void
     * @throws RuntimeException When the file is empty or unreadable, mixes DDL with other statements (allowed only in
     *   the first migration), or a statement fails.
     */
    private function apply(int $version, string $name): void
    {
        $label = sprintf('%04d-%s', $version, $name);
        $sql = file_get_contents($this->dir . '/' . $label . '.sql');
        $statements = $sql === false ? [] : self::statements($sql);
        $transactional = self::transactional($statements);

        if ($statements === []) {
            throw new RuntimeException('Migration ' . $label . ' is empty or unreadable.');
        }

        if ($version > 1 && count($statements) > 1 && !$transactional) {
            throw new RuntimeException('Migration ' . $label . ' must be one DDL statement or DML only.');
        }

        if ($transactional) {
            $this->db->beginTransaction();
        }

        try {
            foreach ($statements as $i => $statement) {
                try {
                    $this->db->exec($statement);
                } catch (PDOException $e) {
                    throw new RuntimeException(
                        'Migration ' . $label . ' failed at statement ' . ($i + 1) . ' (' . strtok($statement, "\n")
                        . '): ' . $e->getMessage(),
                        0,
                        $e,
                    );
                }
            }

            $this->db->prepare('INSERT INTO `migration` (`version`, `name`) VALUES (:version, :name)')
                ->execute([':version' => $version, ':name' => $name]);

            if ($transactional) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($transactional && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
}
