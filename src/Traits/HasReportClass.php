<?php
namespace Rishadblack\IReports\Traits;

use Illuminate\Support\Str;

trait HasReportClass
{
    public function findReportClass(string $report): string
    {

        $livewireNamespace = config('livewire.class_namespace');
        $moduleNamespace = config('modules.namespace', 'Modules');       // e.g., 'Modules'
        $moduleLivewireNamespace = config('modules-livewire.namespace'); // e.g., 'Livewire'
        $reportNamespaceSegment = config('i-reports.report_namespace');  // e.g., 'Reports'
        $suffix = config('i-reports.report_suffix', '');                 // e.g., 'Report'

        // Module format: module::report.path-name
        if (str_contains($report, '::')) {

            [$moduleName, $reportPath] = explode('::', $report, 2);

            // Validate
            if (! preg_match('/^[a-zA-Z0-9\-]+$/', $moduleName) ||
                ! preg_match('/^[a-zA-Z0-9.\-]+$/', $reportPath)) {
                throw new \Exception("Module Report not found: {$report}");
            }

            // Convert dot notation to PSR-4 class path
            $reportClassPath = collect(explode('.', $reportPath))
                ->map(fn($segment) => Str::studly($segment))
                ->implode('\\');

            // Build final class path
            $class = "{$moduleNamespace}\\" . Str::studly($moduleName) . "\\{$moduleLivewireNamespace}\\{$reportNamespaceSegment}\\{$reportClassPath}{$suffix}";

            if (class_exists($class)) {
                return $class;
            }

            throw new \Exception("Module Report class not found: {$class}");
        }

        // Fallback to App\Livewire\Reports\...
        if (! preg_match('/^[a-zA-Z0-9.\-]+$/', $report)) {
            throw new \Exception("Report not found: {$report}");
        }

        $reportClassPath = collect(explode('.', $report))
            ->map(fn($segment) => Str::studly($segment))
            ->implode('\\');

        $class = "{$livewireNamespace}\\{$reportNamespaceSegment}\\{$reportClassPath}{$suffix}";

        if (class_exists($class)) {
            return $class;
        }

        throw new \Exception("Report class not found: {$class}");
    }

}
