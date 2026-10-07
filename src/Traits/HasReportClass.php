<?php

namespace Rishadblack\IReports\Traits;

use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Services\ReportResolver;

trait HasReportClass
{
    /**
     * Resolve a report name to its class, or throw a 404.
     *
     * @return class-string<BaseReportController>
     */
    public function findReportClass(string $report): string
    {
        return app(ReportResolver::class)->resolve($report);
    }
}
