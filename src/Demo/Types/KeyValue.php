<?php

namespace Viewflex\Zoap\Demo\Types;


class KeyValue
{
    /**
     * @var string
     */
    public $key = '';

    /**
     * @var string
     */
    public $value = '';

    /**
     * KeyValue constructor.
     * @param string $key
     * @param string $value
     */
    public function __construct($key='', $value='')
    {
        $this->key = $key;
        $this->value = $value;
    }

}
