<?php

use App\Livewire\Reports\CustomersReport;
use App\Livewire\Reports\PlainCustomersReport;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    seedCustomers();
});

it('falls back to the package default view when the report has none', function () {
    expect((new PlainCustomersReport)->getViewName())->toBe('i-reports::default-report');
});

it('keeps the report\'s own view when it exists', function () {
    expect((new CustomersReport)->getViewName())->toBe('livewire.reports.customers-report');
});

it('renders a viewless report through the default view', function () {
    $this->get(reportUrl(['per_page' => 10], 'plain-customers'))
        ->assertOk()
        ->assertSee('Alice')
        ->assertSee('Bob')
        ->assertSee('Charlie');
});

it('shows a viewless report in the viewer', function () {
    Livewire::test('i-reports.report-viewer', ['report' => 'plain-customers'])
        ->assertSet('total', 3)
        ->assertSee('Plain Customers');
});

it('exports a viewless report to csv', function () {
    $content = $this->get(reportUrl(['export' => 'csv'], 'plain-customers'))
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain('Name')
        ->toContain('Alice');
});

it('prints a viewless report with the branded layout', function () {
    $this->get(reportUrl(['export' => 'print'], 'plain-customers'))
        ->assertOk()
        ->assertSee('Plain Customers');
});

it('prefers an app view over the default once one is created', function () {
    // The suite's view path is the fixtures directory (see TestCase).
    $path = __DIR__.'/../Fixtures/resources/views/livewire/reports/plain-customers-report.blade.php';

    try {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '<x-i-reports::layout>CUSTOMIZED MARKER</x-i-reports::layout>');

        expect((new PlainCustomersReport)->getViewName())->toBe('livewire.reports.plain-customers-report');

        $this->get(reportUrl(report: 'plain-customers'))
            ->assertOk()
            ->assertSee('CUSTOMIZED MARKER');
    } finally {
        File::delete($path);
    }
});
