<?php

namespace Viewflex\Zoap\Tests\Support;

/**
 * Service that fails with a PHP Error when instantiated.
 */
class BrokenService
{
    public function __construct()
    {
        throw new \Error('Secret internal error.');
    }

    /**
     * @return string
     */
    public function hello()
    {
        return 'hello';
    }
}
