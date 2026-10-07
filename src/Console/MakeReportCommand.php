<?php

namespace Rishadblack\IReports\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:report')]
class MakeReportCommand extends Command
{
    protected $signature = 'make:report
        {name : Report name in dot notation, e.g. users or sales.daily}
        {--model= : Model class the report queries (defaults to a guess from the name)}
        {--no-test : Skip the Pest test}
        {--force : Overwrite existing files}';

    protected $description = 'Create a report class, its Blade view and a Pest test';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));

        if (! preg_match('/^[a-zA-Z0-9.\-]+$/', $name)) {
            $this->error('Report names may contain only letters, digits, dots and dashes.');

            return self::FAILURE;
        }

        $segments = collect(explode('.', $name))->map(fn (string $segment) => Str::studly($segment));
        $suffix = (string) config('i-reports.report_suffix', '');
        $className = $segments->last().$suffix;
        $subNamespace = $segments->slice(0, -1)->implode('\\');
        $livewireNamespace = (string) config('livewire.class_namespace', 'App\\Livewire');
        $reportNamespace = trim((string) config('i-reports.report_namespace', ''), '\\');
        $namespace = collect([$livewireNamespace, $reportNamespace, $subNamespace])->filter()->implode('\\');
        $fullClass = $namespace.'\\'.$className;

        $modelName = $this->option('model') ?: Str::studly(Str::singular($segments->last()));
        $modelClass = str_contains($modelName, '\\') ? $modelName : 'App\\Models\\'.$modelName;

        $viewParts = collect(explode('\\', Str::after($fullClass, $livewireNamespace.'\\')))
            ->prepend('livewire')
            ->map(fn (string $part) => Str::kebab($part));
        $viewName = $viewParts->implode('.');
        $viewPath = resource_path('views/'.$viewParts->implode('/').'.blade.php');

        $classPath = $this->classPath($fullClass);
        $testPath = base_path('tests/Feature/'.$className.'Test.php');

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $className,
            '{{ fullClass }}' => $fullClass,
            '{{ model }}' => $modelClass,
            '{{ modelShort }}' => class_basename($modelClass),
            '{{ title }}' => Str::title(str_replace('-', ' ', Str::kebab($segments->last()))),
            '{{ report }}' => $name,
            '{{ view }}' => $viewName,
        ];

        $written = [
            $this->write($classPath, 'report.stub', $replacements),
            $this->write($viewPath, 'report-view.stub', $replacements),
        ];

        if (! $this->option('no-test')) {
            $written[] = $this->write($testPath, 'report-test.stub', $replacements);
        }

        if (in_array(false, $written, true)) {
            return self::FAILURE;
        }

        $this->info("Report [{$name}] created. Embed it with <livewire:i-reports.report-viewer report=\"{$name}\" />");

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
