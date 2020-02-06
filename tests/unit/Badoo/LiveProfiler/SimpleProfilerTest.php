<?php

/**
 * @maintainer Timur Shagiakhmetov <timur.shagiakhmetov@corp.badoo.com>
 */

namespace unit\Badoo\LiveProfiler;

class SimpleProfilerTest extends \unit\Badoo\BaseTestCase
{
    public function testRunWithoutTags()
    {
        \Badoo\LiveProfiler\SimpleProfiler::getInstance()->enable();
        $data = \Badoo\LiveProfiler\SimpleProfiler::getInstance()->disable();

        self::assertEquals(['main()'], array_keys($data));
        self::assertEquals(['wt', 'mu', 'ct'], array_keys($data['main()']));
    }

    public function testRunWithTag()
    {
        \Badoo\LiveProfiler\SimpleProfiler::getInstance()->enable();

        \Badoo\LiveProfiler\SimpleProfiler::getInstance()->startTimer('tag');
        \Badoo\LiveProfiler\SimpleProfiler::getInstance()->endTimer('tag');

        $data = \Badoo\LiveProfiler\SimpleProfiler::getInstance()->disable();

        self::assertEquals(['main()', 'main()==>tag'], array_keys($data));
    }

    public function testNotClosedTag()
    {
        \Badoo\LiveProfiler\SimpleProfiler::getInstance()->enable();

        \Badoo\LiveProfiler\SimpleProfiler::getInstance()->startTimer('tag');

        $data = \Badoo\LiveProfiler\SimpleProfiler::getInstance()->disable();

        self::assertEquals([], $data);
    }

    public function testInvalidClosedTag()
    {
        \Badoo\LiveProfiler\SimpleProfiler::getInstance()->enable();

        \Badoo\LiveProfiler\SimpleProfiler::getInstance()->startTimer('tag');
        \Badoo\LiveProfiler\SimpleProfiler::getInstance()->endTimer('invalid');

        $data = \Badoo\LiveProfiler\SimpleProfiler::getInstance()->disable();

        self::assertEquals([], $data);
    }
}
