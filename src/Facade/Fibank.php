<?php

namespace Gentor\Fibank\Facade;

use Illuminate\Support\Facades\Facade;

/**
 * Class Fibank
 *
 * @package Gentor\Fibank\Facade
 */
class Fibank extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'fibank';
    }
}
