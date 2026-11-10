<?php

namespace Viewflex\Zoap\Tests\Feature;

use Viewflex\Zoap\Tests\Support\Soap;
use Viewflex\Zoap\Tests\TestCase;

/**
 * Server behavior across service configurations.
 */
class ServerTest extends TestCase
{
    protected function endpoint($key)
    {
        return 'http://localhost/zoap/' . $key . '/server';
    }

    protected function callService($key, $operation, array $params = [])
    {
        $endpoint = $this->endpoint($key);

        return $this->http('POST', $endpoint, Soap::envelope($endpoint, $operation, Soap::params($params)));
    }

    protected function assertFault($expectedMessage, array $response)
    {
        list($status, $type, $body) = $response;

        $this->assertSame($expectedMessage, Soap::faultString($body), 'Response is not the expected SOAP fault: ' . $body);
        $this->assertStringStartsWith('application/xml', $type);
        $this->assertSame(500, $status);
    }

    public function testMinimalConfigurationServesWsdl()
    {
        list($status, $type, $body) = $this->http('GET', $this->endpoint('minimal') . '?wsdl');

        $this->assertSame(200, $status);
        $this->assertStringStartsWith('application/xml', $type);
        $this->assertSame(4, Soap::count($body, '//wsdl:portType/wsdl:operation'));
    }

    public function testMinimalConfigurationHandlesRequests()
    {
        list($status, , $body) = $this->callService('minimal', 'ping', ['token' => Soap::TOKEN]);

        $this->assertSame(200, $status);
        $this->assertSame('true', Soap::value($body, '//pingResult'));
    }

    public function testArrayCapableStrategiesGenerateWsdl()
    {
        foreach (['anytype', 'arrayoftypesequence'] as $key) {
            list($status, , $body) = $this->http('GET', $this->endpoint($key) . '?wsdl');

            $this->assertSame(200, $status, $key . ': ' . $body);
            $this->assertSame(4, Soap::count($body, '//wsdl:portType/wsdl:operation'), $key);
        }
    }

    public function testDefaultComplexTypeStrategyRejectsArrayTypes()
    {
        list($status, , $body) = $this->http('GET', $this->endpoint('defaultcomplextype') . '?wsdl');

        $this->assertSame(500, $status);
        $this->assertStringContainsString('Cannot add a complex type', Soap::faultString($body));
    }

    public function testInvalidStrategyFaults()
    {
        $this->assertFault('Please specify a valid complex type strategy.', $this->http('GET', $this->endpoint('bogus_strategy') . '?wsdl'));
    }

    public function testRegisteredExceptionMessageIsReturnedEscaped()
    {
        $this->assertFault('Registered <failure> & details.', $this->callService('faulty', 'registered'));
    }

    public function testUnregisteredExceptionMessageIsHidden()
    {
        $this->assertFault('Unknown error', $this->callService('faulty', 'unregistered'));
    }

    public function testPhpErrorIsReturnedAsFault()
    {
        $response = $this->callService('broken', 'hello');

        $this->assertFault('Unknown error', $response);
        $this->assertStringNotContainsString('Secret', $response[2]);
    }

    public function testEmptyRequestFaults()
    {
        list($status, $type, $body) = $this->http('POST', $this->endpoint('demo'), '');

        $this->assertNotNull(Soap::faultString($body), 'Response is not a SOAP fault: ' . $body);
        $this->assertStringStartsWith('application/xml', $type);
        $this->assertSame(500, $status);
    }
}
