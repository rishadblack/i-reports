<?php

namespace Rishadblack\IReports\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Rishadblack\IReports\Events\ReportExportCompleted;
use Rishadblack\IReports\Exports\ReportExporter;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Models\QueuedExport;
use Rishadblack\IReports\Services\ReportResolver;
use Rishadblack\IReports\Support\ReportContext;
use Throwable;

/**
 * Generates an export in the background and stores it on a disk. When an export record id
 * is given, its status moves queued → processing → ready (or failed) for the viewer.
 */
class ExportReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $request  Report request as produced by RequestHelper::toArray()
     */
    public function __construct(
        public array $request,
        public string $format,
        public int|string|null $userId = null,
        public ?string $disk = null,
        public ?string $directory = null,
        public ?int $exportId = null,
    ) {
        $connection = config('i-reports.queue.connection');
        $queue = config('i-reports.queue.queue');

        if ($connection) {
            $this->onConnection($connection);
        }

        if ($queue) {
            $this->onQueue($queue);
        }
    }

    public function handle(ReportResolver $resolver, ReportExporter $exporter, ReportContext $context): void
    {
        $record = $this->record();
        $record?->markProcessing();

        if ($this->userId !== null && Auth::guest()) {
            Auth::onceUsingId($this->userId);
        }

        $request = new RequestHelper($this->request);
        $request->setExport($this->format);
        $context->reset();
        $request->storeGlobally();

        $report = app($resolver->resolve($request->getReport()));

        if (! $report->authorize()) {
            $record?->markFailed('Not authorized to export this report.');

            return;
        }

        $report->publishToContext();

        $disk = $this->disk ?? $record->disk ?? (string) config('i-reports.queue.disk', 'local');
        $path = $exporter->store($report, $this->format, $disk, $this->directory);

        $record?->markReady($path);

        ReportExportCompleted::dispatch($request->toArray(), $this->format, $disk, $path, $this->userId);
    }

    public function failed(?Throwable $exception): void
    {
        $this->record()?->markFailed($exception ?? 'The export failed.');
    }

    protected function record(): ?QueuedExport
    {
        return $this->exportId === null ? null : QueuedExport::query()->find($this->exportId);
    }
}
