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

final class AssetTest extends TestCase
{
    public function testAnExistingFileCarriesItsModificationTime(): void
    {
        $this->assertSame('style/main.css?v=' . filemtime(PUBLIC_DIR . 'style/main.css'), asset('style/main.css'));
    }

    public function testAMissingFileGetsThePlainPath(): void
    {
        $this->assertSame('style/missing.css', asset('style/missing.css'));
    }

    public function testAChangedFileGetsANewUrl(): void
    {
        $file = (string) tempnam(PUBLIC_DIR, 'asset-test-');
        $path = basename($file);

        try {
            touch($file, 1000);
            clearstatcache();
            $before = asset($path);

            touch($file, 2000);
            clearstatcache();

            $this->assertSame($path . '?v=1000', $before);
            $this->assertSame($path . '?v=2000', asset($path));
        } finally {
            unlink($file);
        }
    }
}
