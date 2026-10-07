<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Blade;
use Rishadblack\IReports\Helpers\ReportHelper;
use Rishadblack\IReports\Support\ReportContext;
use Rishadblack\IReports\Views\Column;

function contextWithColumns(string $export = 'view'): ReportContext
{
    $context = app(ReportContext::class);
    $context->reset();
    $context->setRequestData(['export' => $export, 'sort_field' => 'name', 'sort_direction' => 'desc']);
    $context->setColumns([
        Column::make('Name', 'name')->sortable(),
        Column::make('Amount', 'amount')->money('BDT')->sum(),
        Column::make('Joined', 'joined_at')->hideIn('pdf'),
    ]);
    $context->setReportTitle('Ctx Report');

    return $context;
}

it('is scoped per request so rendered-column tracking cannot leak', function () {
    contextWithColumns();
    $row = new Customer(['name' => 'Alice', 'amount' => 5]);

    $first = Blade::render('<x-i-reports::td name="name" :row="$row" />', ['row' => $row]);
    $second = Blade::render('<x-i-reports::td name="name" :row="$row" />', ['row' => $row]);

    expect($first)->toContain('Alice')->and(trim($second))->toBe('');

    app()->forgetScopedInstances();
    contextWithColumns();

    expect(Blade::render('<x-i-reports::td name="name" :row="$row" />', ['row' => $row]))->toContain('Alice');
});

it('resets the rendered columns at every row', function () {
    contextWithColumns();
    $row = new Customer(['name' => 'Alice', 'amount' => 5]);

    $html = Blade::render(<<<'BLADE'
        <x-i-reports::tr><x-i-reports::th name="name" /><x-i-reports::th name="name" /></x-i-reports::tr>
        <x-i-reports::tr><x-i-reports::th name="name" /></x-i-reports::tr>
        <x-i-reports::tr><x-i-reports::td name="name" :row="$row" /><x-i-reports::td name="name" :row="$row" /></x-i-reports::tr>
        <x-i-reports::tr><x-i-reports::td name="name" :row="$row" /></x-i-reports::tr>
    BLADE, ['row' => $row]);

    expect(substr_count($html, '<th'))->toBe(2)
        ->and(substr_count($html, '<td'))->toBe(2);
});

it('escapes td values unless html is allowed', function () {
    contextWithColumns();

    expect(Blade::render('<x-i-reports::td :value="$v" />', ['v' => '<i>x</i>']))->toContain('&lt;i&gt;x&lt;/i&gt;')
        ->and(Blade::render('<x-i-reports::td :value="$v" :html="true" />', ['v' => '<i>x</i>']))->toContain('<i>x</i>')
        ->and(Blade::render('<x-i-reports::td><i>slot</i></x-i-reports::td>'))->toContain('<i>slot</i>');
});

it('renders custom and skipped cells by name', function () {
    contextWithColumns();
    $row = new Customer(['name' => 'Alice', 'amount' => 5]);

    expect(Blade::render('<x-i-reports::td name="name" custom="other" :row="$row" />', ['row' => $row]))->toBe('')
        ->and(Blade::render('<x-i-reports::td name="name" skip="1" :row="$row" />', ['row' => $row]))->toBe('')
        ->and(Blade::render('<x-i-reports::th name="name" custom="name" />'))->toContain('Name');
});

it('hides columns per output through hideIn', function () {
    contextWithColumns('pdf');

    expect(Blade::render('<x-i-reports::th name="joined_at" />'))->toBe('')
        ->and(Blade::render('<x-i-reports::th name="name" />'))->toContain('Name');
});

it('renders sort links only where sorting works', function () {
    contextWithColumns('view');
    expect(Blade::render('<x-i-reports::th name="name" />'))->toContain('postMessage')->toContain('&#9660;');

    contextWithColumns('inline');
    expect(Blade::render('<x-i-reports::th name="name" />'))->toContain('wire:click.prevent="sortBy(\'name\')"');

    contextWithColumns('pdf');
    expect(Blade::render('<x-i-reports::th name="name" />'))->not->toContain('sortBy')->not->toContain('postMessage');

    contextWithColumns('view');
    expect(Blade::render('<x-i-reports::th name="name" :sortable="false" />'))->not->toContain('postMessage');
});

it('renders the page break per output', function (string $export, string $expected) {
    contextWithColumns($export);

    expect(trim(Blade::render('<x-i-reports::page-break />')))->toBe($expected);
})->with([
    ['pdf', '<pagebreak />'],
    ['print', '<div style="page-break-after: always;"></div>'],
    ['view', ''],
    ['xlsx', ''],
]);

it('renders the aggregates row from the context and nothing without aggregates', function () {
    $context = contextWithColumns();

    expect(trim(Blade::render('<x-i-reports::aggregates />')))->toBe('');

    $context->put('aggregates', ['amount' => 12.5]);

    expect(Blade::render('<x-i-reports::aggregates label="Sum" />'))->toContain('Sum')->toContain('BDT 12.50');
});

it('renders the layout document only for page outputs', function (string $export, bool $document) {
    contextWithColumns($export);

    $html = Blade::render('<x-i-reports::layout>BODY</x-i-reports::layout>');

    expect(str_contains($html, '<!DOCTYPE html>'))->toBe($document)
        ->and($html)->toContain('BODY');
})->with([
    ['view', true],
    ['print', true],
    ['pdf', true],
    ['inline', false],
    ['xlsx', false],
    ['csv', false],
]);

it('keeps the static helper as a facade over the context', function () {
    $context = contextWithColumns();

    ReportHelper::setReportTitle('Via helper');
    ReportHelper::setRequestData(['export' => 'pdf', 'per_page' => '7', 'page' => '0', 'filters' => 'junk', 'search' => ['x'], 'sort_direction' => 'DESC']);

    expect($context->getReportTitle())->toBe('Via helper')
        ->and(ReportHelper::getExport())->toBe('pdf')
        ->and(ReportHelper::getPerPage())->toBe(7)
        ->and(ReportHelper::getPage())->toBe(1)
        ->and(ReportHelper::getFilters())->toBe([])
        ->and(ReportHelper::getSearch())->toBe('')
        ->and(ReportHelper::getSortDirection())->toBe('desc')
        ->and(ReportHelper::getColumnByName('amount'))->toBeInstanceOf(Column::class)
        ->and(ReportHelper::context())->toBe($context);
});

it('renders rows for every visible column without per-cell components', function () {
    contextWithColumns('pdf');
    $rows = [new Customer(['name' => '<b>Alice</b>', 'amount' => 5]), new Customer(['name' => 'Bob', 'amount' => 7.5])];

    $html = Blade::render('<x-i-reports::rows :rows="$rows" />', ['rows' => $rows]);

    expect(substr_count($html, '<tr'))->toBe(2)
        ->and(substr_count($html, '<td'))->toBe(4)
        ->and($html)->toContain('&lt;b&gt;Alice&lt;/b&gt;')
        ->and($html)->toContain('BDT 7.50')
        ->and($html)->toContain('text-align: right')
        ->and($html)->not->toContain('Joined');
});

it('raises the memory limit for full exports only', function () {
    seedCustomers();
    config()->set('i-reports.export_memory_limit', '2048M');
    $original = ini_get('memory_limit');
    ini_set('memory_limit', '512M');

    try {
        $this->get(reportUrl())->assertOk();
        expect(ini_get('memory_limit'))->toBe('512M');

        $this->get(reportUrl(['export' => 'print']))->assertOk();
        expect(ini_get('memory_limit'))->toBe('2048M');

        // Unlimited stays unlimited.
        ini_set('memory_limit', '-1');
        $this->get(reportUrl(['export' => 'print']))->assertOk();
        expect(ini_get('memory_limit'))->toBe('-1');
    } finally {
        ini_set('memory_limit', (string) $original);
    }
});
