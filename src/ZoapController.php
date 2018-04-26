<?php

namespace Viewflex\Zoap;


use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use SoapFault;
use Zend\Soap\AutoDiscover;
use Zend\Soap\Server;
use Zend\Soap\Server\DocumentLiteralWrapper;
use Zend\Soap\Wsdl;
use Zend\Soap\Wsdl\ComplexTypeStrategy\ComplexTypeStrategyInterface;

class ZoapController extends Controller
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
     * Initialize service attributes, disable PHP WSDL caching.
     * 
     * @param string $key
     * @throws \Exception
     */
    public function init($key)
    {
        $config = config('zoap.services.'.$key);

        $this->name = $config['name'];
        $this->service = $config['class'];
        $this->endpoint = self::currentUrlRoot();
        $this->exceptions = $config['exceptions'];
        $this->types = $config['types'];

        $strategies = [
            'AnyType',
            'ArrayOfTypeComplex',
            'ArrayOfTypeSequence',
            'DefaultComplexType'
        ];

        $strategy = ($config['strategy']) ? : 'ArrayOfTypeComplex';
        
        if (! in_array($strategy, $strategies)) {
            throw new \Exception('Please specify a valid complex type strategy.');
        }

        $strategy = "Zend\\Soap\\Wsdl\\ComplexTypeStrategy\\" . $strategy;
        $this->strategy = new $strategy();

        $this->headers = $config['headers'];

        if (! array_key_exists('Content-Type', $this->headers)) {
            $this->headers = array_add($this->headers, 'Content-Type', 'application/xml; charset=utf-8');
        }

        ini_set('soap.wsdl_cache_enable', 0);
        ini_set('soap.wsdl_cache_ttl', 0);

    }

    /**
     * Return results of a call to the specified service.
     *
     * @param $key
     * @return \Illuminate\Contracts\View\Factory|Response|\Illuminate\View\View
     */
    public function server($key)
    {
        $output = new Response();
        ob_start();

        try {

            $this->init($key);
            
            foreach($this->headers as $key => $value) {
                $output->headers->set($key, $value);
            }
            
            if (isset($_GET['wsdl'])) {

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

                // Auto-discover and output xml.
                $discover = new AutoDiscover($this->strategy);
                $discover->setBindingStyle(array('style' => 'document'));
                $discover->setOperationBodyStyle(array('use' => 'literal'));
                $discover->setClass($this->service);
                $discover->setUri($this->endpoint);
                $discover->setServiceName($this->name);
                echo $discover->toXml();

            } else {
                
                $server = new Server($this->endpoint . '?wsdl');
                $server->setClass(new DocumentLiteralWrapper(new $this->service()));
                $server->registerFaultException($this->exceptions);

                // Intercept response, then decide what to do with it.
                $server->setReturnResponse(true);
                $response = $server->handle();

                // Deal with a thrown exception that was converted into a SoapFault.
                // SoapFault thrown directly in a service class bypasses this code.
                if ($response instanceof SoapFault) {

                    $output->headers->set("Status", 500);
                    echo self::serverFault($response);
                    
                } else {
                    
                    echo $response;
                    
                }

            }


        } catch (\Exception $e) {

            $output->headers->set("Status", 500);
            echo self::serverFault($e);
            
        }

        $output->setContent(ob_get_clean());
        return $output;

    }

    /**
     * Get the current absolute URL path, minus the query string.
     *
     * @return string
     */
    public static function currentUrlRoot()
    {
        $url = url(request()->server()['REQUEST_URI']);
        $pos = strpos($url, '?');
        return $pos ? substr($url, 0, $pos) : substr($url, 0);
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
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public static function serverFault(\Exception $exception)
    {
        self::log($exception->getTraceAsString());
        $faultcode = 'SOAP-ENV:Server';
        $faultstring = $exception->getMessage();
        return view('zoap::fault', compact('faultcode', 'faultstring'));
    }
    
}
