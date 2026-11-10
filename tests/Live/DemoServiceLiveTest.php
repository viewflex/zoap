<?php

namespace Viewflex\Zoap\Tests\Live;

use PHPUnit\Framework\TestCase;
use SoapClient;
use SoapFault;
use Viewflex\Zoap\Tests\Support\DemoServiceScenarios;
use Viewflex\Zoap\Tests\Support\Soap;

/**
 * Exercises the Demo service on a running Laravel app over real HTTP.
 *
 * Set ZOAP_LIVE_URL to the app's base URL, e.g.:
 *   ZOAP_LIVE_URL=http://zoap-dev.test vendor/bin/phpunit --testsuite Live
 */
class DemoServiceLiveTest extends TestCase
{
    use DemoServiceScenarios;

    protected function setUp(): void
    {
        $base = getenv('ZOAP_LIVE_URL');

        if (! $base) {
            $this->markTestSkipped('Set ZOAP_LIVE_URL to run live tests.');
        }

        $this->endpoint = rtrim($base, '/') . '/zoap/demo/server';
    }

    protected function http($method, $url, $body = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/xml; charset=utf-8']);
        }

        $content = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        return [$status, $type, (string) $content];
    }

    protected function soapClient()
    {
        return new SoapClient($this->endpoint . '?wsdl', [
            'cache_wsdl' => WSDL_CACHE_NONE,
            'exceptions' => true,
            'connection_timeout' => 20,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Native SoapClient
    |--------------------------------------------------------------------------
    */

    public function testSoapClientGetProduct()
    {
        $result = $this->soapClient()->getProduct(['productId' => 456, 'token' => Soap::TOKEN]);

        $this->assertSame(456, $result->getProductResult->id);
        $this->assertSame('North Face Summit Ski Jacket', $result->getProductResult->name);
    }

    public function testSoapClientGetProducts()
    {
        $result = $this->soapClient()->getProducts([
            'criteria' => [['key' => 'category', 'value' => 'Outerwear']],
            'token' => Soap::TOKEN,
        ]);

        $this->assertCount(2, $result->getProductsResult);
    }

    public function testSoapClientFault()
    {
        try {
            $this->soapClient()->auth(['user' => 'x', 'password' => 'y']);
            $this->fail('Expected SoapFault.');
        } catch (SoapFault $fault) {
            $this->assertSame('Incorrect credentials.', $fault->getMessage());
        }
    }
}
