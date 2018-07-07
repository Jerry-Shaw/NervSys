<?php

/**
 * ZIP extension
 *
 * Copyright 2018 SealingP <464485940@qq.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace ext;

use \ext\lib\fileOperate;

class zip extends fileOperate
{
    //ZipArchive object
    private static $_zip;
    //zip file real path
    private static $_zipFile;
    //static init object (self)
    private static $_instance;

    //object init(self)
    public static function instance(string $file): zip
    {
        if (!self::$_instance instanceof zip) {
            self::$_zip      = new \ZipArchive();
            self::$_instance = new self;
        }
        self::setZip($file);
        return self::$_instance;
    }

    /**
     * set zip path
     *
     * @param string $file : zip file
     *
     * @return void
     */
    public static function setZip(string $file): void
    {
        if ('zip' != \ext\file::get_ext($file)) return;
        fclose(@fopen($file, 'a+'));
        $file = realpath($file);
        parent::clean($file);
        self::$_zipFile = $file;
    }

    //check init
    private static function check(): bool
    {
        return self::$_zipFile && self::$_zip;
    }

    //get current zip file
    public static function getZip(): string
    {
        return self::$_zipFile ? : '';
    }

    //close ZipArchive resource
    public static function close(): void
    {
        self::check() && @self::$_zip->close();
    }

    /**
     * decompress files(deafult all) from zip (default overwrite same file name)
     *
     * @param string $des   DIR path
     * @param array  $files file name from zip
     */
    public static function decompress(string $des, array $files = []): bool
    {
        if (!self::check() || !is_dir(@realpath($des))) {
            return false;
        }
        self::$_zip->open(self::$_zipFile);
        $des = self::$_zip->extractTo($des, $files ? : null);
        self::close();
        return $des;
    }

    /**
     * dcompress file to zip(overwrite if file existed in zip)
     *
     * @param string $file   file path + name which need compressed
     * @param string $rename if rename file
     *
     * @return  bool
     */
    public static function compress(string $file, string $rename = ''): bool
    {
        if (!self::check() || !($file = @realpath($file))) {
            return false;
        }
        parent::clean($file);
        $rename = $rename ? : substr($file, strrpos($file, '/') + 1, strlen($file));
        self::$_zip->open(self::$_zipFile);
        $file = self::$_zip->addFile($file, $rename);
        self::close();
        return $file;
    }

    //read all files from zip
    public static function listFiles(): array
    {
        if (!self::check()) {
            return [];
        }
        $res   = zip_open(self::$_zipFile);
        $files = [];
        while (true) {
            if (false === ($read = zip_read($res))) {
                break;
            }
            $files[] = zip_entry_name($read);
        }
        zip_close($res);
        unset($res);
        return $files;
    }

    //get ZipArchive object
    public static function getArchive(): \ZipArchive
    {
        return self::check() ? self::$_zip : new \ZipArchive();
    }
}
