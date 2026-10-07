<?php

namespace Rishadblack\IReports\Renderers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Contracts\ReportRenderer;

class BladeRenderer implements ReportRenderer
{
    public function __construct(protected Factory $views) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(BaseReportController $report, string $view, array $data = []): View
    {
        return $this->views->make($view, $data);
    }
}
