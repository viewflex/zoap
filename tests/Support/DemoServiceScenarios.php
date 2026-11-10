<?php

namespace Viewflex\Zoap\Tests\Support;

/**
 * Demo service scenarios shared by the in-process and live test suites.
 *
 * The using class sets $endpoint and implements http(), which sends the
 * request through its own transport.
 */
trait DemoServiceScenarios
{
    /**
     * @var string
     */
    protected $endpoint;

    /**
     * Send an HTTP request, returning [status, content type, body].
     *
     * @param string $method
     * @param string $url
     * @param string|null $body
     * @return array
     */
    abstract protected function http($method, $url, $body = null);

    protected function callOperation($operation, array $params, $endpoint = null)
    {
        $endpoint = $endpoint ?: $this->endpoint;

        return $this->http('POST', $endpoint, Soap::envelope($this->endpoint, $operation, Soap::params($params)));
    }

    protected function assertFault($expectedMessage, array $response)
    {
        list($status, $type, $body) = $response;

        $this->assertSame($expectedMessage, Soap::faultString($body), 'Response is not the expected SOAP fault: ' . $body);
        $this->assertStringStartsWith('application/xml', $type);
        $this->assertSame(500, $status);
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
        list($status, , $body) = $this->callOperation('auth', ['user' => Soap::USER, 'password' => Soap::PASSWORD]);

        $this->assertSame(200, $status);
        $this->assertSame('true', Soap::value($body, '//authResult/item[key="status"]/value'));
        $this->assertSame(Soap::TOKEN, Soap::value($body, '//authResult/item[key="token"]/value'));
    }

    public function testPingWithValidToken()
    {
        list($status, , $body) = $this->callOperation('ping', ['token' => Soap::TOKEN]);

        $this->assertSame(200, $status);
        $this->assertSame('true', Soap::value($body, '//pingResult'));
    }

    public function testPingWithInvalidToken()
    {
        list($status, , $body) = $this->callOperation('ping', ['token' => 'wrong']);

        $this->assertSame(200, $status);
        $this->assertSame('false', Soap::value($body, '//pingResult'));
    }

    public function testGetProduct()
    {
        list($status, , $body) = $this->callOperation('getProduct', ['productId' => 456, 'token' => Soap::TOKEN]);

        $this->assertSame(200, $status);
        $this->assertSame('456', Soap::value($body, '//getProductResult/id'));
        $this->assertSame('North Face Summit Ski Jacket', Soap::value($body, '//getProductResult/name'));
        $this->assertSame('249.98', Soap::value($body, '//getProductResult/price'));
    }

    public function testGetProducts()
    {
        $criteria = '<oper:keyValue><oper:key>category</oper:key><oper:value>Outerwear</oper:value></oper:keyValue>';
        list($status, , $body) = $this->callOperation('getProducts', ['criteria' => $criteria, 'token' => Soap::TOKEN]);

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
        $this->assertFault('Incorrect credentials.', $this->callOperation('auth', ['user' => 'x', 'password' => 'y']));
    }

    public function testGetProductWithoutIdFaults()
    {
        $this->assertFault('Please specify product id.', $this->callOperation('getProduct', ['productId' => 0, 'token' => Soap::TOKEN]));
    }

    public function testGetProductsWithBadTokenFaults()
    {
        $this->assertFault('Incorrect credentials.', $this->callOperation('getProducts', ['token' => 'wrong']));
    }

    public function testUnknownServiceFaults()
    {
        $endpoint = str_replace('/zoap/demo/', '/zoap/nope/', $this->endpoint);

        $this->assertFault('Please specify a valid service configuration.', $this->callOperation('ping', [], $endpoint));
    }
}
