<?php

use App\Models\Customer;
use Livewire\Livewire;
use Rishadblack\IReports\Http\Livewire\ReportViewer;

beforeEach(function () {
    foreach (['Alice', 'Bob', 'Charlie', 'Dave', 'Eve'] as $index => $name) {
        Customer::create(['name' => $name, 'city' => $index % 2 ? 'Khulna' : 'Dhaka']);
    }
});

it('is registered with livewire and loads the report settings', function () {
    Livewire::test('i-reports.report-viewer', ['report' => 'customers'])
        ->assertSet('per_page', 2)
        ->assertSet('total', 5)
        ->assertSet('last_page', 3)
        ->assertSet('filter_list.0.name', 'city')
        ->assertSee('Showing 1–2 of 5 results')
        ->assertSeeHtml('/view?per_page=2&amp;page=1&amp;token=');
});

it('moves between pages within bounds', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('nextPage')
        ->assertSet('page', 2)
        ->call('lastPage')
        ->assertSet('page', 3)
        ->call('nextPage')
        ->assertSet('page', 3)
        ->call('firstPage')
        ->assertSet('page', 1)
        ->call('prevPage')
        ->assertSet('page', 1);
});

it('updates the totals when filters are submitted', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('filters.city', 'Khulna')
        ->call('filterSubmit')
        ->assertSet('total', 2)
        ->assertSet('last_page', 1);
});

it('dispatches an export url and clears the selection', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('export', 'pdf')
        ->assertDispatched('exportEvent')
        ->assertSet('export', '');
});
