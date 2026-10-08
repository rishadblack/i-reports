<?php

namespace Rishadblack\IReports\Console\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Shared name/class/view resolution for the scaffolding commands, mirroring
 * the runtime conventions in Traits\Helpers.
 */
trait ResolvesReportScaffolding
{
    protected function isValidReportName(string $name): bool
    {
        return preg_match('/^[a-zA-Z0-9.\-]+$/', $name) === 1;
    }

    /**
     * @return array{namespace: string, class: string, fullClass: string, model: string, title: string}
     */
    protected function reportClassParts(string $name, ?string $model = null): array
    {
        $segments = $this->reportSegments($name);
        $suffix = (string) config('i-reports.report_suffix', '');
        $className = $segments->last().$suffix;
        $subNamespace = $segments->slice(0, -1)->implode('\\');
        $livewireNamespace = (string) config('livewire.class_namespace', 'App\\Livewire');
        $reportNamespace = trim((string) config('i-reports.report_namespace', ''), '\\');
        $namespace = collect([$livewireNamespace, $reportNamespace, $subNamespace])->filter()->implode('\\');

        $modelName = $model ?: Str::studly(Str::singular($segments->last()));

        return [
            'namespace' => $namespace,
            'class' => $className,
            'fullClass' => $namespace.'\\'.$className,
            'model' => str_contains($modelName, '\\') ? $modelName : 'App\\Models\\'.$modelName,
            'title' => Str::title(str_replace('-', ' ', Str::kebab($segments->last()))),
        ];
    }

    /**
     * @return array{name: string, path: string}
     */
    protected function reportViewParts(string $name): array
    {
        $livewireNamespace = (string) config('livewire.class_namespace', 'App\\Livewire');
        $fullClass = $this->reportClassParts($name)['fullClass'];

        $parts = collect(explode('\\', Str::after($fullClass, $livewireNamespace.'\\')))
            ->prepend('livewire')
            ->map(fn (string $part) => Str::kebab($part));

        return [
            'name' => $parts->implode('.'),
            'path' => resource_path('views/'.$parts->implode('/').'.blade.php'),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    protected function reportSegments(string $name): Collection
    {
        return collect(explode('.', $name))->map(fn (string $segment) => Str::studly($segment));
    }
}
