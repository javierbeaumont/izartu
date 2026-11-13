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

abstract class ScriptTestCase extends TestCase
{
    /**
     * The test's scratch directory, removed after it: its fake commands are in `bin/`.
     */
    protected string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/izartu-script-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/bin', 0700, true);
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

    /**
     * Put a fake command first on the PATH of the scripts the test runs.
     *
     * @param string $name The command's name.
     * @param string $body Its sh code.
     */
    protected function fake(string $name, string $body): void
    {
        file_put_contents($this->root . '/bin/' . $name, "#!/bin/sh\n" . $body . "\n");
        chmod($this->root . '/bin/' . $name, 0755);
    }

    /**
     * Fake docker, answering from the scratch directory: `buildx imagetools inspect` with `image.json`, `image inspect`
     * with `layers.json`.
     */
    protected function fakeDocker(): void
    {
        $root = escapeshellarg($this->root);
        $this->fake('docker', <<<SH
            case "\$1" in
            buildx) cat $root/image.json ;;
            image) cat $root/layers.json ;;
            *) exit 99 ;;
            esac
            SH);
    }

    /**
     * An image's description as `docker buildx imagetools inspect --format '{{json .Image}}'` prints it.
     *
     * @param list<array{layers: list<string>}> $platforms Each platform's layers' diff IDs; more than one make an
     *     index.
     * @return string The JSON.
     */
    protected static function image(array $platforms): string
    {
        $configs = [];
        foreach ($platforms as $i => $platform) {
            $configs['linux/' . ['amd64', 'arm64'][$i]] = [
                'architecture' => ['amd64', 'arm64'][$i],
                'config' => new stdClass(),
                'os' => 'linux',
                'rootfs' => ['type' => 'layers', 'diff_ids' => $platform['layers']],
            ];
        }

        return json_encode(count($configs) === 1 ? reset($configs) : $configs, JSON_THROW_ON_ERROR);
    }

    /**
     * Run a command in the scratch directory, with the fakes first on its PATH.
     *
     * @param list<string> $command The program and its arguments, run with no shell in between.
     * @return array{int, string, string} Its exit status, stdout and stderr.
     */
    protected function script(array $command): array
    {
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->root,
            [...getenv(), 'PATH' => $this->root . '/bin:' . getenv('PATH')],
        );
        $this->assertIsResource($process);
        fclose($pipes[0]);

        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);

        return [proc_close($process), $out, $err];
    }

    /**
     * A script of the repository's `docker/`.
     *
     * @param string $name The script's file name.
     * @return string Its path.
     */
    protected static function docker(string $name): string
    {
        return dirname(__DIR__, 2) . '/docker/' . $name;
    }
}
