<?php

namespace Rishadblack\IReports;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Rishadblack\IReports\Helpers\ReportHelper;
use Rishadblack\IReports\Http\Livewire\ReportViewer;

class IReportsServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        Blade::componentNamespace('Rishadblack\\IReports\\View\\Components', 'i-reports');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'i-reports');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'i-reports');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        // View::composer('i-reports::*');

        Livewire::component('i-reports.report-viewer', ReportViewer::class);

        View::composer('i-reports::*', function ($view) {
            $view->with('export', ReportHelper::getExport());
            $view->with('columns', ReportHelper::getColumns());
            $view->with('report_title', ReportHelper::getReportTitle());
            $view->with('header_title', ReportHelper::getHeaderTitle());
        });

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/i-reports.php', 'i-reports');

        // Register the service the package provides.
        $this->app->singleton('i-reports', function ($app) {
            return new IReports;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['i-reports'];
    }

    /**
     * Console-specific booting.
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/i-reports.php' => config_path('i-reports.php'),
        ], 'i-reports.config');

        // Publishing the views.
        $this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/rishadblack'),
        ], 'i-reports.views');

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/rishadblack'),
        ], 'i-reports.assets');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/rishadblack'),
        ], 'i-reports.lang');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
