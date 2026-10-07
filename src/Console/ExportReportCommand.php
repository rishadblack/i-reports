<?php

namespace Rishadblack\IReports\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Rishadblack\IReports\Exports\ReportExporter;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Jobs\ExportReportJob;
use Rishadblack\IReports\Services\ReportResolver;
use Rishadblack\IReports\Support\ReportContext;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Generate a report file from the console. Schedule it for recurring deliveries.
 */
#[AsCommand(name: 'i-reports:export')]
class ExportReportCommand extends Command
{
    protected $signature = 'i-reports:export
        {report : Report name, e.g. users}
        {--format=xlsx : csv, xlsx or pdf}
        {--filter=* : Filter values as key=value (repeatable)}
        {--search= : Search term}
        {--sort= : Sort column name}
        {--direction=asc : asc or desc}
        {--disk= : Storage disk (defaults to i-reports.queue.disk)}
        {--path= : Directory on the disk (defaults to i-reports.queue.path)}
        {--user= : Run as this user id (for scoped builders)}
        {--queue : Dispatch a job instead of exporting now}';

    protected $description = 'Export a report to csv, xlsx or pdf and store it on a disk';

    public function handle(ReportResolver $resolver, ReportExporter $exporter, ReportContext $context): int
    {
        $format = strtolower((string) $this->option('format'));

        if (! in_array($format, ReportExporter::FORMATS, true)) {
            $this->error('Format must be csv, xlsx or pdf.');

            return self::FAILURE;
        }

        $filters = [];

        foreach ((array) $this->option('filter') as $pair) {
            if (! is_string($pair) || ! str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $filters[trim($key)] = str_contains($value, ',') ? explode(',', $value) : $value;
        }

        $request = new RequestHelper([
            'report' => (string) $this->argument('report'),
            'filters' => $filters,
            'search' => (string) $this->option('search'),
            'sort_field' => $this->option('sort'),
            'sort_direction' => (string) $this->option('direction'),
            'export' => $format,
        ]);

        $userId = $this->option('user');

        if ($this->option('queue')) {
            ExportReportJob::dispatch($request->toArray(), $format, $userId, $this->option('disk'), $this->option('path'));
            $this->info('Export queued.');

            return self::SUCCESS;
        }

        if ($userId !== null) {
            Auth::onceUsingId($userId);
        }

        $context->reset();
        $request->storeGlobally();

        $report = app($resolver->resolve($request->getReport()));

        if (! $report->authorize()) {
            $this->error('Not authorized to export this report.');

            return self::FAILURE;
        }

        $report->publishToContext();

        $path = $exporter->store($report, $format, $this->option('disk'), $this->option('path'));

        $this->info("Export written to [{$path}] on disk [".($this->option('disk') ?: config('i-reports.queue.disk', 'local')).'].');

        return self::SUCCESS;
    }
}
