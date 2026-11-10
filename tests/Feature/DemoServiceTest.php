<?php

namespace Viewflex\Zoap\Tests\Feature;

use Viewflex\Zoap\Tests\Support\DemoServiceScenarios;
use Viewflex\Zoap\Tests\TestCase;

/**
 * Runs the Demo service scenarios in-process through the Laravel HTTP kernel.
 */
class DemoServiceTest extends TestCase
{
    use DemoServiceScenarios;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = 'http://localhost/zoap/demo/server';
    }

    public function testConfiguredHeadersAreSent()
    {
        $response = $this->get($this->endpoint . '?wsdl');

        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
