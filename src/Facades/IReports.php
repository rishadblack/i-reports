<?php

namespace Rishadblack\IReports\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Rishadblack\IReports\IReports register(array<string, class-string>|string $name, ?string $class = null)
 * @method static string|null find(string $name)
 * @method static array<string, class-string<\Rishadblack\IReports\BaseReportController>> all()
 * @method static \Illuminate\Support\Collection<string, class-string<\Rishadblack\IReports\BaseReportController>> collection()
 *
 * @see \Rishadblack\IReports\IReports
 */
class IReports extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Rishadblack\IReports\IReports::class;
    }
}
