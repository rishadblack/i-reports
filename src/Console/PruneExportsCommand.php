<?php

namespace Rishadblack\IReports\Console;

use Illuminate\Console\Command;
use Rishadblack\IReports\Models\QueuedExport;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Delete background exports (records and files) older than the retention period. Schedule it daily.
 */
#[AsCommand(name: 'i-reports:prune-exports')]
class PruneExportsCommand extends Command
{
    protected $signature = 'i-reports:prune-exports {--days= : Keep exports newer than this many days (default i-reports.queue.keep_days)}';

    protected $description = 'Delete old background report exports and their files';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('i-reports.queue.keep_days', 7));
        $count = 0;

        QueuedExport::query()
            ->where('created_at', '<', now()->subDays(max(0, $days)))
            ->chunkById(200, function ($exports) use (&$count) {
                foreach ($exports as $export) {
                    $export->deleteWithFile();
                    $count++;
                }
            });

        $this->components->info("Deleted {$count} export(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
