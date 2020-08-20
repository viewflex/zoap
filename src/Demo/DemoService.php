<?php

namespace Viewflex\Zoap\Demo;


use SoapFault;
use Viewflex\Zoap\Demo\DemoProvider as Provider;
use Viewflex\Zoap\Demo\Types\KeyValue;
use Viewflex\Zoap\Demo\Types\Product;

/**
 * An example of a class that is used as a SOAP gateway to application functions.
 */
class DemoService
{
    /*
    |--------------------------------------------------------------------------
    | Public Methods
    |--------------------------------------------------------------------------
    */
    
    /**
     * Authenticates user/password, returning status of true with token, or throws SoapFault.
     *
     * @param string $user
     * @param string $password
     * @return array
     * @throws SoapFault
     */
    public function auth($user, $password)
    {

        if (Provider::validateUser($user, $password)) {
            return ['status' => 'true', 'token' => Provider::getToken($user)];
        } else {
            header("Status: 401");
            throw new SoapFault('SOAP-ENV:Client', 'Incorrect credentials.');

        }

    }

    /**
     * Returns boolean authentication result using given token or user/password.
     *
     * @param string $token
     * @param string $user
     * @param string $password
     * @return bool
     */
    public function ping($token = '', $user = '', $password = '')
    {
        return Provider::authenticate($token, $user, $password);
    }

    /**
     * Returns a product by id.
     *
     * @param int $productId
     * @param string $token
     * @param string $user
     * @param string $password
     * @return Product
     * @throws SoapFault
     */
    public function getProduct($productId, $token = '', $user = '', $password = '')
    {

        if (! $productId) {
            header("Status: 400");
            throw new SoapFault('SOAP-ENV:Client', 'Please specify product id.');
        }

        if (! Provider::authenticate($token, $user, $password)) {
            header("Status: 401");
            throw new SoapFault('SOAP-ENV:Client', 'Incorrect credentials.');
        }

        return Provider::findProduct($productId);
    }

    /**
     * Returns an array of products by search criteria.
     *
     * @param KeyValue[] $criteria
     * @param string $token
     * @param string $user
     * @param string $password
     * @return Product[]
     * @throws SoapFault
     */
    public function getProducts($criteria = [], $token = '', $user = '', $password = '')
    {

        if (! Provider::authenticate($token, $user, $password)) {
            header("Status: 401");
            throw new SoapFault('SOAP-ENV:Client', 'Incorrect credentials.');
        }

        return Provider::findProductsBy(self::arrayOfKeyValueToArray($criteria));

    }

    /*
    |--------------------------------------------------------------------------
    | Utility
    |--------------------------------------------------------------------------
    */
    
    /**
     * Convert array of KeyValue objects to associative array, non-recursively.
     *
     * @param KeyValue[] $objects
     * @return array
     */
    protected static function arrayOfKeyValueToArray($objects)
    {
        $return = array();
        foreach ($objects as $object) {
            $return[$object->key] = $object->value;
        }

        return $return;
    }

}
