<?php

namespace Viewflex\Zoap\Tests\Live;

use PHPUnit\Framework\TestCase;
use SoapClient;
use SoapFault;
use Viewflex\Zoap\Tests\Support\Soap;

/**
 * Exercises the Demo service on a running Laravel app over real HTTP.
 *
 * Set ZOAP_LIVE_URL to the app's base URL, e.g.:
 *   ZOAP_LIVE_URL=http://zoap-dev.test vendor/bin/phpunit --testsuite Live
 */
class DemoServiceLiveTest extends TestCase
{
    /**
     * @var string
     */
    protected $endpoint;

    protected function setUp(): void
    {
        $base = getenv('ZOAP_LIVE_URL');

        if (! $base) {
            $this->markTestSkipped('Set ZOAP_LIVE_URL to run live tests.');
        }

        $this->endpoint = rtrim($base, '/') . '/zoap/demo/server';
    }

    /**
     * Send an HTTP request, returning [status, content type, body].
     *
     * @param string $method
     * @param string $url
     * @param string|null $body
     * @return array
     */
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

    protected function call($operation, array $params, $endpoint = null)
    {
        $endpoint = $endpoint ?: $this->endpoint;

        return $this->http('POST', $endpoint, Soap::envelope($this->endpoint, $operation, Soap::params($params)));
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
    | WSDL
    |--------------------------------------------------------------------------
    */

    public function testWsdlMatchesSnapshot()
    {
        list($status, $type, $body) = $this->http('GET', $this->endpoint . '?wsdl');

        $this->assertSame(200, $status);
        $this->assertStringStartsWith('application/xml', $type);
        $this->assertNotNull(Soap::xpath($body), 'WSDL is not well-formed XML.');
        $this->assertSame(Soap::wsdlFixture($this->endpoint), $body);
    }

    /*
    |--------------------------------------------------------------------------
    | Successful Operations
    |--------------------------------------------------------------------------
    */

    public function testAuthReturnsToken()
    {
        list($status, , $body) = $this->call('auth', ['user' => Soap::USER, 'password' => Soap::PASSWORD]);

        $this->assertSame(200, $status);
        $this->assertSame('true', Soap::value($body, '//authResult/item[key="status"]/value'));
        $this->assertSame(Soap::TOKEN, Soap::value($body, '//authResult/item[key="token"]/value'));
    }

    public function testPingWithValidToken()
    {
        list($status, , $body) = $this->call('ping', ['token' => Soap::TOKEN]);

        $this->assertSame(200, $status);
        $this->assertSame('true', Soap::value($body, '//pingResult'));
    }

    public function testPingWithInvalidToken()
    {
        list($status, , $body) = $this->call('ping', ['token' => 'wrong']);

        $this->assertSame(200, $status);
        $this->assertSame('false', Soap::value($body, '//pingResult'));
    }

    public function testGetProduct()
    {
        list($status, , $body) = $this->call('getProduct', ['productId' => 456, 'token' => Soap::TOKEN]);

        $this->assertSame(200, $status);
        $this->assertSame('456', Soap::value($body, '//getProductResult/id'));
        $this->assertSame('North Face Summit Ski Jacket', Soap::value($body, '//getProductResult/name'));
        $this->assertSame('249.98', Soap::value($body, '//getProductResult/price'));
    }

    public function testGetProducts()
    {
        $criteria = '<oper:keyValue><oper:key>category</oper:key><oper:value>Outerwear</oper:value></oper:keyValue>';
        list($status, , $body) = $this->call('getProducts', ['criteria' => $criteria, 'token' => Soap::TOKEN]);

        $this->assertSame(200, $status);
        $this->assertSame(2, Soap::count($body, '//getProductsResult/*'));
        $this->assertSame('Marmot Crew Neck Base Layer', Soap::value($body, '//getProductsResult/*[2]/name'));
    }

    /*
    |--------------------------------------------------------------------------
    | Faults
    |--------------------------------------------------------------------------
    */

    public function testAuthWithBadCredentialsFaults()
    {
        list($status, $type, $body) = $this->call('auth', ['user' => 'x', 'password' => 'y']);

        $this->assertSame('Incorrect credentials.', Soap::faultString($body));
        $this->assertStringStartsWith('application/xml', $type);
        $this->assertSame(500, $status);
    }

    public function testGetProductWithoutIdFaults()
    {
        list($status, , $body) = $this->call('getProduct', ['productId' => 0, 'token' => Soap::TOKEN]);

        $this->assertSame('Please specify product id.', Soap::faultString($body));
        $this->assertSame(500, $status);
    }

    public function testGetProductsWithBadTokenFaults()
    {
        list($status, , $body) = $this->call('getProducts', ['token' => 'wrong']);

        $this->assertSame('Incorrect credentials.', Soap::faultString($body));
        $this->assertSame(500, $status);
    }

    public function testUnknownServiceFaults()
    {
        $endpoint = str_replace('/zoap/demo/', '/zoap/nope/', $this->endpoint);
        list($status, $type, $body) = $this->call('ping', [], $endpoint);

        $this->assertSame('Please specify a valid service configuration.', Soap::faultString($body));
        $this->assertStringStartsWith('application/xml', $type);
        $this->assertSame(500, $status);
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
