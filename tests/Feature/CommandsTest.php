<?php

use App\Livewire\Reports\CustomersReport;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Rishadblack\IReports\Jobs\ExportReportJob;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    seedCustomers();
});

it('lists registered reports', function () {
    $this->artisan('i-reports:list')->expectsOutputToContain('No registered reports')->assertSuccessful();

    config()->set('i-reports.reports', ['people' => CustomersReport::class]);

    $this->artisan('i-reports:list')
        ->expectsTable(['Name', 'Class', 'Title'], [['people', CustomersReport::class, 'Customer List']])
        ->assertSuccessful();
});

it('exports a report from the console with filters, search and sort', function () {
    Storage::fake('exports');

    $this->artisan('i-reports:export', [
        'report' => 'customers',
        '--format' => 'csv',
        '--filter' => ['cities=Dhaka,Khulna', 'active=1'],
        '--sort' => 'amount',
        '--direction' => 'desc',
        '--disk' => 'exports',
        '--path' => 'cli',
    ])->assertSuccessful();

    $files = Storage::disk('exports')->files('cli');
    $content = Storage::disk('exports')->get($files[0]);

    expect($files)->toHaveCount(1)
        ->and($content)->toContain('Charlie')->toContain('Bob')->not->toContain('Alice')
        ->and(strpos($content, 'Charlie'))->toBeLessThan(strpos($content, 'Bob'));
});

it('exports pdf and xlsx from the console', function (string $format) {
    Storage::fake('exports');

    $this->artisan('i-reports:export', ['report' => 'customers', '--format' => $format, '--disk' => 'exports'])->assertSuccessful();

    expect(Storage::disk('exports')->allFiles()[0])->toEndWith('.'.$format);
})->with(['pdf', 'xlsx']);

it('queues a console export', function () {
    Queue::fake();

    $this->artisan('i-reports:export', ['report' => 'customers', '--format' => 'xlsx', '--queue' => true, '--user' => 7])
        ->expectsOutputToContain('queued')
        ->assertSuccessful();

    Queue::assertPushed(ExportReportJob::class, fn (ExportReportJob $job) => $job->format === 'xlsx' && (string) $job->userId === '7');
});

it('fails for bad formats, unknown reports and unauthorized reports', function () {
    $this->artisan('i-reports:export', ['report' => 'customers', '--format' => 'exe'])->assertFailed();
    $this->artisan('i-reports:export', ['report' => 'secret', '--format' => 'csv', '--disk' => 'exports'])->assertFailed();

    expect(fn () => $this->artisan('i-reports:export', ['report' => 'nope', '--format' => 'csv']))->toThrow(NotFoundHttpException::class);
});

it('scaffolds a report class, view and test', function () {
    $paths = [
        app_path('Livewire/Reports/OrdersReport.php'),
        resource_path('views/livewire/reports/orders-report.blade.php'),
        base_path('tests/Feature/OrdersReportTest.php'),
    ];

    try {
        $this->artisan('make:report', ['name' => 'orders', '--model' => 'App\\Models\\Customer'])->assertSuccessful();

        foreach ($paths as $path) {
            expect(File::exists($path))->toBeTrue($path);
        }

        expect(File::get($paths[0]))
            ->toContain('namespace App\Livewire\Reports;')
            ->toContain('class OrdersReport extends BaseReportController')
            ->toContain('use App\Models\Customer;')
            ->toContain('Customer::query()')
            ->and(File::get($paths[1]))->toContain('<x-i-reports::layout>')
            ->and(File::get($paths[2]))->toContain("'report' => 'orders'");

        $this->artisan('make:report', ['name' => 'orders'])->assertFailed();
        $this->artisan('make:report', ['name' => 'orders', '--force' => true, '--no-test' => true])->assertSuccessful();
        $this->artisan('make:report', ['name' => 'bad name!'])->assertFailed();
    } finally {
        foreach ($paths as $path) {
            File::delete($path);
        }
    }
});
