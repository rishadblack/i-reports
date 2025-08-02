<?php
namespace Rishadblack\IReports\Traits;

use Illuminate\Support\Str;

trait HasReportClass
{
    public function findReportClass($report)
    {
        // Allow only letters and numbers (adjust regex if needed)
        if (! preg_match('/^[a-zA-Z0-9\-]+$/', $report)) {
            abort(404);
        }

        $report = Str::studly($report);
        $namespaceSegment = config('i-reports.report_namespace', 'Reports');
        $suffix = config('i-reports.report_suffix', '');
        $controllerClass = null;

        // 1. Check in nWidart Modules (if package is installed)
        if (class_exists('\Nwidart\Modules\Facades\Module')) {
            foreach (\Nwidart\Modules\Facades\Module::allEnabled() as $module) {
                $moduleName = $module->getName();
                $class = "Modules\\{$moduleName}\\{$namespaceSegment}\\{$report}{$suffix}";

                if (class_exists($class)) {
                    $controllerClass = $class;
                    break;
                }
            }
        }

        // 2. Fallback to App\Reports\...
        if (! $controllerClass) {
            $class = "App\\{$namespaceSegment}\\{$report}{$suffix}";
            if (class_exists($class)) {
                $controllerClass = $class;
            }
        }

        // 3. Abort if not found
        if (! $controllerClass) {
            abort(404, 'Report controller not found');
        }

        return $controllerClass;
    }
}