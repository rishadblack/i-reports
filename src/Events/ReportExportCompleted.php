<?php

namespace Rishadblack\IReports\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportExportCompleted
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $request  The report request (report, filters, search, sort, export)
     */
    public function __construct(
        public array $request,
        public string $format,
        public string $disk,
        public string $path,
        public int|string|null $userId = null,
    ) {}
}
