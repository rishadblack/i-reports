<?php

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Rishadblack\IReports\Http\Livewire\ReportViewer;
use Rishadblack\IReports\Jobs\ExportReportJob;
use Rishadblack\IReports\Services\ReportTokenManager;

beforeEach(function () {
    seedCustomers();
});

/**
 * Resolve the token from a dispatched export URL.
 *
 * @return array<string, mixed>
 */
function tokenData(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return ReportTokenManager::resolve((string) $query['token']) ?? [];
}

/*
|--------------------------------------------------------------------------
| Mounting
|--------------------------------------------------------------------------
*/

it('is registered with livewire and loads the report settings', function () {
    Livewire::test('i-reports.report-viewer', ['report' => 'customers'])
        ->assertSet('per_page', 2)
        ->assertSet('total', 3)
        ->assertSet('last_page', 2)
        ->assertSet('mode', 'iframe')
        ->assertSet('filter_list.0.name', 'city')
        ->assertSet('filter_list.7.depends_on', 'country_id')
        ->assertSet('filters.region', 'north')
        ->assertSee('Showing 1–2 of 3 results')
        ->assertSeeHtml('/view?token=');
});

it('includes the default page size in the page size list', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSet('per_page_list', [2, 25, 50, 100, 150, 200, 250, 500]);
});

it('aborts with 403 when the report refuses authorization', function () {
    Livewire::test(ReportViewer::class, ['report' => 'secret'])->assertForbidden();
});

it('aborts with 404 for an unknown report', function () {
    Livewire::test(ReportViewer::class, ['report' => 'nope'])->assertNotFound();
});

it('falls back to the configured viewer mode', function () {
    config()->set('i-reports.viewer_mode', 'inline');

    Livewire::test(ReportViewer::class, ['report' => 'customers'])->assertSet('mode', 'inline');
    Livewire::test(ReportViewer::class, ['report' => 'customers', 'mode' => 'bogus'])->assertSet('mode', 'inline');
    Livewire::test(ReportViewer::class, ['report' => 'customers', 'mode' => 'iframe'])->assertSet('mode', 'iframe');
});

/*
|--------------------------------------------------------------------------
| Locked state and input validation
|--------------------------------------------------------------------------
*/

it('locks the properties the browser must not change', function (string $property, mixed $value) {
    expect(fn () => Livewire::test(ReportViewer::class, ['report' => 'customers'])->set($property, $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'report' => ['report', 'secret'],
    'filter_extended_view' => ['filter_extended_view', 'admin.secrets'],
    'filter_list' => ['filter_list', []],
    'per_page_list' => ['per_page_list', [100000]],
    'mode' => ['mode', 'inline'],
    'presets_enabled' => ['presets_enabled', true],
]);

it('rejects a page size that is not in the list', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('per_page', 999999)
        ->assertSet('per_page', 2)
        ->set('per_page', 50)
        ->assertSet('per_page', 50)
        ->assertSet('page', 1);
});

it('clamps the page into range', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('page', 50)
        ->call('goToPage')
        ->assertSet('page', 2)
        ->set('page', -3)
        ->call('goToPage')
        ->assertSet('page', 1);
});

it('trims and limits the search term', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('search', '  '.str_repeat('a', 300).'  ')
        ->assertSet('search', str_repeat('a', 255));
});

it('rejects sort fields that are not sortable and bad directions', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('sort_field', 'secret')
        ->assertSet('sort_field', null)
        ->set('sort_field', 'amount')
        ->assertSet('sort_field', 'amount')
        ->set('sort_direction', 'sideways')
        ->assertSet('sort_direction', 'asc');
});

/*
|--------------------------------------------------------------------------
| Pagination actions
|--------------------------------------------------------------------------
*/

it('moves between pages within bounds', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('nextPage')
        ->assertSet('page', 2)
        ->call('nextPage')
        ->assertSet('page', 2)
        ->call('prevPage')
        ->assertSet('page', 1)
        ->call('prevPage')
        ->assertSet('page', 1)
        ->call('lastPage')
        ->assertSet('page', 2)
        ->call('firstPage')
        ->assertSet('page', 1)
        ->assertSee('Showing 1–2 of 3 results')
        ->call('lastPage')
        ->assertSee('Showing 3–3 of 3 results');
});

it('carries page, page size and sort in the iframe token', function () {
    $component = Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('per_page', 50)
        ->call('sortBy', 'amount')
        ->call('nextPage');

    $data = tokenData($component->instance()->reportUrl());

    expect($data)->toMatchArray(['report' => 'customers', 'per_page' => 50, 'page' => 1, 'sort_field' => 'amount', 'sort_direction' => 'asc', 'export' => 'view', 'total' => 3]);
});

/*
|--------------------------------------------------------------------------
| Search, filters, reset
|--------------------------------------------------------------------------
*/

it('searches and returns to the first page', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('nextPage')
        ->set('search', '  bo ')
        ->call('searchReport')
        ->assertSet('search', 'bo')
        ->assertSet('page', 1)
        ->assertSet('total', 1);
});

it('updates the totals when filters are submitted', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('nextPage')
        ->set('filters.city', 'Khulna')
        ->call('filterSubmit')
        ->assertSet('page', 1)
        ->assertSet('total', 1)
        ->assertSet('last_page', 1)
        ->assertSet('show_filters', false)
        ->assertDispatched('i-reports:filters-applied');
});

it('resets the filters but keeps their defaults', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('filters.city', 'Khulna')
        ->set('filters.region', 'south')
        ->call('filterReset')
        ->assertSet('filters.city', null)
        ->assertSet('filters.region', 'north')
        ->assertSet('total', 3);
});

it('resets the whole viewer', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('search', 'bo')
        ->set('filters.city', 'Khulna')
        ->set('per_page', 50)
        ->call('sortBy', 'amount')
        ->call('resetReport')
        ->assertSet('search', '')
        ->assertSet('filters.city', null)
        ->assertSet('filters.region', 'north')
        ->assertSet('per_page', 2)
        ->assertSet('sort_field', null)
        ->assertSet('page', 1)
        ->assertSet('total', 3);
});

it('clears dependent filters when their parent changes', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('filters.region', 'north')
        ->set('filters.country_id', '2')
        ->assertSet('filters.region', null)
        ->assertSet('filters.country_id', '2');
});

/*
|--------------------------------------------------------------------------
| Sorting actions
|--------------------------------------------------------------------------
*/

it('sorts by sortable columns and toggles the direction', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('nextPage')
        ->call('sortBy', 'amount')
        ->assertSet('sort_field', 'amount')
        ->assertSet('sort_direction', 'asc')
        ->assertSet('page', 1)
        ->call('sortBy', 'amount')
        ->assertSet('sort_direction', 'desc')
        ->call('sortBy', 'name')
        ->assertSet('sort_field', 'name')
        ->assertSet('sort_direction', 'asc')
        ->call('sortBy', 'secret')
        ->assertSet('sort_field', 'name')
        ->call('clearSort')
        ->assertSet('sort_field', null)
        ->assertSet('sort_direction', 'asc');
});

it('lists the sortable columns in the toolbar', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSeeHtml('<option value="amount">Amount</option>')
        ->assertDontSeeHtml('<option value="active">');
});

/*
|--------------------------------------------------------------------------
| Export actions
|--------------------------------------------------------------------------
*/

it('dispatches an export url for every format and clears the selection', function (string $format) {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('filters.city', 'Dhaka')
        ->set('export', $format)
        ->assertSet('export', '')
        ->assertDispatched('exportEvent', function (string $name, array $params) use ($format) {
            $data = tokenData($params['url']);

            return $data['export'] === $format
                && $data['filters'] === ['region' => 'north', 'city' => 'Dhaka']
                && $data['report'] === 'customers'
                && $data['total'] === null;
        });
})->with(['print', 'pdf', 'xlsx', 'csv']);

it('ignores unknown export formats', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('exportAs', 'exe')
        ->assertNotDispatched('exportEvent')
        ->assertSet('export', '');
});

it('queues an export on demand', function () {
    Queue::fake();

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('filters.city', 'Khulna')
        ->call('queueExport', 'csv')
        ->assertDispatched('exportQueued')
        ->assertSet('export_message', fn (string $message) => str_contains($message, 'CSV'));

    Queue::assertPushed(ExportReportJob::class, fn (ExportReportJob $job) => $job->format === 'csv' && $job->request['filters']['city'] === 'Khulna' && $job->request['report'] === 'customers');
});

it('never queues print and ignores unknown formats for queueing', function () {
    Queue::fake();

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('queueExport', 'print')
        ->call('queueExport', 'exe')
        ->assertNotDispatched('exportQueued');

    Queue::assertNothingPushed();
});

it('queues automatically above the configured threshold', function () {
    Queue::fake();
    config()->set('i-reports.queue.enabled', true);
    config()->set('i-reports.queue.thresholds.xlsx', 2);

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('exportAs', 'xlsx')
        ->assertNotDispatched('exportEvent')
        ->assertDispatched('exportQueued')
        ->call('exportAs', 'print')
        ->assertDispatched('exportEvent');

    Queue::assertPushed(ExportReportJob::class, 1);
});

it('does not queue below the threshold', function () {
    Queue::fake();
    config()->set('i-reports.queue.enabled', true);
    config()->set('i-reports.queue.threshold', 100);

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('exportAs', 'xlsx')
        ->assertDispatched('exportEvent');

    Queue::assertNothingPushed();
});

/*
|--------------------------------------------------------------------------
| Inline mode and themes
|--------------------------------------------------------------------------
*/

it('renders the report inline with one count and one select per interaction', function () {
    DB::enableQueryLog();

    $component = Livewire::test(ReportViewer::class, ['report' => 'customers', 'mode' => 'inline'])
        ->assertSee('Alice')
        ->assertSee('BDT 250.50')
        ->assertDontSeeHtml('<iframe');

    $queries = collect(DB::getQueryLog())->pluck('query')->map(fn ($sql) => strtolower($sql));

    expect($queries->filter(fn ($sql) => str_contains($sql, 'count('))->count())->toBe(1)
        ->and($queries->filter(fn ($sql) => str_starts_with($sql, 'select') && str_contains($sql, 'from "customers"') && ! str_contains($sql, 'count(') && ! str_contains($sql, 'sum('))->count())->toBe(1);

    $component->call('sortBy', 'name')->assertSeeHtml('&#9650;')->assertSeeHtml("wire:click.prevent=\"sortBy('name')\"");
});

it('renders the bootstrap 5.3 card with title, export menu, pagination and filter dialog', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSee('Customer List')
        ->assertSee('3 records')
        ->assertSeeHtml('class="i-reports-viewer card')
        ->assertSeeHtml('id="i-reports-export-button"')
        ->assertSeeHtml('data-export="pdf"')
        ->assertSeeHtml("wire:click=\"exportAs('xlsx')\"")
        ->assertSeeHtml('class="pagination pagination-sm mb-0"')
        ->assertSeeHtml('wire:click="goTo(2)"')
        ->assertSeeHtml('class="modal fade show" x-bind:class="{ \'d-block\': filtersOpen }"')
        ->assertSeeHtml('wire:model="filters.city"')
        ->assertSeeHtml('wire:model="filters.joined.from"')
        ->assertSeeHtml('wire:model="filters.amount.to"')
        ->assertSeeHtml('<option value="0">No</option>')
        ->assertSeeHtml('wire:loading.flex');
});

it('shows active filters as chips and removes them one by one', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('filters.cities', ['Khulna'])
        ->set('filters.amount', ['from' => '90'])
        ->set('search', 'li')
        ->call('filterSubmit')
        ->assertSet('activeFilterCount', 3)
        ->assertSee('Cities:')
        ->assertSee('Khulna')
        ->assertSee('from 90')
        ->assertSee('Search:')
        ->assertSeeHtml("wire:click=\"removeFilter('cities')\"")
        ->call('removeFilter', 'cities')
        ->assertSet('filters.cities', null)
        ->assertSet('page', 1)
        ->call('removeFilter', '__search')
        ->assertSet('search', '')
        ->call('sortBy', 'amount')
        ->assertSee('Sorted by:')
        ->call('removeFilter', '__sort')
        ->assertSet('sort_field', null);
});

it('removing a parent filter clears its dependents', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('filters.country_id', '1')
        ->set('filters.region', 'north')
        ->call('removeFilter', 'country_id')
        ->assertSet('filters.region', null);
});

it('builds a page window with gaps', function () {
    foreach (range(1, 17) as $index) {
        Customer::create(['name' => 'Extra '.$index, 'city' => 'Dhaka']);
    }

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSet('last_page', 10)
        ->call('goTo', 5)
        ->assertSet('page', 5)
        ->assertSet('pageWindow', [1, null, 3, 4, 5, 6, 7, null, 10])
        ->call('goTo', 99)
        ->assertSet('page', 10)
        ->assertSet('pageWindow', [1, null, 8, 9, 10]);
});

it('renders the extended filter view passed at mount', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers', 'filter_extended_view' => 'partials.report-header'])
        ->assertSee('Company Header');
});

it('respects the toolbar visibility settings', function () {
    config()->set('i-reports.show_search', false);
    config()->set('i-reports.show_export_button', false);
    config()->set('i-reports.show_pagination', false);

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertDontSeeHtml('id="i-reports-search"')
        ->assertDontSeeHtml('id="i-reports-export-button"')
        ->assertDontSee('Showing');
});
