<?php

namespace Viewflex\Zoap\Tests\Support;

/**
 * Service whose methods throw exceptions, registered or not.
 */
class FaultyService
{
    /**
     * Throws an exception registered as a fault exception.
     *
     * @return string
     */
    public function registered()
    {
        throw new \InvalidArgumentException('Registered <failure> & details.');
    }

    /**
     * Throws an exception not registered as a fault exception.
     *
     * @return string
     */
    public function unregistered()
    {
        throw new \RuntimeException('Secret internal details.');
    }
}
