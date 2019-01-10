<?php

/**
 * @maintainer Timur Shagiakhmetov <timur.shagiakhmetov@corp.badoo.com>
 */

namespace unit\Badoo;

class BaseTestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Call protected/private method of a class.
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     * @return mixed Method return.
     * @throws \ReflectionException
     */
    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(\get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * @param $object
     * @param $property
     * @param $value
     * @throws \ReflectionException
     */
    public function setProtectedProperty(&$object, $property, $value)
    {
        $reflection = new \ReflectionClass(\get_class($object));
        $reflection_property = $reflection->getProperty($property);
        $reflection_property->setAccessible(true);
        $reflection_property->setValue($object, $value);
    }

    /**
     * @param $object
     * @param $property
     * @return mixed
     * @throws \ReflectionException
     */
    public function getProtectedProperty(&$object, $property)
    {
        $reflection = new \ReflectionClass(\get_class($object));
        $reflection_property = $reflection->getProperty($property);
        $reflection_property->setAccessible(true);
        return $reflection_property->getValue($object);
    }
}
