<?php

Route::any('zoap/{key}/server',
    array(
        'as' => 'zoap.server', 
        'uses' => '\Viewflex\Zoap\ZoapController@server',
        'middleware' => 'api'
    )
);
