<?php

/**
 * @maintainer Timur Shagiakhmetov <timur.shagiakhmetov@corp.badoo.com>
 */

namespace unit\Badoo\LiveProfiler;

class LoggerTest extends \unit\Badoo\BaseTestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testGetLogMsg()
    {
        $Logger = new \Badoo\LiveProfiler\Logger();
        $log_msg = $this->invokeMethod($Logger, 'getLogMsg', ['error', 'Error msg', ['param' => 1]]);
        self::assertEquals(date('Y-m-d H:i:s'). "\terror\tError msg\t{\"param\":1}\n", $log_msg);
    }

    public function testLog()
    {
        $tmp_log_file = tempnam('/tmp', 'live.profiling');
        $Logger = new \Badoo\LiveProfiler\Logger();
        $Logger->setLogFile($tmp_log_file);
        $Logger->log('error', 'Error msg');

        $log_msg = file_get_contents($tmp_log_file);
        unset($tmp_log_file);

        self::assertEquals(date('Y-m-d H:i:s'). "\terror\tError msg\n", $log_msg);
    }
}
