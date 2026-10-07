<?php

use App\Livewire\Reports\CustomersReport;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Rishadblack\IReports\Exports\ReportExporter;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Http\Livewire\ReportViewer;
use Rishadblack\IReports\Models\ReportPreset;
use Rishadblack\IReports\Services\ReportResolver;
use Rishadblack\IReports\Services\ReportTokenManager;
use Rishadblack\IReports\Support\ReportContext;
use Rishadblack\IReports\Views\Column;

beforeEach(function () {
    seedCustomers();
});

it('lists hideable columns and leaves out hidden or locked ones', function () {
    $names = collect(Livewire::test(ReportViewer::class, ['report' => 'customers'])->instance()->hideableColumns())->pluck('name')->all();

    expect($names)->toBe(['name', 'city', 'country.name', 'amount', 'active', 'joined_at', 'actions'])
        ->not->toContain('secret');
});

it('toggles columns, keeps them in the token and shows them again', function () {
    $component = Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('toggleColumn', 'city')
        ->call('toggleColumn', 'amount')
        ->assertSet('hidden_columns', ['city', 'amount'])
        ->assertSeeHtml('2 hidden');

    parse_str((string) parse_url($component->instance()->reportUrl(), PHP_URL_QUERY), $query);
    expect(ReportTokenManager::resolve((string) $query['token'])['hidden_columns'])->toBe(['city', 'amount']);

    $component->call('toggleColumn', 'city')
        ->assertSet('hidden_columns', ['amount'])
        ->call('showAllColumns')
        ->assertSet('hidden_columns', []);
});

it('ignores unknown, hidden and locked columns', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('toggleColumn', 'secret')
        ->call('toggleColumn', 'nope')
        ->assertSet('hidden_columns', [])
        ->set('hidden_columns', ['city', 'secret', 'nope', 7, 'city'])
        ->assertSet('hidden_columns', ['city']);
});

it('never hides the last visible column', function () {
    $component = Livewire::test(ReportViewer::class, ['report' => 'customers']);

    foreach (['name', 'city', 'country.name', 'amount', 'active', 'joined_at', 'actions'] as $name) {
        $component->call('toggleColumn', $name);
    }

    expect($component->get('hidden_columns'))->toHaveCount(6)->not->toContain('actions');
});

it('respects hidden-by-default and non-hideable columns', function () {
    config()->set('i-reports.reports', ['picker' => PickerReport::class]);

    $component = Livewire::test(ReportViewer::class, ['report' => 'picker'])
        ->assertSet('hidden_columns', ['city'])
        ->call('toggleColumn', 'name')
        ->assertSet('hidden_columns', ['city'])
        ->call('showAllColumns')
        ->assertSet('hidden_columns', [])
        ->call('resetReport')
        ->assertSet('hidden_columns', ['city']);

    expect(collect($component->instance()->hideableColumns())->pluck('name')->all())->toBe(['city', 'amount']);
});

it('loads the model key so formats and links can use it', function () {
    $html = $this->get(reportUrl())->assertOk()->getContent();

    expect($html)->toMatch('#href="/customers/\d+"#')->not->toContain('href="/customers/"');
});

class PickerReport extends CustomersReport
{
    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->hideable(false),
            Column::make('City', 'city')->hiddenByDefault(),
            Column::make('Amount', 'amount'),
        ];
    }
}

it('drops user-hidden columns from the report and every export', function () {
    $html = $this->get(reportUrl(['hidden_columns' => ['city', 'amount']]))->assertOk()->getContent();

    expect($html)->not->toContain('>City<')
        ->and($html)->not->toContain('BDT 250.50')
        ->and($html)->toContain('Alice')
        ->and($html)->toContain('>Name<');

    $csv = $this->get(reportUrl(['export' => 'csv', 'hidden_columns' => ['city']]))->assertOk()->streamedContent();
    expect($csv)->toContain('Name,Country,Amount,Active,Actions');

    config()->set('i-reports.stream_threshold', 0);
    $print = $this->get(reportUrl(['export' => 'print', 'hidden_columns' => ['country.name']]))->assertOk()->streamedContent();
    expect($print)->not->toContain('Bangladesh')->and($print)->toContain('Alice');

    Storage::fake('exports');
    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => 'customers', 'export' => 'xlsx', 'hidden_columns' => ['active']]))->storeGlobally();
    $report = app(app(ReportResolver::class)->resolve('customers'));
    $path = app(ReportExporter::class)->store($report, 'xlsx', 'exports');
    $headings = IOFactory::load(Storage::disk('exports')->path($path))->getActiveSheet()->rangeToArray('A5:F5')[0];

    expect($headings)->toBe(['Name', 'City', 'Country', 'Amount', 'Joined', 'Actions']);
});

it('keeps hidden columns in saved views', function () {
    $this->actingAs(makeUser());

    $component = Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('toggleColumn', 'city')
        ->set('preset_name', 'No city')
        ->call('savePreset')
        ->call('showAllColumns');

    $preset = ReportPreset::first();

    $component->call('applyPreset', $preset->id)->assertSet('hidden_columns', ['city']);
});

/**
 * SQL of the row query for the current context.
 *
 * @param  array<string, mixed>  $params
 */
function rowQuerySql(array $params): string
{
    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => 'customers'] + $params))->storeGlobally();

    return app(app(ReportResolver::class)->resolve('customers'))->exportBuilder()->toSql();
}

it('removes hidden columns and their joins from the query', function () {
    $all = rowQuerySql([]);
    $trimmed = rowQuerySql(['hidden_columns' => ['country.name', 'amount']]);

    expect($all)->toContain('"country"."name"')->toContain('left join "countries"')->toContain('"customers"."amount"')
        ->and($trimmed)->not->toContain('"country"."name"')
        ->and($trimmed)->not->toContain('left join "countries"')
        ->and($trimmed)->not->toContain('"customers"."amount"')
        ->and($trimmed)->toContain('"customers"."name"');
});

it('drops columns hidden in this output from the query', function () {
    expect(rowQuerySql(['export' => 'csv']))->not->toContain('"customers"."joined_at"')
        ->and(rowQuerySql(['export' => 'xlsx']))->toContain('"customers"."joined_at"');
});

it('keeps a hidden column in the query while search or sort uses it', function () {
    expect(rowQuerySql(['hidden_columns' => ['country.name'], 'search' => 'ind']))->toContain('left join "countries"')
        ->and(rowQuerySql(['hidden_columns' => ['amount'], 'sort_field' => 'amount']))->toContain('"customers"."amount"');

    $this->get(reportUrl(['hidden_columns' => ['country.name'], 'search' => 'ind']))
        ->assertOk()
        ->assertSee('Bob')
        ->assertDontSee('Alice')
        ->assertDontSee('India');
});

it('keeps always-selected columns in the query when hidden', function () {
    config()->set('i-reports.reports', ['picker' => AlwaysSelectReport::class]);

    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => 'picker', 'hidden_columns' => ['city', 'amount']]))->storeGlobally();
    $sql = app(app(ReportResolver::class)->resolve('picker'))->exportBuilder()->toSql();

    expect($sql)->toContain('"customers"."city"')->not->toContain('"customers"."amount"');
});

class AlwaysSelectReport extends CustomersReport
{
    public function columns(): array
    {
        return [
            Column::make('Name', 'name'),
            Column::make('City', 'city')->alwaysSelect(),
            Column::make('Amount', 'amount'),
        ];
    }
}
