<?php

namespace Rishadblack\IReports\Http\Controllers;

use Illuminate\Http\Request;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Services\ReportResolver;
use Rishadblack\IReports\Services\ReportTokenManager;
use Rishadblack\IReports\Support\ReportContext;
use Rishadblack\IReports\Support\Runtime;

/**
 * Renders a report (or returns its export) from a token issued by the viewer.
 *
 * Only the token is trusted. Query parameters other than the token are ignored.
 */
class ReportViewController
{
    public function __invoke(Request $request, ReportResolver $resolver, ReportContext $context): mixed
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            abort(403, 'A report token is required.');
        }

        $resolved = ReportTokenManager::resolve($token);

        if ($resolved === null) {
            abort(403, 'Invalid or expired report token.');
        }

        $requestHelper = new RequestHelper($resolved);
        $context->reset();
        $requestHelper->storeGlobally();

        $reportName = $requestHelper->getReport();

        if ($reportName === '') {
            abort(404, 'Report not found.');
        }

        $report = app($resolver->resolve($reportName));

        if (! $report->authorize()) {
            abort(403, 'You are not allowed to view this report.');
        }

        Runtime::disableDebugbar();

        if ($context->isFullExport()) {
            Runtime::prepareForExport();
        }

        return $report->view();
    }
}
