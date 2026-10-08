<?php

namespace Rishadblack\IReports\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Rishadblack\IReports\Console\Concerns\ResolvesReportScaffolding;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:report')]
class MakeReportCommand extends Command
{
    use ResolvesReportScaffolding;

    protected $signature = 'make:report
        {name : Report name in dot notation, e.g. users or sales.daily}
        {--model= : Model class the report queries (defaults to a guess from the name)}
        {--view : Also create a Blade view to customize (without it the package default view renders the report)}
        {--no-test : Skip the Pest test}
        {--force : Overwrite existing files}';

    protected $description = 'Create a report class and a Pest test; pass --view when the report needs a custom Blade view';

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

        $class = $this->reportClassParts($name, $this->option('model'));
        $view = $this->reportViewParts($name);

        $classPath = $this->classPath($class['fullClass']);
        $testPath = base_path('tests/Feature/'.$class['class'].'Test.php');

        $replacements = [
            '{{ namespace }}' => $class['namespace'],
            '{{ class }}' => $class['class'],
            '{{ fullClass }}' => $class['fullClass'],
            '{{ model }}' => $class['model'],
            '{{ modelShort }}' => class_basename($class['model']),
            '{{ title }}' => $class['title'],
            '{{ report }}' => $name,
            '{{ view }}' => $view['name'],
        ];

        $written = [
            $this->write($classPath, 'report.stub', $replacements),
        ];

        if ($this->option('view')) {
            $written[] = $this->write($view['path'], 'report-view.stub', $replacements);
        }

        if (! $this->option('no-test')) {
            $written[] = $this->write($testPath, 'report-test.stub', $replacements);
        }

        if (in_array(false, $written, true)) {
            return self::FAILURE;
        }

        $this->info("Report [{$name}] created. Embed it with <livewire:i-reports.report-viewer report=\"{$name}\" />");

        if (! $this->option('view')) {
            $this->line("It renders with the package's default view. Customize it later with: php artisan i-reports:view {$name}");
        }

        return self::SUCCESS;
    }

    protected function classPath(string $fullClass): string
    {
        $relative = str_replace('\\', '/', Str::after($fullClass, 'App\\'));

        if (Str::startsWith($fullClass, 'App\\')) {
            return app_path($relative.'.php');
        }

        return base_path(str_replace('\\', '/', $fullClass).'.php');
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function write(string $path, string $stub, array $replacements): bool
    {
        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->error("File already exists: {$path}");

            return false;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $content = str_replace(array_keys($replacements), array_values($replacements), $this->files->get($this->stubPath($stub)));
        $this->files->put($path, $content);
        $this->components->info("Created {$path}");

        return true;
    }

    protected function stubPath(string $stub): string
    {
        $published = base_path('stubs/i-reports/'.$stub);

        return $this->files->exists($published) ? $published : __DIR__.'/../../stubs/'.$stub;
    }
}
