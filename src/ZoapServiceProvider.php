<?php

namespace Viewflex\Zoap;


use Illuminate\Support\ServiceProvider;

class ZoapServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {

        /*
    	|--------------------------------------------------------------------------
    	| Set the Package Views Namespace
    	|--------------------------------------------------------------------------
    	*/

        $this->loadViewsFrom(__DIR__.'/Resources/views', 'zoap');

        /*
        |--------------------------------------------------------------------------
        | Publish Views and Config
        |--------------------------------------------------------------------------
        */

        $this->publishes([
            __DIR__.'/Resources/views' => base_path('resources/views/vendor/zoap'),
            __DIR__.'/../config/zoap.php' => base_path('config/zoap.php')
        ], 'zoap');

    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {

        /*
    	|--------------------------------------------------------------------------
        | Merge User-Customized Config Values
        |--------------------------------------------------------------------------
        */

        $this->mergeConfigFrom(
            __DIR__.'/../config/zoap.php', 'zoap'
        );


        /*
    	|--------------------------------------------------------------------------
    	| Include the Package Routes File.
    	|--------------------------------------------------------------------------
    	*/

        require __DIR__ . '/routes.php';

    }

}
