<?php

namespace Rishadblack\IReports\Facades;

use Illuminate\Support\Facades\Facade;

class IReports extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'i-reports';
    }
}
