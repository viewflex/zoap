<?php

namespace Viewflex\Zoap\Tests\Support;

use DOMDocument;
use DOMXPath;

/**
 * Helpers for building Demo service request envelopes and inspecting responses.
 */
class Soap
{
    const ENVELOPE_NS = 'http://schemas.xmlsoap.org/soap/envelope/';

    const USER = 'test@test.com';

    const PASSWORD = 'tester';

    const TOKEN = 'tGSGYv8al1Ce6Rui8oa4Kjo8ADhYvR9x8KFZOeEGWgU1iscF7N2tUnI3t9bX';

    /**
     * Build a document/literal request envelope for an operation.
     *
     * @param string $endpoint
     * @param string $operation
     * @param string $body Inner XML of the operation element.
     * @return string
     */
    public static function envelope($endpoint, $operation, $body = '')
    {
        return '<soapenv:Envelope xmlns:soapenv="' . self::ENVELOPE_NS . '" xmlns:oper="' . $endpoint . '">'
            . '<soapenv:Header/>'
            . '<soapenv:Body>'
            . '<oper:' . $operation . '>' . $body . '</oper:' . $operation . '>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';
    }

    /**
     * Build operation parameters from an associative array.
     *
     * @param array $params
     * @return string
     */
    public static function params(array $params)
    {
        $xml = '';
        foreach ($params as $name => $value) {
            $xml .= '<oper:' . $name . '>' . $value . '</oper:' . $name . '>';
        }

        return $xml;
    }

    /**
     * Load an XML string, returning an XPath with 'env' registered, or null if not well-formed.
     *
     * @param string $xml
     * @return DOMXPath|null
     */
    public static function xpath($xml)
    {
        $dom = new DOMDocument();

        if (! is_string($xml) || $xml === '' || ! @$dom->loadXML($xml)) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('env', self::ENVELOPE_NS);
        $xpath->registerNamespace('wsdl', 'http://schemas.xmlsoap.org/wsdl/');
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/wsdl/soap/');
        $xpath->registerNamespace('xsd', 'http://www.w3.org/2001/XMLSchema');

        return $xpath;
    }

    /**
     * Return the text value of the first node matching the query, or null.
     *
     * @param string $xml
     * @param string $query
     * @return string|null
     */
    public static function value($xml, $query)
    {
        $xpath = self::xpath($xml);
        $nodes = $xpath ? $xpath->query($query) : false;

        return ($nodes && $nodes->length) ? $nodes->item(0)->textContent : null;
    }

    /**
     * Count nodes matching the query.
     *
     * @param string $xml
     * @param string $query
     * @return int
     */
    public static function count($xml, $query)
    {
        $xpath = self::xpath($xml);
        $nodes = $xpath ? $xpath->query($query) : false;

        return $nodes ? $nodes->length : 0;
    }

    /**
     * Return the faultstring of a SOAP fault response, or null if the response is not a fault.
     *
     * @param string $xml
     * @return string|null
     */
    public static function faultString($xml)
    {
        return self::value($xml, '/env:Envelope/env:Body/env:Fault/faultstring');
    }

    /**
     * Load the WSDL snapshot fixture with the given endpoint substituted.
     *
     * @param string $endpoint
     * @return string
     */
    public static function wsdlFixture($endpoint)
    {
        return str_replace('{{endpoint}}', $endpoint, file_get_contents(__DIR__ . '/../fixtures/demo.wsdl'));
    }
}
