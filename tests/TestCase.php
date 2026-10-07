<?php

namespace Rishadblack\IReports\Tests;

use App\Models\User;
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
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('filesystems.disks.exports', ['driver' => 'local', 'root' => storage_path('app/exports-test')]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('livewire.class_namespace', 'App\Livewire');
        $app['config']->set('i-reports.report_namespace', 'Reports');
        $app['config']->set('i-reports.report_suffix', 'Report');
        $app['config']->set('i-reports.presets.enabled', true);
        $app['config']->set('i-reports.queue.enabled', true);
        $app['config']->set('i-reports.queue.disk', 'exports');
        $app['config']->set('i-reports.route_throttle', '60,1');
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/resources/views']);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city');
            $table->foreignId('country_id')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->date('joined_at')->nullable();
            $table->string('secret')->nullable();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
