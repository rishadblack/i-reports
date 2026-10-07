<?php

use App\Livewire\Reports\CustomersReport;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Http\Livewire\ReportViewer;
use Rishadblack\IReports\Services\ReportResolver;
use Rishadblack\IReports\Support\ReportContext;
use Rishadblack\IReports\Views\Column;
use Rishadblack\IReports\Views\Filter;

beforeEach(function () {
    seedCustomers();
});

/**
 * @param  array<string, mixed>  $params
 */
function legacyReport(array $params = []): CustomersReport
{
    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => 'customers'] + $params))->storeGlobally();

    return app(app(ReportResolver::class)->resolve('customers'));
}

/**
 * The names of the customers a report query returns.
 *
 * @return array<int, string>
 */
function legacyNames(CustomersReport $report): array
{
    return $report->exportBuilder()->get()->pluck('name')->sort()->values()->all();
}

it('searches custom searchable columns by their database field', function () {
    $report = new class extends CustomersReport
    {
        public function columns(): array
        {
            return [
                Column::make('Name', 'name'),
                Column::make('City', 'city')->custom()->searchable()->format(fn ($value, $row) => 'in '.$row->name),
            ];
        }
    };

    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => 'customers', 'search' => 'khul']))->storeGlobally();

    expect(legacyNames($report))->toBe(['Alice']);
});

it('searches json paths with dots, case-insensitively', function () {
    DB::table('customers')->update(['secret' => json_encode(['profile' => ['nick' => 'Plain']])]);
    DB::table('customers')->where('name', 'Bob')->update(['secret' => json_encode(['profile' => ['nick' => 'BigBob']])]);

    $report = legacyReport(['search' => 'bigbob']);
    $report->setSearchField(['secret->profile.nick']);

    expect(legacyNames($report))->toBe(['Bob']);
});

it('counts rows with the conditions added in additionalQuery', function () {
    $report = new class extends CustomersReport
    {
        public function additionalQuery(Builder $builder): Builder
        {
            return $builder->where('customers.city', 'Dhaka');
        }
    };

    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => 'customers']))->storeGlobally();

    expect($report->total())->toBe(2);
});

it('eager loads the relations of relation columns, and can be switched off', function () {
    $rows = legacyReport()->exportBuilder()->get();

    expect($rows->every(fn (Customer $row) => $row->relationLoaded('country')))->toBeTrue()
        ->and($rows->firstWhere('name', 'Alice')->country->name)->toBe('Bangladesh');

    config()->set('i-reports.eager_load_relations', false);

    expect(legacyReport()->exportBuilder()->get()->first()->relationLoaded('country'))->toBeFalse();
});

it('passes lists to callbacks that accept them and the first value to scalar-typed ones', function () {
    $received = [];
    $untyped = Filter::make('City', 'city')->select()->filter(function ($query, $value) use (&$received) {
        $received['untyped'] = $value;
    });
    $typed = Filter::make('City', 'city')->select()->filter(function ($query, string $value) use (&$received) {
        $received['typed'] = $value;
    });

    $untyped->apply(Customer::query(), ['Dhaka', 'Khulna']);
    $typed->apply(Customer::query(), ['Dhaka', 'Khulna']);

    expect($received)->toBe(['untyped' => ['Dhaka', 'Khulna'], 'typed' => 'Dhaka']);
});

it('passes lists to select and text filters', function () {
    $select = Filter::make('City', 'city')->select(['Dhaka' => 'Dhaka', 'Khulna' => 'Khulna'])->column('customers.city');
    $text = Filter::make('Name', 'name')->text()->column('customers.name');

    $query = Customer::query();
    $select->apply($query, ['Dhaka', 'Khulna']);
    $textQuery = Customer::query();
    $text->apply($textQuery, ['ali', 'bo']);

    expect($select->sanitize([' Dhaka ', '', 'Khulna']))->toBe(['Dhaka', 'Khulna'])
        ->and($select->sanitize('Dhaka'))->toBe('Dhaka')
        ->and($query->count())->toBe(3)
        ->and($textQuery->pluck('name')->sort()->values()->all())->toBe(['Alice', 'Bob']);
});

it('sends export urls in both the new and the 0.1.x event shape', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('exportAs', 'csv')
        ->assertDispatched('exportEvent', fn (string $name, array $params) => isset($params['url'], $params[0]['url']) && $params['url'] === $params[0]['url']);
});

it('keeps the 0.1.x exportIframe listener and filter component parameters', function () {
    expect(file_get_contents(__DIR__.'/../../resources/views/livewire/report-viewer.blade.php'))->toContain("window.addEventListener('exportIframe'");

    $view = file_get_contents(__DIR__.'/../../resources/views/viewer/filters.blade.php');

    expect($view)->toContain("'key' => 'filter-'.\$filter['name'].'-item-'.\$loop->index")
        ->and($view)->toContain(':datalist="$componentParams[\'datalist\'] ?? null"');
});
