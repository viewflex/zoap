<?php

namespace Viewflex\Zoap;


use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use SoapFault;
use Laminas\Soap\AutoDiscover;
use Laminas\Soap\Server;
use Laminas\Soap\Server\DocumentLiteralWrapper;
use Laminas\Soap\Wsdl;
use Laminas\Soap\Wsdl\ComplexTypeStrategy\ComplexTypeStrategyInterface;

class ZoapController
{
    
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $service;

    /**
     * @var string
     */
    protected $endpoint;

    /**
     * @var array
     */
    protected $exceptions;

    /**
     * @var array
     */
    protected $types;

    /**
     * @var ComplexTypeStrategyInterface
     */
    protected $strategy;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var array
     */
    protected $options;
    
    /**
     * Initialize service attributes, disable PHP WSDL caching.
     * 
     * @param string $key
     * @throws \Exception
     */
    public function init($key)
    {
        $config = config('zoap.services.'.$key);

        if (! $config) {
            throw new \Exception('Please specify a valid service configuration.');
        }

        $this->name = $config['name'];
        $this->service = $config['class'];
        $this->endpoint = self::currentUrlRoot();
        $this->exceptions = Arr::get($config, 'exceptions', []);
        $this->types = Arr::get($config, 'types', []);

        $strategies = [
            'AnyType',
            'ArrayOfTypeComplex',
            'ArrayOfTypeSequence',
            'DefaultComplexType'
        ];

        $strategy = Arr::get($config, 'strategy') ? : 'ArrayOfTypeComplex';
        
        if (! in_array($strategy, $strategies)) {
            throw new \Exception('Please specify a valid complex type strategy.');
        }

        $strategy = "Laminas\\Soap\\Wsdl\\ComplexTypeStrategy\\" . $strategy;
        $this->strategy = new $strategy();

        $this->headers = Arr::get($config, 'headers', []);
        $this->options = Arr::get($config, 'options', []);

        if (! array_key_exists('Content-Type', $this->headers)) {
            $this->headers = Arr::add($this->headers, 'Content-Type', 'application/xml; charset=utf-8');
        }

        ini_set('soap.wsdl_cache_enable', 0);
        ini_set('soap.wsdl_cache_ttl', 0);

    }

    /**
     * Return results of a call to the specified service.
     *
     * @param $key
     * @return Factory|Response|View
     */
    public function server($key)
    {
        $request = app('request');
        $output = new Response();
        ob_start();

        try {

            $this->init($key);

            foreach($this->headers as $name => $value) {
                $output->headers->set($name, $value);
            }

            if ($request->query->has('wsdl')) {

                echo $this->buildWsdl();

            } else {

                // Serve from the WSDL generated in-process, rather than having
                // the SOAP server fetch it from this endpoint over HTTP.
                $wsdlFile = tempnam(sys_get_temp_dir(), 'zoap');
                file_put_contents($wsdlFile, $this->buildWsdl());

                try {

                    $server = new Server($wsdlFile);
                    $server->setClass(new DocumentLiteralWrapper(new $this->service()));
                    $server->registerFaultException($this->exceptions);
                    $server->setOptions($this->options);

                    // Intercept response, then decide what to do with it.
                    $server->setReturnResponse(true);
                    $response = $server->handle($request->getContent());

                } finally {
                    @unlink($wsdlFile);
                }

                // Deal with a thrown exception that was converted into a SoapFault.
                // SoapFault thrown directly in a service class is rendered by the
                // SOAP server itself, so detect it in the response.
                if ($response instanceof SoapFault) {

                    $output->setStatusCode(500);
                    echo self::serverFault($response);

                } else {

                    if (self::isFault($response)) {
                        $output->setStatusCode(500);
                    }

                    echo $response;

                }

            }


        } catch (\Exception $e) {

            $output->setStatusCode(500);
            echo self::serverFault($e);

        } catch (\Throwable $e) {

            // Don't expose internal error messages, as with unregistered exceptions.
            self::log($e->getMessage() . PHP_EOL . $e->getTraceAsString());
            $output->setStatusCode(500);
            echo self::serverFault(new \Exception('Unknown error', 0, $e));

        }

        // Faults raised before service headers were applied still need an XML content type.
        if (! $output->headers->has('Content-Type')) {
            $output->headers->set('Content-Type', 'application/xml; charset=utf-8');
        }

        $output->setContent(ob_get_clean());
        return $output;

    }

    /**
     * Generate the WSDL for the current service by auto-discovery.
     *
     * @return string
     */
    protected function buildWsdl()
    {
        // Create wsdl object and register type(s).
        $wsdl = new Wsdl('wsdl', $this->endpoint);

        foreach($this->types as $key => $class) {
            $wsdl->addType($class, $key);
        }

        // Set type(s) on strategy object.
        $this->strategy->setContext($wsdl);

        foreach($this->types as $key => $class) {
            $this->strategy->addComplexType($class);
        }

        // Auto-discover and return xml.
        $discover = new AutoDiscover($this->strategy);
        $discover->setBindingStyle(array('style' => 'document'));
        $discover->setOperationBodyStyle(array('use' => 'literal'));
        $discover->setClass($this->service);
        $discover->setUri($this->endpoint);
        $discover->setServiceName($this->name);

        return $discover->toXml();
    }

    /**
     * Determine whether a SOAP response contains a fault.
     *
     * @param mixed $response
     * @return bool
     */
    public static function isFault($response)
    {
        if (! is_string($response) || strpos($response, 'Fault') === false) {
            return false;
        }

        $dom = new \DOMDocument();

        if (! @$dom->loadXML($response)) {
            return false;
        }

        return $dom->getElementsByTagNameNS('http://schemas.xmlsoap.org/soap/envelope/', 'Fault')->length > 0
            || $dom->getElementsByTagNameNS('http://www.w3.org/2003/05/soap-envelope', 'Fault')->length > 0;
    }

    /**
     * Get the current absolute URL path, minus the query string.
     *
     * @return string
     */
    public static function currentUrlRoot()
    {
        $url = url(app()->request->server()['REQUEST_URI']);
        $pos = strpos($url, '?');
        return $pos ? substr($url, 0, $pos) : $url;
    }
    
    /**
     * Log message if logging is enabled in config, return input fluently.
     *
     * @param string $message
     * @return string
     */
    public static function log($message = '')
    {
        if(config('zoap.logging', false)) {
            Log::info($message);
        }

        return $message;
    }
    
    /**
     * Return error response and log stack trace.
     *
     * @param \Exception $exception
     * @return Factory|View
     */
    public static function serverFault(\Exception $exception)
    {
        self::log($exception->getTraceAsString());
        $faultcode = 'SOAP-ENV:Server';
        $faultstring = $exception->getMessage();
        return view('zoap::fault', compact('faultcode', 'faultstring'));
    }
    
}
