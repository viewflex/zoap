<?php

namespace Viewflex\Zoap\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Viewflex\Zoap\ZoapServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [ZoapServiceProvider::class];
    }

    /**
     * Register additional service configurations used by the tests.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $demo = $app['config']->get('zoap.services.demo');

        $app['config']->set('zoap.services.minimal', [
            'name' => 'Minimal',
            'class' => 'Viewflex\Zoap\Demo\DemoService',
        ]);

        foreach (['AnyType', 'ArrayOfTypeSequence', 'DefaultComplexType'] as $strategy) {
            $app['config']->set('zoap.services.' . strtolower($strategy), array_merge($demo, ['strategy' => $strategy]));
        }

        $app['config']->set('zoap.services.bogus_strategy', array_merge($demo, ['strategy' => 'Bogus']));

        $app['config']->set('zoap.services.faulty', [
            'name' => 'Faulty',
            'class' => 'Viewflex\Zoap\Tests\Support\FaultyService',
            'exceptions' => ['InvalidArgumentException'],
        ]);

        $app['config']->set('zoap.services.broken', [
            'name' => 'Broken',
            'class' => 'Viewflex\Zoap\Tests\Support\BrokenService',
        ]);
    }

    /**
     * Send a request through the application, returning [status, content type, body].
     *
     * @param string $method
     * @param string $url
     * @param string|null $body
     * @return array
     */
    protected function http($method, $url, $body = null)
    {
        $server = ($body !== null) ? ['CONTENT_TYPE' => 'text/xml; charset=utf-8'] : [];
        $response = $this->call($method, $url, [], [], [], $server, $body);

        return [$response->getStatusCode(), (string) $response->headers->get('Content-Type'), $response->getContent()];
    }
}
