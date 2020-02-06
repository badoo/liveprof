<?php

/**
 * @maintainer Timur Shagiakhmetov <timur.shagiakhmetov@corp.badoo.com>
 */

namespace Badoo\LiveProfiler;

class SimpleProfiler
{
    /**
     * @var self
     */
    private static $instance;

    private $methods = [];
    /** @var \SplStack */
    private $stack;
    private $is_enabled = false;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function enable()
    {
        $this->is_enabled= true;
        $this->stack = new \SplStack();

        $this->startTimer('main()');
    }

    public function disable()
    {
        $this->endTimer('main()');

        if (!$this->stack->isEmpty()) {
            return [];
        }

        $this->is_enabled = false;

        return $this->methods;
    }

    public function startTimer($tag)
    {
        if (!$this->is_enabled) {
            return false;
        }

        $full_method = $this->getFullMethod($tag);
        $this->stack->push($tag);

        if (!isset($this->methods[$full_method])) {
            $this->methods[$full_method] = [
                'wt' => 0,
                'ct' => 0,
            ];
        }

        $time = (int)(microtime(true) * 1e6);
        $this->methods[$full_method]['wt'] = $time - $this->methods[$full_method]['wt'];

        return true;
    }

    public function endTimer($tag)
    {
        if (!$this->is_enabled) {
            return false;
        }

        if ($this->stack->isEmpty() || $this->stack->top() !== $tag) {
            return false;
        }

        $tag = $this->stack->pop();

        $full_method = $this->getFullMethod($tag);

        $time = (int)(microtime(true) * 1e6);
        $this->methods[$full_method]['ct']++;
        $this->methods[$full_method]['wt'] = $time - $this->methods[$full_method]['wt'];

        return true;
    }

    private function getFullMethod($tag)
    {
        if ($this->stack->isEmpty()) {
            return $tag;
        }

        return $this->stack->top() . '==>' . $tag;
    }
}
