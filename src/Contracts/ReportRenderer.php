<?php

namespace Rishadblack\IReports\Contracts;

use Illuminate\Contracts\View\View;
use Rishadblack\IReports\BaseReportController;

/**
 * Renders a report's HTML body. Bind another implementation to swap the template engine.
 */
interface ReportRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(BaseReportController $report, string $view, array $data = []): View;
}
