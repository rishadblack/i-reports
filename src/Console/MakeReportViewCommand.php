<?php

namespace Rishadblack\IReports\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Rishadblack\IReports\Console\Concerns\ResolvesReportScaffolding;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'i-reports:view')]
class MakeReportViewCommand extends Command
{
    use ResolvesReportScaffolding;

    protected $signature = 'i-reports:view
        {name : Report name in dot notation, e.g. users or sales.daily}
        {--force : Overwrite an existing view}';

    protected $description = "Copy the package's default report view into the application so the report can be customized";

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));

        if (! $this->isValidReportName($name)) {
            $this->error('Report names may contain only letters, digits, dots and dashes.');

            return self::FAILURE;
        }

        $view = $this->reportViewParts($name);

        if ($this->files->exists($view['path']) && ! $this->option('force')) {
            $this->error("View already exists: {$view['path']}");

            return self::FAILURE;
        }

        $published = base_path('stubs/i-reports/report-view.stub');
        $stub = $this->files->exists($published) ? $published : __DIR__.'/../../stubs/report-view.stub';

        $this->files->ensureDirectoryExists(dirname($view['path']));
        $this->files->put($view['path'], $this->files->get($stub));

        $this->components->info("Created {$view['path']}");
        $this->line("The report [{$name}] now renders this view instead of the package default. Customize it freely.");

        return self::SUCCESS;
    }
}
