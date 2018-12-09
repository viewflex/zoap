# Zoap

[![GitHub license](https://img.shields.io/github/license/mashape/apistatus.svg?maxAge=2592000)](LICENSE.md)

Instant SOAP server for Laravel and Lumen, turns any class into a WS-I compliant SOAP service, with automatic discovery of WSDL definitions. Wraps the Zend SOAP components to provide easy declarative configuration of services, requiring no additional coding.

## Overview

### System Requirements

Laravel or Lumen framework, version 5.2 or greater.

### Basic Steps

Setting up services is quick and painless:

* Install this package in your Laravel or Lumen application.
* Publish the config file for customization.
* Define configurations for your services.

### Examples

There is a Demo service already configured and ready to test. This can be used as a template for creating your own services from existing classes. WSDL auto-discovery and generation depends on you having properly annotated your service class attributes and methods with PHP DocBlocks, as illustrated in the `DemoService` class and explained below.

### Architecture

This package uses the `document/literal wrapped` pattern in SOAP communications and WSDL generation, but if necessary, the `ZoapController` class can be extended to deploy an alternate pattern.


## Installation

From your Laravel or Lumen application's root directory, install via Composer:

```bash
composer require viewflex/zoap
```

After installing, add the `ZoapServiceProvider` to the list of service providers:

### For Laravel

Add this line in `config/app.php`. If you are using Laravel version 5.5 or greater, this step is not necessary.

```php
Viewflex\Zoap\ZoapServiceProvider::class,
```

### For Lumen

Add this line in `bootstrap/app.php`:

```php
$app->register(Viewflex\Zoap\ZoapServiceProvider::class);
```

You may also want to install the [irazasyed/larasupport](https://github.com/irazasyed/larasupport) package, which adds a few basic Laravel features to Lumen, including support for publishing package files. This will allow you to publish the Zoap config and view files for customization as described [below](#configuration); otherwise, you can just copy those files manually from the package to their published locations.

## Configuration

The `zoap.php` config file contains both general settings and configuration of individual services.

Run this command to publish the `zoap.php` config file to the project's `config` directory for customization:

```bash
php artisan vendor:publish  --tag='zoap'
```

This will also publish the SoapFault response template to your project's `resources/views/vendor/zoap` directory.

### Logging

When enabled, full error information, including trace stack, will be logged for exceptions.


### Services

The `ZoapController` class configures the server for the service matching the `key` route parameter (see [Routing](#routing) below). In this way you can serve any number of classes with your SOAP server, simply by defining them here.

The Demo service is provided as an example; it's configuration is shown here:

```php
    'services'          => [

        'demo'              => [
            'name'              => 'Demo',
            'class'             => 'Viewflex\Zoap\Demo\DemoService',
            'exceptions'        => [
                'Exception'
            ],
            'types'             => [
                'keyValue'          => 'Viewflex\Zoap\Demo\Types\KeyValue',
                'product'           => 'Viewflex\Zoap\Demo\Types\Product'
            ],
            'strategy'          => 'ArrayOfTypeComplex',
            'headers'           => [
                'Cache-Control'     => 'no-cache, no-store'
            ],
            'options'           => []
        ]

    ],
```

#### Name

Specify the name of the service as it will appear in the generated WSDL file.

#### Class

Specify the class you want to serve. Public attributes and methods of this class will be made available by the SOAP server.

#### Exceptions

List any exceptions you want caught and converted to a `SoapFault`. Using `Exception` will catch all exceptions, or you can be more specific and list individual child exceptions. Any exceptions not on this whitelist may return unpredictable results, including no result at all. We don't want to let the server return a `SoapFault` directly, which could expose a stack trace; instead the exception is caught and then returned as a `SoapFault` with the proper message.

#### Types

Add complex types as necessary - typically auto-discovery will find them, but if not they can be specified here - auto-discovery will not redundantly add the same type again anyway.

#### Strategy

Specify one of these `ComplexTypeStrategyInterface` implementations to use in auto-discovery:

* AnyType
* ArrayOfTypeComplex
* ArrayOfTypeSequence
* DefaultComplexType

If not specified, the `ArrayOfTypeComplex` strategy will be used.

#### Headers

A `Content-Type` header of 'application/xml; charset=utf-8' is set automatically if not otherwise specified here. Specify any additional HTTP response headers required.

#### Options

Specify an array of server options for this service (optional).

## Routing

The package routes file routes the Demo service:

```php
app()->router->get('zoap/{key}/server', [
    'as' => 'zoap.server.wsdl',
    'uses' => '\Viewflex\Zoap\ZoapController@server'
]);

app()->router->post('zoap/{key}/server', [
    'as' => 'zoap.server',
    'uses' => '\Viewflex\Zoap\ZoapController@server'
]);
```

Use this route or create new routes as necessary to access your SOAP services on the `ZoapController` class, using the same URL parameter `{key}` to indicate the key for a service configuration. The key 'demo' is used to look up the Demo [service configuration](#configuration).

## Usage

SOAP is a complex specification with various implementations, and can be difficult to work with for a number of reasons. This package abstracts much of the implementation details away from the developer.

It remains for you to define your SOAP API using PHP DocBlock notation on all public class attributes and methods; this is used by the auto-discovery process to define your service. See the [Demo](#demo) section below to get a walk-through of a real implementation provided as an example to get you started.


## Demo

The Demo SOAP service provided with this package is a simple implementation example, with commonly used configuration values. The `DemoService` class references a fictional provider (`DemoProvider` class) which returns some hard-coded results, simply to illustrate the concept of application functionality exposed as a SOAP service.

Example requests for the Demo service methods are provided below, along with the expected responses. Replace 'http://example.com' in the requests with the actual domain of your Laravel application.

The Demo service class provides an example of how method parameters and return values are automatically transformed by the server to the appropriate data formats. Shown here is the DocBlock of the `getProducts` method in the `DemoService` class:

```php
/**
 * Returns an array of products by search criteria.
 *
 * @param \Viewflex\Zoap\Types\KeyValue[] $criteria
 * @param string $token
 * @param string $user
 * @param string $password
 * @return \Viewflex\Zoap\Types\Product[]
 * @throws SoapFault
 */
 public function getProducts($criteria = [], $token = '', $user = '', $password = '')
```

This method returns an array of `Product` objects, wrapped and formatted as an XML string, [as shown below](#getproducts).


### WSDL Generation

A SOAP server must be provided with a WSDL file to be able to recognize the methods and data models it is expected to handle, but writing one manually is difficult and error-prone, given the complexity of the specification and the lack of good documentation. This package uses auto-discovery to generate the WSDL file automatically.

The WSDL definition of the Demo service can be obtained via GET request to the Demo route with the empty URL parameter `'wsdl'`:

    http://example.com/zoap/demo/server?wsdl

It should return a complete WSDL file describing the Demo service.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<definitions xmlns="http://schemas.xmlsoap.org/wsdl/" xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/" xmlns:tns="http://example.com/zoap/demo/server" xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap-enc="http://schemas.xmlsoap.org/soap/encoding/" xmlns:soap12="http://schemas.xmlsoap.org/wsdl/soap12/" name="Demo" targetNamespace="http://example.com/zoap/demo/server">
    <types>
        <xsd:schema targetNamespace="http://example.com/zoap/demo/server">
            <xsd:element name="auth">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="user" type="xsd:string"/>
                        <xsd:element name="password" type="xsd:string"/>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>
            <xsd:element name="authResponse">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="authResult" type="soap-enc:Array"/>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>
            <xsd:element name="ping">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="token" type="xsd:string" nillable="true"/>
                        <xsd:element name="user" type="xsd:string" nillable="true"/>
                        <xsd:element name="password" type="xsd:string" nillable="true"/>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>
            <xsd:element name="pingResponse">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="pingResult" type="xsd:boolean"/>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>
            <xsd:element name="getProduct">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="productId" type="xsd:int"/>
                        <xsd:element name="token" type="xsd:string" nillable="true"/>
                        <xsd:element name="user" type="xsd:string" nillable="true"/>
                        <xsd:element name="password" type="xsd:string" nillable="true"/>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>
            <xsd:complexType name="Product">
                <xsd:all>
                    <xsd:element name="id" type="xsd:int"/>
                    <xsd:element name="name" type="xsd:string" nillable="true"/>
                    <xsd:element name="category" type="xsd:string" nillable="true"/>
                    <xsd:element name="subcategory" type="xsd:string" nillable="true"/>
                    <xsd:element name="price" type="xsd:float" nillable="true"/>
                </xsd:all>
            </xsd:complexType>
            <xsd:element name="getProductResponse">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="getProductResult" type="tns:Product"/>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>
            <xsd:complexType name="KeyValue">
                <xsd:all>
                    <xsd:element name="key" type="xsd:string"/>
                    <xsd:element name="value" type="xsd:string"/>
                </xsd:all>
            </xsd:complexType>
            <xsd:complexType name="ArrayOfKeyValue">
                <xsd:complexContent>
                    <xsd:restriction base="soap-enc:Array">
                        <xsd:attribute ref="soap-enc:arrayType" wsdl:arrayType="tns:KeyValue[]"/>
                    </xsd:restriction>
                </xsd:complexContent>
            </xsd:complexType>
            <xsd:element name="getProducts">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="criteria" type="tns:ArrayOfKeyValue" nillable="true"/>
                        <xsd:element name="token" type="xsd:string" nillable="true"/>
                        <xsd:element name="user" type="xsd:string" nillable="true"/>
                        <xsd:element name="password" type="xsd:string" nillable="true"/>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>
            <xsd:complexType name="ArrayOfProduct">
                <xsd:complexContent>
                    <xsd:restriction base="soap-enc:Array">
                        <xsd:attribute ref="soap-enc:arrayType" wsdl:arrayType="tns:Product[]"/>
                    </xsd:restriction>
                </xsd:complexContent>
            </xsd:complexType>
            <xsd:element name="getProductsResponse">
                <xsd:complexType>
                    <xsd:sequence>
                        <xsd:element name="getProductsResult" type="tns:ArrayOfProduct"/>
                    </xsd:sequence>
                </xsd:complexType>
            </xsd:element>
        </xsd:schema>
    </types>
    <portType name="DemoPort">
        <operation name="auth">
            <documentation>Authenticates user/password, returning status of true with token, or throws SoapFault.</documentation>
            <input message="tns:authIn"/>
            <output message="tns:authOut"/>
        </operation>
        <operation name="ping">
            <documentation>Returns boolean authentication result using given token or user/password.</documentation>
            <input message="tns:pingIn"/>
            <output message="tns:pingOut"/>
        </operation>
        <operation name="getProduct">
            <documentation>Returns a product by id.</documentation>
            <input message="tns:getProductIn"/>
            <output message="tns:getProductOut"/>
        </operation>
        <operation name="getProducts">
            <documentation>Returns an array of products by search criteria.</documentation>
            <input message="tns:getProductsIn"/>
            <output message="tns:getProductsOut"/>
        </operation>
    </portType>
    <binding name="DemoBinding" type="tns:DemoPort">
        <soap:binding style="document" transport="http://schemas.xmlsoap.org/soap/http"/>
        <operation name="auth">
            <soap:operation soapAction="http://example.com/zoap/demo/server#auth"/>
            <input>
                <soap:body use="literal"/>
            </input>
            <output>
                <soap:body use="literal"/>
            </output>
        </operation>
        <operation name="ping">
            <soap:operation soapAction="http://example.com/zoap/demo/server#ping"/>
            <input>
                <soap:body use="literal"/>
            </input>
            <output>
                <soap:body use="literal"/>
            </output>
        </operation>
        <operation name="getProduct">
            <soap:operation soapAction="http://example.com/zoap/demo/server#getProduct"/>
            <input>
                <soap:body use="literal"/>
            </input>
            <output>
                <soap:body use="literal"/>
            </output>
        </operation>
        <operation name="getProducts">
            <soap:operation soapAction="http://example.com/zoap/demo/server#getProducts"/>
            <input>
                <soap:body use="literal"/>
            </input>
            <output>
                <soap:body use="literal"/>
            </output>
        </operation>
    </binding>
    <service name="DemoService">
        <port name="DemoPort" binding="tns:DemoBinding">
            <soap:address location="http://example.com/zoap/demo/server"/>
        </port>
    </service>
    <message name="authIn">
        <part name="parameters" element="tns:auth"/>
    </message>
    <message name="authOut">
        <part name="parameters" element="tns:authResponse"/>
    </message>
    <message name="pingIn">
        <part name="parameters" element="tns:ping"/>
    </message>
    <message name="pingOut">
        <part name="parameters" element="tns:pingResponse"/>
    </message>
    <message name="getProductIn">
        <part name="parameters" element="tns:getProduct"/>
    </message>
    <message name="getProductOut">
        <part name="parameters" element="tns:getProductResponse"/>
    </message>
    <message name="getProductsIn">
        <part name="parameters" element="tns:getProducts"/>
    </message>
    <message name="getProductsOut">
        <part name="parameters" element="tns:getProductsResponse"/>
    </message>
</definitions>
```

### Service Methods

To access a service method, use a POST request with `Content-Type` header of 'application/xml' or 'text/xml', and body content as shown below. The `user`, `password` and `token` parameters will be authenticated against hard-coded values, so you can see the failure result if you change them. Also included in the Demo service are methods for getting a single product or an array of products, to illustrate the formatting of results from methods returning complex objects.

#### auth

##### Request

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:oper="http://example.com/zoap/demo/server">
    <soapenv:Header/>
    <soapenv:Body>
        <oper:auth>
            <oper:user>test@test.com</oper:user>
            <oper:password>tester</oper:password>
        </oper:auth>
    </soapenv:Body>
</soapenv:Envelope>
```

##### Response

```xml
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://example.com/zoap/demo/server">
    <SOAP-ENV:Body>
        <ns1:authResponse>
            <authResult>
                <item>
                    <key>status</key>
                    <value>true</value>
                </item>
                <item>
                    <key>token</key>
                    <value>tGSGYv8al1Ce6Rui8oa4Kjo8ADhYvR9x8KFZOeEGWgU1iscF7N2tUnI3t9bX</value>
                </item>
            </authResult>
        </ns1:authResponse>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
```

#### ping

##### Request

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:oper="http://example.com/zoap/demo/server">
    <soapenv:Header/>
    <soapenv:Body>
        <oper:ping>
            <oper:token>tGSGYv8al1Ce6Rui8oa4Kjo8ADhYvR9x8KFZOeEGWgU1iscF7N2tUnI3t9bX</oper:token>
        </oper:ping>
    </soapenv:Body>
</soapenv:Envelope>
```

##### Response

```xml
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://example.com/zoap/demo/server">
    <SOAP-ENV:Body>
        <ns1:pingResponse>
            <pingResult>true</pingResult>
        </ns1:pingResponse>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
```


#### getProduct

##### Request

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:oper="http://example.com/zoap/demo/server">
    <soapenv:Header/>
    <soapenv:Body>
        <oper:getProduct>
            <oper:productId>456</oper:productId>
            <oper:token>tGSGYv8al1Ce6Rui8oa4Kjo8ADhYvR9x8KFZOeEGWgU1iscF7N2tUnI3t9bX </oper:token>
        </oper:getProduct>
    </soapenv:Body>
</soapenv:Envelope>
```
##### Response

```xml
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://example.com/zoap/demo/server">
    <SOAP-ENV:Body>
        <ns1:getProductResponse>
            <getProductResult>
                <id>456</id>
                <name>North Face Summit Ski Jacket</name>
                <category>Outerwear</category>
                <subcategory>Women</subcategory>
                <price>249.98</price>
            </getProductResult>
        </ns1:getProductResponse>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
```


#### getProducts

##### Request

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:oper="http://example.com/zoap/demo/server">
    <soapenv:Header/>
    <soapenv:Body>
        <oper:getProducts>
            <oper:criteria>
                <oper:keyValue>
                    <oper:key>category</oper:key>
                    <oper:value>Outerwear</oper:value>
                </oper:keyValue>
            </oper:criteria>
            <oper:token>tGSGYv8al1Ce6Rui8oa4Kjo8ADhYvR9x8KFZOeEGWgU1iscF7N2tUnI3t9bX</oper:token> 
        </oper:getProducts>
    </soapenv:Body>
</soapenv:Envelope>
```

##### Response

```xml
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://example.com/zoap/demo/server">
    <SOAP-ENV:Body>
        <ns1:getProductsResponse>
            <getProductsResult>
                <ns1:Product>
                    <id>456</id>
                    <name>North Face Summit Ski Jacket</name>
                    <category>Outerwear</category>
                    <subcategory>Women</subcategory>
                    <price>249.98</price>
                </ns1:Product>
                <ns1:Product>
                    <id>789</id>
                    <name>Marmot Crew Neck Base Layer</name>
                    <category>Outerwear</category>
                    <subcategory>Men</subcategory>
                    <price>95.29</price>
                </ns1:Product>
            </getProductsResult>
        </ns1:getProductsResponse>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
```

## Tests

Using an HTTP client such as [Postman](https://www.getpostman.com/), you can test your services directly with XML requests. A [Postman collection file](tests/Zoap.postman_collection.json) for the Demo service is included in this package's `tests` directory; you can run the collection's test suite from within Postman or on the command line via Newman (see Postman docs). Set the collection variable `domain` to your Laravel app's actual domain (instead of 'http://example.com').

## License

This software is offered for use under the [MIT License](LICENSE.md).

## Changelog

Release versions are tracked in the [Changelog](CHANGELOG.md).
