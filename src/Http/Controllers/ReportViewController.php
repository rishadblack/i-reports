<?php
namespace Rishadblack\IReports\Http\Controllers;

use Illuminate\Http\Request;
use Rishadblack\IReports\Traits\HasReportClass;

class ReportViewController
{
    use HasReportClass;

    public function __invoke(Request $request, $report)
    {
        $controllerClass = $this->findReportClass($report);

        return app($controllerClass)->view($request);
    }
}
