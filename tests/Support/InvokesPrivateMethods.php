<?php

namespace HalaeiTests\Support;

trait InvokesPrivateMethods
{
    protected function invokePrivateMethod(object $object, string $method, array $args = [])
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($object, ...$args);
    }
}
