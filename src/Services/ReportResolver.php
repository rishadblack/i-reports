<?php

namespace Rishadblack\IReports\Services;

use Illuminate\Support\Str;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\IReports;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Turns a report name ("users", "sales.daily", "billing::invoices") into a report class.
 *
 * Registered names (config `reports` or IReports::register()) win. Otherwise the name is
 * resolved by convention under the Livewire class namespace.
 */
class ReportResolver
{
    public function __construct(protected IReports $registry) {}

    /**
     * @return class-string<BaseReportController>
     */
    public function resolve(string $report): string
    {
        $class = $this->find($report);

        if ($class === null) {
            throw new NotFoundHttpException("Report not found: {$report}");
        }

        return $class;
    }

    /**
     * @return class-string<BaseReportController>|null
     */
    public function find(string $report): ?string
    {
        $report = trim($report);

        if ($report === '') {
            return null;
        }

        $registered = $this->registry->find($report);

        if ($registered !== null) {
            return $this->validClass($registered);
        }

        if (str_contains($report, '::')) {
            return $this->resolveModule($report);
        }

        if (! preg_match('/^[a-zA-Z0-9.\-]+$/', $report)) {
            return null;
        }

        $livewireNamespace = config('livewire.class_namespace', 'App\\Livewire');

        return $this->validClass($livewireNamespace.'\\'.$this->namespaceSegment().$this->classPath($report).$this->suffix());
    }

    /**
     * @return class-string<BaseReportController>|null
     */
    protected function resolveModule(string $report): ?string
    {
        [$moduleName, $reportPath] = explode('::', $report, 2);

        if (! preg_match('/^[a-zA-Z0-9\-]+$/', $moduleName) || ! preg_match('/^[a-zA-Z0-9.\-]+$/', $reportPath)) {
            return null;
        }

        $moduleNamespace = config('modules.namespace', 'Modules');
        $moduleLivewireNamespace = config('modules-livewire.namespace', 'Livewire');

        $class = "{$moduleNamespace}\\".Str::studly($moduleName)."\\{$moduleLivewireNamespace}\\".$this->namespaceSegment().$this->classPath($reportPath).$this->suffix();

        return $this->validClass($class);
    }

    protected function classPath(string $report): string
    {
        return collect(explode('.', $report))
            ->map(fn (string $segment) => Str::studly($segment))
            ->implode('\\');
    }

    protected function namespaceSegment(): string
    {
        $segment = (string) config('i-reports.report_namespace', '');

        return $segment === '' ? '' : rtrim($segment, '\\').'\\';
    }

    protected function suffix(): string
    {
        return (string) config('i-reports.report_suffix', '');
    }

    /**
     * @return class-string<BaseReportController>|null
     */
    protected function validClass(string $class): ?string
    {
        if (class_exists($class) && is_subclass_of($class, BaseReportController::class)) {
            /** @var class-string<BaseReportController> $class */
            return $class;
        }

        return null;
    }
}
