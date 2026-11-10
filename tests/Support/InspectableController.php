<?php

namespace Viewflex\Zoap\Tests\Support;

use Viewflex\Zoap\ZoapController;

/**
 * Exposes the controller's initialized service attributes for assertions.
 */
class InspectableController extends ZoapController
{
    public function __get($name)
    {
        return $this->$name;
    }
}
