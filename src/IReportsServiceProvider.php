<?php

namespace Rishadblack\IReports;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Rishadblack\IReports\Console\ExportReportCommand;
use Rishadblack\IReports\Console\ListReportsCommand;
use Rishadblack\IReports\Console\MakeReportCommand;
use Rishadblack\IReports\Console\PruneExportsCommand;
use Rishadblack\IReports\Contracts\ReportRenderer;
use Rishadblack\IReports\Http\Livewire\ReportViewer;
use Rishadblack\IReports\Renderers\BladeRenderer;
use Rishadblack\IReports\Support\ReportContext;

class IReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/i-reports.php', 'i-reports');

        $this->app->singleton(IReports::class, fn () => new IReports);
        $this->app->alias(IReports::class, 'i-reports');

        $this->app->scoped(ReportContext::class, fn () => new ReportContext);

        $this->app->bind(ReportRenderer::class, function ($app) {
            $renderer = config('i-reports.renderer', BladeRenderer::class);

            return $app->make(is_string($renderer) && class_exists($renderer) ? $renderer : BladeRenderer::class);
        });
    }

    public function boot(): void
    {
        Blade::componentNamespace('Rishadblack\\IReports\\View\\Components', 'i-reports');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'i-reports');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        $migrations = array_filter([
            config('i-reports.presets.enabled') ? __DIR__.'/../database/migrations/2026_01_01_000000_create_i_reports_presets_table.php' : null,
            config('i-reports.queue.enabled') ? __DIR__.'/../database/migrations/2026_01_02_000000_create_i_reports_exports_table.php' : null,
        ]);

        if (count($migrations) > 0) {
            $this->loadMigrationsFrom($migrations);
        }

        Livewire::component('i-reports.report-viewer', ReportViewer::class);

        // Private channel for live export status (queue.realtime = broadcast): a user may only
        // listen to their own exports. Harmless when broadcasting is not used.
        Broadcast::channel(
            rtrim((string) config('i-reports.queue.broadcast_channel', 'i-reports.exports'), '.').'.{userId}',
            fn ($user, $userId): bool => (string) $user->getAuthIdentifier() === (string) $userId,
        );

        View::composer('i-reports::*', function ($view) {
            $context = $this->app->make(ReportContext::class);

            $view->with('export', $context->getExport());
            $view->with('columns', $view->getData()['columns'] ?? $context->getColumns());
            $view->with('report_title', $view->getData()['report_title'] ?? $context->getReportTitle());
            $view->with('header_title', $view->getData()['header_title'] ?? $context->getHeaderTitle());
        });

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return ['i-reports', IReports::class, ReportContext::class, ReportRenderer::class];
    }

    protected function bootForConsole(): void
    {
        $this->publishes([
            __DIR__.'/../config/i-reports.php' => config_path('i-reports.php'),
        ], 'i-reports.config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/i-reports'),
        ], 'i-reports.views');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'i-reports.migrations');

        $this->commands([
            MakeReportCommand::class,
            ExportReportCommand::class,
            ListReportsCommand::class,
            PruneExportsCommand::class,
        ]);
    }
}
