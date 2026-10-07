<?php

namespace Rishadblack\IReports\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireServiceProvider;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Rishadblack\IReports\IReportsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            ExcelServiceProvider::class,
            IReportsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('livewire.class_namespace', 'App\Livewire');
        $app['config']->set('i-reports.report_namespace', 'Reports');
        $app['config']->set('i-reports.report_suffix', 'Report');
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/resources/views']);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city');
        });
    }
}
