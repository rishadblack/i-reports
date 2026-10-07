<?php

namespace Rishadblack\IReports\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Rishadblack\IReports\Models\QueuedExport;
use Rishadblack\IReports\Support\Runtime;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads a finished background export. Only its owner (user, or browser session for guests) can.
 */
class ExportDownloadController
{
    public function __invoke(int $export): StreamedResponse
    {
        $record = QueuedExport::query()->ownedBy(QueuedExport::currentOwner())->find($export);

        abort_if($record === null, 404);
        abort_unless($record->isReady() && $record->path !== null && Storage::disk($record->disk)->exists($record->path), 404, 'The export file is not available.');

        Runtime::disableDebugbar();

        return Storage::disk($record->disk)->download($record->path, $record->file_name ?? basename($record->path));
    }
}
