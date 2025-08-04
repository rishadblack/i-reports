<?php
namespace Rishadblack\IReports\Http\Controllers;

use Illuminate\Http\Request;
use Rishadblack\IReports\Helpers\ReportHelper;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Services\ReportTokenManager;
use Rishadblack\IReports\Traits\HasReportClass;

class ReportViewController
{
    use HasReportClass;

    public function __invoke(Request $request)
    {

        $token = $request->query('token');

        if ($token) {
            $resolved = ReportTokenManager::resolve($token);

            if (! $resolved) {
                abort(403, 'Invalid or expired report token.');
            }

            foreach ($resolved as $key => $value) {
                $request->merge([$key => $value]);
            }
        }

        $requestHelper = new RequestHelper($request->all());
        $requestHelper->storeGlobally();

        $report = ReportHelper::getReport();

        if (! $report) {
            throw new \Exception("Report not found: {$report}");
        }

        $controllerClass = $this->findReportClass($report);

        $reportInstance = app($controllerClass);

        if (! $reportInstance) {
            throw new \Exception("Report class not found: {$controllerClass}");

        }

        ReportHelper::setColumns($reportInstance->columns());

        return $reportInstance->view();
    }
}
