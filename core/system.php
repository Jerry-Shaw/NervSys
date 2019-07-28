<?php

/**
 * System script
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
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

namespace core;

//"ROOT" and "ENTRY_SCRIPT" MUST be defined in entry script
if (!defined('ROOT') || !defined('ENTRY_SCRIPT')) {
    exit('Constant "ROOT" and "ENTRY_SCRIPT" MUST be defined in entry script!');
}

//Require PHP version >= 7.2.0
if (version_compare(PHP_VERSION, '7.2.0', '<')) {
    exit('NervSys needs PHP 7.2.0 or higher!');
}

//Define NervSys version
define('SYSVER', '7.3.0');

//Define system root path
define('SYSROOT', substr(strtr(__DIR__, ['/' => DIRECTORY_SEPARATOR, '\\' => DIRECTORY_SEPARATOR]) . DIRECTORY_SEPARATOR, 0, -5));

//Register autoload function
spl_autoload_register(
    static function (string $class): void
    {
        //Load class file without namespace directly from include path
        if (false === strpos($class, '\\')) {
            require $class . '.php';
            return;
        }

        //Get relative path of target class file
        $file = strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';

        //Load class file from SYSROOT or ROOT
        foreach ([SYSROOT, ROOT] as $path) {
            if (is_file($class_file = $path . $file)) {
                require $class_file;
                break;
            }
        }

        unset($class, $file, $path, $class_file);
    }
);

use core\handler\error;
use core\handler\operator;
use core\handler\platform;

use core\parser\cmd;
use core\parser\input;
use core\parser\output;

//Register error handler
register_shutdown_function([error::class, 'shutdown_handler']);
set_exception_handler([error::class, 'exception_handler']);
set_error_handler([error::class, 'error_handler']);

//Config environment
system::load_cfg();
system::init_env();

/**
 * Class system
 *
 * @package core
 */
class system
{
    //Log path
    const LOG_PATH = SYSROOT . 'logs' . DIRECTORY_SEPARATOR;

    //Configuration file
    const CFG_FILE = SYSROOT . 'core' . DIRECTORY_SEPARATOR . 'system.ini';

    //Running stage codes
    const STAGE_INIT  = 1;
    const STAGE_READ  = 2;
    const STAGE_EXEC  = 3;
    const STAGE_FLUSH = 4;

    //Process pool
    public static $logs   = '';
    public static $data   = [];
    public static $error  = [];
    public static $result = [];

    //Runtime values
    public static $cmd    = '';
    public static $mime   = '';
    public static $is_CLI = true;
    public static $is_TLS = true;

    //System settings
    protected static $sys  = [];
    protected static $log  = [];
    protected static $cgi  = [];
    protected static $cli  = [];
    protected static $cors = [];
    protected static $init = [];
    protected static $load = [];
    protected static $path = [];

    //Parsed cmd & params
    protected static $cmd_cgi   = [];
    protected static $cmd_cli   = [];
    protected static $cgi_list  = [];
    protected static $cli_list  = [];
    protected static $param_cgi = [];
    protected static $param_cli = ['argv' => [], 'pipe' => '', 'time' => 0, 'ret' => false];

    //Error reporting level
    protected static $err_lv = E_ALL | E_STRICT;

    /**
     * Load configurations
     */
    public static function load_cfg(): void
    {
        //Parse configuration file
        $conf = parse_ini_file(self::CFG_FILE, true, INI_SCANNER_TYPED);

        //Set include path
        if (!empty($conf['PATH'])) {
            $conf['PATH'] = array_map(
                static function (string $path): string
                {
                    $path = rtrim(strtr($path, ['/' => DIRECTORY_SEPARATOR, '\\' => DIRECTORY_SEPARATOR]), DIRECTORY_SEPARATOR);

                    if (0 !== strpos($path, '/') && 1 !== strpos($path, ':')) {
                        $path = ROOT . $path;
                    }

                    return $path . DIRECTORY_SEPARATOR;
                }, $conf['PATH']
            );

            set_include_path(implode(PATH_SEPARATOR, $conf['PATH']));
        }

        //Set setting values
        foreach ($conf as $key => $val) {
            $key = strtolower($key);

            if (isset(self::$$key)) {
                self::$$key = $val;
            }
        }

        //Refill app_path
        if ('' !== self::$sys['app_path']) {
            self::$sys['app_path'] = trim(self::$sys['app_path'], '\\/') . '/';
        }

        unset($conf, $key, $val);
    }

    /**
     * Initialize environment values
     */
    public static function init_env(): void
    {
        //Set runtime values
        set_time_limit(0);
        ignore_user_abort(true);
        error_reporting(self::$err_lv);
        date_default_timezone_set('' !== self::$sys['timezone'] ? self::$sys['timezone'] : 'UTC');

        //Set running mode
        self::$is_CLI = 'cli' === PHP_SAPI;

        //Set TLS protocol
        self::$is_TLS = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'])
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']);
    }

    /**
     * Boot system
     *
     * @param int $stage
     */
    public static function boot(int $stage = self::STAGE_FLUSH): void
    {
        /**
         * INIT stage (S1)
         * Initialize system
         *
         * Steps:
         * 1. Check Cross-Origin Resource Sharing (CORS) permissions.
         * 2. Execute all configured settings in "init" section of "system.ini".
         */
        self::validate_cors();
        self::initialize_sys();

        //S1 stage abort
        if ($stage === self::STAGE_INIT) {
            return;
        }

        /**
         * READ stage (S2)
         * Read & parse input data
         *
         * Steps:
         * 1. Read and parse input data (REQUEST + JSON + XML).
         * 2. Save parsed data to process pool in non-overwrite mode.
         */
        input::read();

        //S2 stage abort
        if ($stage === self::STAGE_READ) {
            return;
        }

        /**
         * EXEC stage (S3)
         * Execute input commands
         *
         * Steps:
         * 1. Prepare commands. Skip when already set.
         * 2. Execute script functions order by commands via CGI mode.
         * 3. Execute script functions and external commands via CLI mode (available under CLI).
         * 4. Gathering results on calling every function or external command. Save to process result pool.
         */
        '' !== self::$cmd && cmd::prepare();

        operator::exec_cgi();
        operator::exec_cli();

        //S3 stage abort
        if ($stage === self::STAGE_EXEC) {
            return;
        }

        /**
         * FLUSH stage (S4, default)
         * Output results in preset format
         *
         * Steps:
         * 1. Output MIME-Type header.
         * 2. Output formatted result content.
         */
        output::flush();
        unset($stage);
    }

    /**
     * Stop system
     */
    public static function stop(): void
    {
        output::flush();
        exit;
    }

    /**
     * Get client IP
     *
     * @return string
     */
    public static function get_ip(): string
    {
        //Direct request
        if (!isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['REMOTE_ADDR'] ?? '';
        }

        //Forwarded request
        $remote_list = false !== strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ', ')
            ? explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR'])
            : [$_SERVER['HTTP_X_FORWARDED_FOR']];

        //Get valid client IP
        foreach ($remote_list as $ip) {
            if (false === $remote_ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
                continue;
            }

            unset($remote_list, $ip);
            return $remote_ip;
        }

        //IP NOT valid
        return '';
    }

    /**
     * Add CGI job
     *
     * @param string $class
     * @param string ...$method
     */
    public static function add_cgi(string $class, string ...$method): void
    {
        self::$cmd_cgi[] = func_get_args();
        unset($class, $method);
    }

    /**
     * Add CLI job
     *
     * @param string $cmd
     * @param string $argv
     * @param string $pipe
     * @param int    $time
     * @param bool   $ret
     *
     * @throws \Exception
     */
    public static function add_cli(string $cmd, string $argv = '', string $pipe = '', int $time = 0, bool $ret = false): void
    {
        if (!self::$is_CLI) {
            throw new \Exception('Operation NOT permitted!', E_USER_WARNING);
        }

        if ('PHP' === $cmd) {
            self::$cli['PHP'] = platform::php_path();
        }

        if (!isset(self::$cli[$cmd])) {
            throw new \Exception('"' . $cmd . '" NOT defined!', E_USER_WARNING);
        }

        $cmd_cli = [
            'key'  => &$cmd,
            'cmd'  => self::$cli[$cmd],
            'ret'  => &$ret,
            'time' => &$time
        ];

        if ('' !== $pipe) {
            $cmd_cli['pipe'] = $pipe . PHP_EOL;
        }

        if ('' !== $argv) {
            $cmd_cli['argv'] = ' ' . $argv;
        }

        self::$cmd_cli[] = &$cmd_cli;
        unset($cmd, $argv, $pipe, $time, $ret, $cmd_cli);
    }

    /**
     * Validate CORS permissions
     */
    private static function validate_cors(): void
    {
        //Check settings and ENV
        if (empty(self::$cors)
            || !isset($_SERVER['HTTP_ORIGIN'])
            || $_SERVER['HTTP_ORIGIN'] === (self::$is_TLS ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) {
            return;
        }

        //Exit on access NOT permitted
        is_null($allow_headers = self::$cors[$_SERVER['HTTP_ORIGIN']] ?? self::$cors['*'] ?? null) && exit;

        //Response access allowed headers
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: ' . $allow_headers);
        header('Access-Control-Allow-Credentials: true');

        //Exit on OPTION request
        'OPTIONS' === $_SERVER['REQUEST_METHOD'] && exit;
        unset($allow_headers);
    }

    /**
     * Initialize system
     */
    private static function initialize_sys(): void
    {
        if (empty(self::$init)) {
            return;
        }

        $list = [];
        foreach (self::$init as $item) {
            is_array($item) ? array_push($list, ...$item) : $list[] = $item;
        }

        try {
            //Execute dependency
            operator::exec_dep($list);
        } catch (\Throwable $throwable) {
            error::exception_handler(new \Exception($throwable->getMessage(), E_USER_ERROR));
            unset($throwable);
        }

        unset($list, $item);
    }
}