<?php

/**
 * Basic Configurations
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 彼岸花开 <330931138@qq.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2015-2017 Jerry Shaw
 * Copyright 2016-2017 彼岸花开
 * Copyright 2016-2017 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

//Basic Settings
set_time_limit(0);
error_reporting(E_ALL);
ignore_user_abort(true);
date_default_timezone_set('PRC');
header('Content-Type:text/html; charset=utf-8');

//Document Root Definition
define('ROOT', substr(__DIR__, 0, -14));

//Enable/Disable HTTP GET Method
//Helpful for debugging with custom URL parameters
define('ENABLE_GET', false);

//Enable/Disable API Safe Zone
define('SECURE_API', true);

//Enable/Disable Language Module for Error Controlling Module
define('ERROR_LANG', true);

//Define the path containing Encrypt/Decrypt module
define('CRYPT_PATH', 'core');

//Define Online State Tags
define('ONLINE_TAGS', ['uuid', 'char']);

//Define Available languages
define('LANGUAGE_LIST', ['en-US', 'zh-CN']);

//File Storage Server Settings
define('FILE_PATH', '/usr/files/file.oobase.com/');
define('FILE_DOMAIN', 'https://file.oobase.com/');

//CLI Settings
define('CLI_CFG', ROOT . '/_cli/cfg.json');
define('CLI_LOG_PATH', ROOT . '/_cli/_log/');
define('CLI_WORKING_PATH', ROOT . '/_cli/_temp/');
define('CLI_DEBUG_MODE', 0);//0: No log; 1: Log errors; 2: Log details
define('CLI_RUN_OPTION', ['cmd:', 'map:', 'data:']);//Required options for internal calling

//MySQL Settings
define('MySQL_HOST', '127.0.0.1');
define('MySQL_PORT', 3306);
define('MySQL_DB', 'DB_NAME');
define('MySQL_USER', 'root');
define('MySQL_PWD', '');
define('MySQL_CHARSET', 'utf8mb4');
define('MySQL_PERSISTENT', true);

//Redis Settings
define('Redis_HOST', '127.0.0.1');
define('Redis_PORT', 6379);
define('Redis_DB', 0);
define('Redis_AUTH', '');
define('Redis_PERSISTENT', true);
define('Redis_SESSION', true);

//SMTP Mail Settings
define('SMTP_HOST', 'SMTP_HOST');
define('SMTP_PORT', 465);
define('SMTP_USER', 'SMTP_USER');
define('SMTP_PWD', 'SMTP_PWD');
define('SMTP_SENDER', 'SMTP_SENDER');

//Load basic function script
require __DIR__ . '/cfg_fn.php';