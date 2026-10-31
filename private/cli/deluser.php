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
 * CLI: delete a user, with their bookmarks.
 *
 * Prompts for the user's email, shows who it is and how many bookmarks go with them, and deletes only once the email is
 * typed again. It also deletes the tags only that user's bookmarks have, and ends every session the user has open. The
 * only owner cannot be deleted.
 *
 * Run it:
 *   docker compose exec app php /var/www/izartu/private/cli/deluser.php   (Docker)
 *   php private/cli/deluser.php                                           (bare PHP, as root or the web server's user)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

require dirname(__DIR__) . '/bootstrap.php';

$email = strtolower(Cli::prompt('Email: '));

try {
    $pdo = Cli::connect();

    $find = $pdo->prepare(
        <<<'SQL'
        SELECT
            `id`, `username`, `role`,
            (SELECT COUNT(*) FROM `bookmark` WHERE `bookmark`.`user` = `user`.`id`) AS `bookmarks`,
            (SELECT COUNT(*) FROM `user` WHERE `role` = 'owner') AS `owners`
        FROM `user`
        WHERE
            `email` = :email
        SQL,
    );
    $find->execute([':email' => $email]);
    /** @var array{id: int, username: string, role: string, bookmarks: int, owners: int}|false $user */
    $user = $find->fetch(PDO::FETCH_ASSOC);

    if ($user === false) {
        fwrite(STDERR, "No user has the email {$email}.\n");
        exit(1);
    }

    $refusal = "{$email} is the only owner, and cannot be deleted.\n";
    if ($user['role'] === 'owner' && $user['owners'] === 1) {
        fwrite(STDERR, $refusal);
        exit(1);
    }

    fwrite(STDOUT, "{$user['username']} ({$user['role']}) has " . Cli::plural($user['bookmarks'], 'bookmark')
        . ", deleted with the user.\n");
    if (strtolower(Cli::prompt('Type the email again to delete the user: ')) !== $email) {
        fwrite(STDERR, "The emails differ: nothing was deleted.\n");
        exit(1);
    }

    $pdo->beginTransaction();

    // Counted again, and locked until the commit, so two concurrent deletions cannot leave no owner.
    $owners = $pdo->prepare(
        <<<'SQL'
        SELECT
            COUNT(*)
        FROM `user`
        WHERE
            `role` = 'owner'
        FOR UPDATE
        SQL,
    );
    $owners->execute();
    if ($user['role'] === 'owner' && $owners->fetchColumn() === 1) {
        fwrite(STDERR, $refusal);
        exit(1);
    }

    try {
        $ended = Auth::endSessions($user['id']);
    } catch (RuntimeException $e) {
        fwrite(STDERR, "The user's open sessions could not be ended, so nothing was deleted: " . $e->getMessage()
            . " Run this again with the web server's session.save_path,"
            . " as a user who can list and delete its files.\n");
        exit(1);
    }

    $userTags = $pdo->prepare(
        <<<'SQL'
        SELECT DISTINCT
            `tag`.`name`
        FROM `tag`
        JOIN
            `bookmark_tag` ON (`bookmark_tag`.`tag` = `tag`.`id`)
        JOIN
            `bookmark` ON (`bookmark`.`id` = `bookmark_tag`.`bookmark`)
        WHERE
            `bookmark`.`user` = :user
        SQL,
    );
    $userTags->execute([':user' => $user['id']]);
    /** @var list<string> $names */
    $names = $userTags->fetchAll(PDO::FETCH_COLUMN);

    // The user's tags, locked through the `name` index and in its order, as saving a bookmark locks its tags: a save
    // that has already locked one links it first, and the deletion below finds the link.
    $ids = [];
    if ($names) {
        $lock = $pdo->prepare(
            sprintf(
                <<<'SQL'
                SELECT
                    `id`
                FROM `tag` FORCE INDEX (`name`)
                WHERE
                    `name` IN (%s)
                FOR UPDATE
                SQL,
                implode(', ', array_fill(0, count($names), '?')),
            ),
        );
        $lock->execute($names);
        /** @var list<int> $ids */
        $ids = $lock->fetchAll(PDO::FETCH_COLUMN);
    }

    $pdo->prepare(
        <<<'SQL'
        DELETE FROM `user`
        WHERE
            `id` = :id
        SQL,
    )->execute([':id' => $user['id']]);

    $deletedTags = 0;
    if ($ids) {
        $deleteTags = $pdo->prepare(
            sprintf(
                <<<'SQL'
                DELETE FROM `tag`
                WHERE
                    `id` IN (%s)
                    AND NOT EXISTS (SELECT 1 FROM `bookmark_tag` WHERE `bookmark_tag`.`tag` = `tag`.`id`)
                SQL,
                implode(', ', array_fill(0, count($ids), '?')),
            ),
        );
        $deleteTags->execute($ids);
        $deletedTags = $deleteTags->rowCount();
    }

    $pdo->commit();
} catch (PDOException $e) {
    fwrite(STDERR, 'Could not delete the user: ' . $e->getMessage() . "\n");
    exit(1);
}

// Again, for a session opened by a login between the first call to endSessions() and the commit.
try {
    $ended += Auth::endSessions($user['id']);
} catch (RuntimeException $e) {
    fwrite(STDERR, 'The user is deleted, but the sessions opened meanwhile could not be ended: ' . $e->getMessage()
        . "\n");
    exit(1);
}

fwrite(STDOUT, "Deleted {$email} with " . Cli::plural($user['bookmarks'], 'bookmark') . ' and '
    . Cli::plural($deletedTags, 'tag') . ' no other user had, and ended ' . Cli::plural($ended, 'open session')
    . ".\n");
