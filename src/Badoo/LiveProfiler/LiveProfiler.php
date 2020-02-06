<?php

/**
 * @maintainer Timur Shagiakhmetov <timur.shagiakhmetov@corp.badoo.com>
 */

namespace Badoo\LiveProfiler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Psr\Log\LoggerInterface;

class LiveProfiler
{
    CONST MODE_DB = 'db';
    CONST MODE_FILES = 'files';
    CONST MODE_API = 'api';

    /** @var LiveProfiler */
    protected static $instance;
    /** @var string */
    protected $mode = self::MODE_DB;
    /** @var string */
    protected $path = '';
    /** @var string */
    protected $api_key = '';
    /** @var string */
    protected $url = 'http://liveprof.org/api';
    /** @var Connection */
    protected $Conn;
    /** @var LoggerInterface */
    protected $Logger;
    /** @var DataPackerInterface */
    protected $DataPacker;
    /** @var string */
    protected $connection_string;
    /** @var string */
    protected $app;
    /** @var string */
    protected $label;
    /** @var string */
    protected $datetime;
    /** @var int */
    protected $divider = 1000;
    /** @var int */
    protected $total_divider = 10000;
    /** @var callable callback to start profiling */
    protected $start_callback;
    /** @var callable callback to end profiling */
    protected $end_callback;
    /** @var bool */
    protected $is_enabled = false;
    /** @var array */
    protected $last_profile_data = [];

    /**
     * LiveProfiler constructor.
     * @param string $connection_string_or_path
     * @param string $mode
     */
    public function __construct($connection_string_or_path = '', $mode = self::MODE_DB)
    {
        $this->mode = $mode;

        $this->app = 'Default';
        $this->label = $this->getAutoLabel();
        $this->datetime = date('Y-m-d H:i:s');

        $this->detectProfiler();
        $this->Logger = new Logger();
        $this->DataPacker = new DataPacker();

        if ($mode === self::MODE_DB) {
            $this->connection_string = $connection_string_or_path ?: getenv('LIVE_PROFILER_CONNECTION_URL');
        } elseif ($mode === self::MODE_API) {
            if ($connection_string_or_path) {
                $this->url = $connection_string_or_path;
            } elseif (getenv('LIVE_PROFILER_API_URL')) {
                $this->url = getenv('LIVE_PROFILER_API_URL');
            }
        } else {
            $this->setPath($connection_string_or_path ?: getenv('LIVE_PROFILER_PATH'));
        }
    }

    public static function getInstance($connection_string = '', $mode = self::MODE_DB)
    {
        if (self::$instance === null) {
            self::$instance = new static($connection_string, $mode);
        }

        return self::$instance;
    }

    public function start()
    {
        if ($this->is_enabled) {
            return true;
        }

        if (null === $this->start_callback) {
            return true;
        }

        if ($this->needToStart($this->divider)) {
            $this->is_enabled = true;
        } elseif ($this->needToStart($this->total_divider)) {
            $this->is_enabled = true;
            $this->label = 'All';
        }

        if ($this->is_enabled) {
            register_shutdown_function([$this, 'end']);
            call_user_func($this->start_callback);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function end()
    {
        if (!$this->is_enabled) {
            return true;
        }

        $this->is_enabled = false;

        if (null === $this->end_callback) {
            return true;
        }

        $data = call_user_func($this->end_callback);
        if (!is_array($data)) {
            $this->Logger->warning('Invalid profiler data: ' . var_export($data, true));
            return false;
        }

        $this->last_profile_data = $data;
        $result = $this->save($this->app, $this->label, $this->datetime, $data);

        if (!$result) {
            $this->Logger->warning('Can\'t insert profile data');
        }

        return $result;
    }

    public function detectProfiler()
    {
        if (function_exists('xhprof_enable')) {
            return $this->useXhprof();
        }

        if (function_exists('tideways_xhprof_enable')) {
            return $this->useTidyWays();
        }

        if (function_exists('uprofiler_enable')) {
            return $this->useUprofiler();
        }

        return $this->useSimpleProfiler();
    }

    public function useXhprof()
    {
        if ($this->is_enabled) {
            $this->Logger->warning('can\'t change profiler after profiling started');
            return false;
        }

        $this->start_callback = function () {
            xhprof_enable(XHPROF_FLAGS_MEMORY | XHPROF_FLAGS_CPU);
        };

        $this->end_callback = function () {
            return xhprof_disable();
        };

        return true;
    }

    public function useXhprofSample()
    {
        if ($this->is_enabled) {
            $this->Logger->warning('can\'t change profiler after profiling started');
            return false;
        }

        if (!ini_get('xhprof.sampling_interval')) {
            ini_set('xhprof.sampling_interval', 10000);
        }

        if (!ini_get('xhprof.sampling_depth')) {
            ini_set('xhprof.sampling_depth', 200);
        }

        $this->start_callback = function () {
            define('XHPROF_SAMPLING_BEGIN', microtime(true));
            xhprof_sample_enable();
        };

        $this->end_callback = function () {
            return $this->convertSampleDataToCommonFormat(xhprof_sample_disable());
        };

        return true;
    }

    protected function convertSampleDataToCommonFormat(array $sampling_data)
    {
        $result_data = [];
        $prev_time = XHPROF_SAMPLING_BEGIN;
        foreach ($sampling_data as $time => $callstack) {
            $wt = (int)(($time - $prev_time) * 1000000);
            $functions = explode('==>', $callstack);
            $prev_i = 0;
            $main_key = $functions[$prev_i];
            if (!isset($result_data[$main_key])) {
                $result_data[$main_key] = [
                    'ct' => 0,
                    'wt' => 0,
                ];
            }
            $result_data[$main_key]['ct'] ++;
            $result_data[$main_key]['wt'] += $wt;

            $func_cnt = count($functions);
            for ($i = 1; $i < $func_cnt; $i++) {
                $key = $functions[$prev_i] . '==>' . $functions[$i];

                if (!isset($result_data[$key])) {
                    $result_data[$key] = [
                        'ct' => 0,
                        'wt' => 0,
                    ];
                }

                $result_data[$key]['wt'] += $wt;
                $result_data[$key]['ct']++;

                $prev_i = $i;
            }

            $prev_time = $time;
        }

        return $result_data;
    }

    public function useTidyWays()
    {
        if ($this->is_enabled) {
            $this->Logger->warning('can\'t change profiler after profiling started');
            return false;
        }

        $this->start_callback = function () {
            tideways_xhprof_enable(TIDEWAYS_XHPROF_FLAGS_MEMORY | TIDEWAYS_XHPROF_FLAGS_CPU);
        };

        $this->end_callback = function () {
            return tideways_xhprof_disable();
        };

        return true;
    }

    public function useUprofiler()
    {
        if ($this->is_enabled) {
            $this->Logger->warning('can\'t change profiler after profiling started');
            return false;
        }

        $this->start_callback = function () {
            uprofiler_enable(UPROFILER_FLAGS_CPU | UPROFILER_FLAGS_MEMORY);
        };

        $this->end_callback = function () {
            return uprofiler_disable();
        };

        return true;
    }

    public function useSimpleProfiler()
    {
        if ($this->is_enabled) {
            $this->Logger->warning('can\'t change profiler after profiling started');
            return false;
        }

        $this->start_callback = function () {
            \Badoo\LiveProfiler\SimpleProfiler::getInstance()->enable();
        };

        $this->end_callback = function () {
            return \Badoo\LiveProfiler\SimpleProfiler::getInstance()->disable();
        };

        return true;
    }

    /**
     * @return bool
     */
    public function reset()
    {
        if ($this->is_enabled) {
            call_user_func($this->end_callback);
            $this->is_enabled = false;
        }

        return true;
    }

    /**
     * @param string $mode
     * @return $this
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
        return $this;
    }

    /**
     * @return string
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setPath($path)
    {
        if (!is_dir($path)) {
            $this->Logger->error('Directory ' . $path . ' does not exists');
        }

        $this->path = $path;
        return $this;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $api_key
     * @return $this
     */
    public function setApiKey($api_key)
    {
        $this->api_key = $api_key;
        return $this;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->api_key;
    }

    /**
     * @param string $app
     * @return $this
     */
    public function setApp($app)
    {
        $this->app = $app;
        return $this;
    }

    /**
     * @return string
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel($label)
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $datetime
     * @return $this
     */
    public function setDateTime($datetime)
    {
        $this->datetime = $datetime;
        return $this;
    }

    /**
     * @return string
     */
    public function getDateTime()
    {
        return $this->datetime;
    }

    /**
     * @param int $divider
     * @return $this
     */
    public function setDivider($divider)
    {
        $this->divider = $divider;
        return $this;
    }

    /**
     * @param int $total_divider
     * @return $this
     */
    public function setTotalDivider($total_divider)
    {
        $this->total_divider = $total_divider;
        return $this;
    }

    /**
     * @param \Closure $start_callback
     * @return $this
     */
    public function setStartCallback(\Closure $start_callback)
    {
        $this->start_callback = $start_callback;
        return $this;
    }

    /**
     * @param \Closure $end_callback
     * @return $this
     */
    public function setEndCallback(\Closure $end_callback)
    {
        $this->end_callback = $end_callback;
        return $this;
    }

    /**
     * @param LoggerInterface $Logger
     * @return $this
     */
    public function setLogger(LoggerInterface $Logger)
    {
        $this->Logger = $Logger;
        return $this;
    }

    /**
     * @param DataPackerInterface $DataPacker
     * @return $this
     */
    public function setDataPacker($DataPacker)
    {
        $this->DataPacker = $DataPacker;
        return $this;
    }

    /**
     * @return array
     */
    public function getLastProfileData()
    {
        return $this->last_profile_data;
    }

    /**
     * @return Connection
     * @throws DBALException
     */
    protected function getConnection()
    {
        if (null === $this->Conn) {
            $config = new \Doctrine\DBAL\Configuration();
            $connectionParams = ['url' => $this->connection_string];
            $this->Conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);
        }

        return $this->Conn;
    }

    /**
     * @param Connection $Conn
     * @return $this
     */
    public function setConnection(Connection $Conn)
    {
        $this->Conn = $Conn;
        return $this;
    }

    /**
     * @param string $connection_string
     * @return $this
     */
    public function setConnectionString($connection_string)
    {
        $this->connection_string = $connection_string;
        return $this;
    }

    /**
     * @param string $app
     * @param string $label
     * @param string $datetime
     * @param array $data
     * @return bool
     */
    protected function save($app, $label, $datetime, $data)
    {
        if ($this->mode === self::MODE_DB) {
            return $this->saveToDB($app, $label, $datetime, $data);
        }

        if ($this->mode === self::MODE_API) {
            return $this->sendToAPI($app, $label, $datetime, $data);
        }

        return $this->saveToFile($app, $label, $datetime, $data);
    }

    /**
     * @param string $app
     * @param string $label
     * @param string $datetime
     * @param array $data
     * @return bool
     */
    protected function sendToAPI($app, $label, $datetime, $data)
    {
        $data = $this->DataPacker->pack($data);
        $api_key = $this->api_key;
        $curl_handle = curl_init();
        curl_setopt($curl_handle,CURLOPT_URL,$this->url);
        curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_POST, 1);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, http_build_query(compact('api_key', 'app', 'label', 'datetime', 'data')));
        curl_exec($curl_handle);
        $http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
        curl_close($curl_handle);

        return $http_code === 200;
    }

    /**
     * @param string $app
     * @param string $label
     * @param string $datetime
     * @param array $data
     * @return bool
     */
    protected function saveToDB($app, $label, $datetime, $data)
    {
        $packed_data = $this->DataPacker->pack($data);

        try {
            return (bool)$this->getConnection()->insert(
                'details',
                [
                    'app' => $app,
                    'label' => $label,
                    'perfdata' => $packed_data,
                    'timestamp' => $datetime
                ]
            );
        } catch (DBALException $Ex) {
            $this->Logger->error('Error in insertion profile data: ' . $Ex->getMessage());
            return false;
        }
    }

    /**
     * @param string $app
     * @param string $label
     * @param string $datetime
     * @param array $data
     * @return bool
     */
    private function saveToFile($app, $label, $datetime, $data)
    {
        $path = sprintf('%s/%s/%s', $this->path, $app, base64_encode($label));

        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            $this->Logger->error('Directory "'. $path .'" was not created');
            return false;
        }

        $filename = sprintf('%s/%s.json', $path, strtotime($datetime));
        $packed_data = $this->DataPacker->pack($data);
        return (bool)file_put_contents($filename, $packed_data);
    }

    /**
     * @throws DBALException
     */
    public function createTable()
    {
        $driver_name = $this->getConnection()->getDriver()->getName();
        $sql_path = __DIR__ . '/../../../bin/install_data/' . $driver_name . '/source.sql';
        if (!file_exists($sql_path)) {
            $this->Logger->error('Invalid sql path:' . $sql_path);
            return false;
        }

        $sql = file_get_contents($sql_path);

        $this->getConnection()->exec($sql);
        return true;
    }

    /**
     * @return string
     */
    protected function getAutoLabel()
    {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $label = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            return $label ?: $_SERVER['REQUEST_URI'];
        }

        return $_SERVER['SCRIPT_NAME'];
    }

    /**
     * @param int $divider
     * @return bool
     */
    protected function needToStart($divider)
    {
        return mt_rand(1, $divider) === 1;
    }
}
