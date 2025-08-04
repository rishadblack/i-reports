<?php
namespace Rishadblack\IReports\Http\Controllers;

use Illuminate\Http\Request;
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

        $report = $request->query('report');

        if (! $report) {
            abort(404, 'Invalid report.');
        }

        $controllerClass = $this->findReportClass($report);

        $requestHelper = new RequestHelper($request->all());

        // Optionally store globally for easy static access
        $requestHelper->storeGlobally();

        return app($controllerClass)->view($request);
    }
}
