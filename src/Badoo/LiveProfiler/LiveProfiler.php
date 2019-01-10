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
    /** @var LiveProfiler */
    protected static $instance;
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
     * @param string $connection_string
     */
    public function __construct($connection_string = '')
    {
        if ($connection_string) {
            $this->connection_string = $connection_string;
        } else {
            $this->connection_string = getenv('LIVE_PROFILER_CONNECTION_URL');
        }

        $this->app = 'Default';
        $this->label = $this->getAutoLabel();
        $this->datetime = date('Y-m-d H:i:s');


        $this->detectProfiler();
        $this->Logger = new Logger();
        $this->DataPacker = new DataPacker();
    }

    public static function getInstance($connection_string = '')
    {
        if (self::$instance === null) {
            self::$instance = new static($connection_string);
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
        $packed_data = $this->DataPacker->pack($data);

        $result = false;
        try {
            $result = $this->save($this->app, $this->label, $this->datetime, $packed_data);
        } catch (DBALException $Ex) {
            $this->Logger->error('Error in insertion profile data: ' . $Ex->getMessage());
        }

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

        return false;
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
     * @param string $data
     * @return bool
     * @throws DBALException
     */
    protected function save($app, $label, $datetime, $data)
    {
        return (bool)$this->getConnection()->insert(
            'details',
            [
                'app' => $app,
                'label' => $label,
                'perfdata' => $data,
                'timestamp' => $datetime
            ]
        );
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
