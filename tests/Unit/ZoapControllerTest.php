<?php

namespace Viewflex\Zoap\Tests\Unit;

use Illuminate\Http\Request;
use Laminas\Soap\Wsdl\ComplexTypeStrategy\ArrayOfTypeComplex;
use SoapFault;
use Viewflex\Zoap\Tests\Support\InspectableController;
use Viewflex\Zoap\Tests\Support\Soap;
use Viewflex\Zoap\Tests\TestCase;
use Viewflex\Zoap\ZoapController;

class ZoapControllerTest extends TestCase
{
    protected function expectExceptionWithMessage($message, callable $callback)
    {
        try {
            $callback();
            $this->fail('Expected exception: ' . $message);
        } catch (\Exception $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function testInitRejectsUnknownService()
    {
        $this->expectExceptionWithMessage('Please specify a valid service configuration.', function () {
            (new ZoapController())->init('nope');
        });
    }

    public function testInitRejectsInvalidStrategy()
    {
        $this->expectExceptionWithMessage('Please specify a valid complex type strategy.', function () {
            (new ZoapController())->init('bogus_strategy');
        });
    }

    public function testInitAppliesDefaultsForOptionalConfig()
    {
        $controller = new InspectableController();
        $controller->init('minimal');

        $this->assertInstanceOf(ArrayOfTypeComplex::class, $controller->strategy);
        $this->assertSame([], $controller->types);
        $this->assertSame([], $controller->exceptions);
        $this->assertSame([], $controller->options);
        $this->assertSame(['Content-Type' => 'application/xml; charset=utf-8'], $controller->headers);
    }

    public function testInitKeepsConfiguredContentType()
    {
        config(['zoap.services.minimal.headers' => ['Content-Type' => 'text/xml']]);

        $controller = new InspectableController();
        $controller->init('minimal');

        $this->assertSame(['Content-Type' => 'text/xml'], $controller->headers);
    }

    public function testCurrentUrlRootStripsQueryString()
    {
        $this->app->instance('request', Request::create('http://example.com/zoap/demo/server?wsdl'));

        $this->assertSame('http://example.com/zoap/demo/server', ZoapController::currentUrlRoot());
    }

    public function testServerFaultRendersEscapedXml()
    {
        $xml = (string) ZoapController::serverFault(new \Exception('a < b & "c"'));

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertSame('a < b & "c"', Soap::faultString($xml));
        $this->assertSame('SOAP-ENV:Server', Soap::value($xml, '//faultcode'));
    }

    public function testIsFaultDetectsSoap11Fault()
    {
        $xml = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body>'
            . '<SOAP-ENV:Fault><faultcode>SOAP-ENV:Client</faultcode><faultstring>x</faultstring></SOAP-ENV:Fault>'
            . '</SOAP-ENV:Body></SOAP-ENV:Envelope>';

        $this->assertTrue(ZoapController::isFault($xml));
    }

    public function testIsFaultDetectsSoap12Fault()
    {
        $xml = '<env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope"><env:Body>'
            . '<env:Fault><env:Code><env:Value>env:Sender</env:Value></env:Code></env:Fault>'
            . '</env:Body></env:Envelope>';

        $this->assertTrue(ZoapController::isFault($xml));
    }

    public function testIsFaultIgnoresNonFaults()
    {
        $response = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body>'
            . '<getProductResponse><name>Fault Line Jacket</name></getProductResponse>'
            . '</SOAP-ENV:Body></SOAP-ENV:Envelope>';

        $this->assertFalse(ZoapController::isFault($response));
        $this->assertFalse(ZoapController::isFault('Fault: not xml'));
        $this->assertFalse(ZoapController::isFault(''));
        $this->assertFalse(ZoapController::isFault(null));
        $this->assertFalse(ZoapController::isFault(new SoapFault('Server', 'x')));
    }
}
