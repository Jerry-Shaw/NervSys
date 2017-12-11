<?php

/**
 * Redis Session Extension
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
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

namespace ext;

class redis_session extends redis
{
    /**
     * Default settings for Redis Session
     */
    public static $sess_host    = '127.0.0.1';
    public static $sess_port    = 6379;
    public static $sess_auth    = '';
    public static $sess_db      = 0;
    public static $sess_prefix  = 'sess';
    public static $sess_timeout = 10;
    public static $sess_persist = true;

    //SESSION Lifetime (in seconds)
    public static $sess_life = 600;

    //Redis connection
    private static $db_redis;

    //Redis config keys
    const cfg = ['host', 'port', 'auth', 'db', 'prefix', 'timeout', 'persist'];

    /**
     * Backup Redis default config
     *
     * @param array $cfg
     */
    private static function backup_cfg(array &$cfg): void
    {
        foreach (self::cfg as $key) $cfg[$key] = parent::$$key;
        unset($key);
    }

    /**
     * Setup Redis Session config
     */
    private static function setup_cfg(): void
    {
        foreach (self::cfg as $key) parent::$$key = self::${'sess_' . $key};
        unset($key);
    }

    /**
     * Restore Redis default config
     *
     * @param array $cfg
     */
    private static function restore_cfg(array $cfg): void
    {
        foreach ($cfg as $key => $value) parent::$$key = $value;
        unset($cfg, $key, $value);
    }

    /**
     * Initialize SESSION
     */
    public static function start(): void
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            $cfg = [];
            //Backup Redis settings
            self::backup_cfg($cfg);

            //Setup Redis Session settings
            self::setup_cfg();
            //Connect Redis
            self::$db_redis = parent::connect();

            //Restore Redis settings
            self::restore_cfg($cfg);
            unset($cfg);

            //Setup Session GC config
            ini_set('session.gc_divisor', 100);
            ini_set('session.gc_probability', 100);

            //Set Session handler & start Session
            $handler = __CLASS__;
            session_set_save_handler(
                [$handler, 'open'],
                [$handler, 'close'],
                [$handler, 'read'],
                [$handler, 'write'],
                [$handler, 'destroy'],
                [$handler, 'gc']
            );
            session_start();
            unset($handler);
        }
    }

    /**
     * @param string $save_path
     * @param string $session_name
     *
     * @return bool
     */
    public static function open(string $save_path, string $session_name): bool
    {
        unset($save_path, $session_name);
        return true;
    }

    /**
     * @return bool
     */
    public static function close(): bool
    {
        return true;
    }

    /**
     * @param string $session_id
     *
     * @return string
     */
    public static function read(string $session_id): string
    {
        return (string)self::$db_redis->get(self::$prefix . $session_id);
    }

    /**
     * @param string $session_id
     * @param string $session_data
     *
     * @return bool
     */
    public static function write(string $session_id, string $session_data): bool
    {
        $write = (bool)self::$db_redis->set(self::$prefix . $session_id, $session_data, self::$sess_life);
        unset($session_id, $session_data);
        return $write;
    }

    /**
     * @param string $session_id
     *
     * @return bool
     */
    public static function destroy(string $session_id): bool
    {
        self::$db_redis->del(self::$prefix . $session_id);
        unset($session_id);
        return true;
    }

    /**
     * @param int $lifetime
     *
     * @return bool
     */
    public static function gc(int $lifetime): bool
    {
        unset($lifetime);
        return true;
    }
}