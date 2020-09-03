<?php

/**
 * Darwin handler
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

namespace core\lib\os;

/**
 * Class darwin
 *
 * @package core\lib\os
 */
final class darwin
{
    /**
     * OS command
     *
     * @var string
     */
    public $os_cmd = '';

    /**
     * Get hardware hash
     *
     * @return string
     * @throws \Exception
     */
    public function get_hw_hash(): string
    {
        //Execute command
        exec('system_profiler SPHardwareDataType SPMemoryDataType SPPCIDataType', $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $output = array_filter($output);
        $output = array_unique($output);

        $hash = hash('md5', json_encode($output));

        unset($queries, $output, $query, $status);
        return $hash;
    }

    /**
     * Get PHP executable path
     *
     * @return string
     * @throws \Exception
     */
    public function get_php_path(): string
    {
        //Execute command
        exec('lsof -p ' . getmypid() . ' -Fn | awk "NR==5{print}" | sed "s/n\//\//"', $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        //Get path value
        $env = &$output[0];

        unset($output, $status);
        return $env;
    }

    /**
     * Set as background command
     *
     * @return $this
     */
    public function bg(): object
    {
        $this->os_cmd = 'screen ' . $this->os_cmd . ' > /dev/null 2>&1 &';
        return $this;
    }

    /**
     * Set command with ENV values
     *
     * @return $this
     */
    public function env(): object
    {
        $this->os_cmd = 'source /etc/profile && ' . $this->os_cmd;
        return $this;
    }

    /**
     * Set command for proc_* functions
     *
     * @return $this
     */
    public function proc(): object
    {
        return $this;
    }
}