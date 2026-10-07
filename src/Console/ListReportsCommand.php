<?php

namespace Rishadblack\IReports\Console;

use Illuminate\Console\Command;
use Rishadblack\IReports\IReports;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'i-reports:list')]
class ListReportsCommand extends Command
{
    protected $signature = 'i-reports:list';

    protected $description = 'List the reports registered in config or with IReports::register()';

    public function handle(IReports $registry): int
    {
        $reports = $registry->all();

        if (count($reports) === 0) {
            $this->components->info('No registered reports. Convention-based reports still resolve by name.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($reports as $name => $class) {
            $title = class_exists($class) ? app($class)->getReportTitle() : '(class missing)';
            $rows[] = [$name, $class, $title];
        }

        $this->table(['Name', 'Class', 'Title'], $rows);

        return self::SUCCESS;
    }
}
