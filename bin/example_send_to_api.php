<?php
/**
 * Runs profiling test code
 * @maintainer Timur Shagiakhmetov <timur.shagiakhmetov@corp.badoo.com>
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../../vendor/autoload.php';
}

use Badoo\LiveProfiler\LiveProfiler;

// Initialize profiler with API mode
$path = '/tmp';
$Profiler = new LiveProfiler($path, LiveProfiler::MODE_FILES);
$Profiler->setMode(LiveProfiler::MODE_API);
$Profiler->setApiKey('70366397-97d6-41be-a83c-e9e649c824e1');
$Profiler->setApp('Test');
$Profiler->setLabel('DockerTest');
$Profiler->setDivider(1);

// Run this method before profiled code
$Profiler->start();

// Start of profiled code
testCode(1);
// End of profiled code

// Run this method after profiled code
$profiling_result = $Profiler->end();

echo $profiling_result ? "Profiling successfully finished\n" : "Error in profiling\n";


/**
 * Test functions
 * @param int $level
 */
function testCode($level = 2)
{
    getSlower($level);
    getFaster($level);
}

/**
 * @param int $level
 * @return float
 */
function getSlower($level)
{
    $result = 0;
    for ($i = 0; $i < $level; $i++) {
        for ($j = 0; $j < 1000000; $j++) {
            $result = $j * (1000000 - $j);
        }
    }
    return $result;
}

/**
 * @param int $level
 * @return float
 */
function getFaster($level)
{
    $result = 0;
    for ($i = 0; $i < (10 - $level); $i++) {
        for ($j = 0; $j < 1000000; $j++) {
            $result = $i * (1000000 - $i);
        }
    }
    return $result;
}
