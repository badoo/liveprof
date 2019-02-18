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

// Initialize profiler with file mode
$path = '/tmp';
$Profiler = new LiveProfiler($path, LiveProfiler::MODE_FILES);
$Profiler->setDivider(1);

// Run this method before profiled code
$Profiler->start();

// Start of profiled code
testCode(1);
// End of profiled code

// Run this method after profiled code
$profiling_result = $Profiler->end();

echo $profiling_result ? "Profiling successfully finished\n" : "Error in profiling\n";

echo "Profiling data from the file:\n";
$filename = sprintf('%s/%s/%s/%s.json', $path, $Profiler->getApp(), base64_encode($Profiler->getLabel()), strtotime($Profiler->getDateTime()));
echo file_get_contents($filename) . "\n";

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
