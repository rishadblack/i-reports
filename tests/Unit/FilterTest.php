<?php

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Rishadblack\IReports\Views\Filter;

function filterSql(Filter $filter, mixed $value): array
{
    $query = Customer::query();
    $filter->apply($query, $value);

    return [$query->toSql(), $query->getBindings()];
}

it('sanitises values by type', function (Filter $filter, mixed $input, mixed $expected) {
    expect($filter->sanitize($input))->toBe($expected)
        ->and($filter->hasValue($input))->toBe($expected !== null);
})->with([
    'text trims' => [Filter::make('N', 'n')->text(), '  abc ', 'abc'],
    'text blank' => [Filter::make('N', 'n')->text(), '   ', null],
    'text array keeps the list' => [Filter::make('N', 'n')->text(), ['x', ' ', 'y'], ['x', 'y']],
    'select' => [Filter::make('N', 'n')->select(['a' => 'A']), 'a', 'a'],
    'select array keeps the list' => [Filter::make('N', 'n')->select(), ['b', 'c'], ['b', 'c']],
    'multi' => [Filter::make('N', 'n')->multiSelect(), ['a', '', ' b '], ['a', 'b']],
    'multi scalar' => [Filter::make('N', 'n')->multiSelect(), 'a', ['a']],
    'multi empty' => [Filter::make('N', 'n')->multiSelect(), [''], null],
    'date' => [Filter::make('N', 'n')->date(), '2024-02-15T10:00', '2024-02-15'],
    'date invalid' => [Filter::make('N', 'n')->date(), 'nope', null],
    'date range' => [Filter::make('N', 'n')->dateRange(), ['from' => '2024-01-01', 'to' => ''], ['from' => '2024-01-01']],
    'date range invalid edge dropped' => [Filter::make('N', 'n')->dateRange(), ['from' => 'x', 'to' => '2024-01-31'], ['to' => '2024-01-31']],
    'date range not array' => [Filter::make('N', 'n')->dateRange(), '2024-01-01', null],
    'number' => [Filter::make('N', 'n')->number(), '12.5', 12.5],
    'number int' => [Filter::make('N', 'n')->number(), '12', 12],
    'number invalid' => [Filter::make('N', 'n')->number(), 'abc', null],
    'number range' => [Filter::make('N', 'n')->numberRange(), ['from' => '1', 'to' => 'x'], ['from' => 1]],
    'boolean true' => [Filter::make('N', 'n')->boolean(), 'yes', '1'],
    'boolean false' => [Filter::make('N', 'n')->boolean(), '0', '0'],
    'boolean invalid' => [Filter::make('N', 'n')->boolean(), 'maybe', null],
    'component list' => [Filter::make('N', 'n')->component('x'), ['1', '2'], ['1', '2']],
    'component scalar' => [Filter::make('N', 'n')->component('x'), '3', '3'],
    'object' => [Filter::make('N', 'n')->text(), new stdClass, null],
]);

it('applies default constraints on the column', function (Filter $filter, mixed $value, string $sql, array $bindings) {
    expect(filterSql($filter, $value))->toBe([$sql, $bindings]);
})->with([
    'text like' => [Filter::make('N', 'n')->text()->column('customers.name'), 'al', 'select * from "customers" where "customers"."name" like ?', ['%al%']],
    'select equals' => [Filter::make('N', 'n')->select()->column('customers.city'), 'Dhaka', 'select * from "customers" where "customers"."city" = ?', ['Dhaka']],
    'multi in' => [Filter::make('N', 'n')->multiSelect()->column('customers.city'), ['a', 'b'], 'select * from "customers" where "customers"."city" in (?, ?)', ['a', 'b']],
    'date' => [Filter::make('N', 'n')->date()->column('customers.joined_at'), '2024-01-05', 'select * from "customers" where strftime(\'%Y-%m-%d\', "customers"."joined_at") = cast(? as text)', ['2024-01-05']],
    'number range' => [Filter::make('N', 'n')->numberRange()->column('customers.amount'), ['from' => '1', 'to' => '9'], 'select * from "customers" where "customers"."amount" >= ? and "customers"."amount" <= ?', [1, 9]],
    'boolean' => [Filter::make('N', 'n')->boolean()->column('customers.active'), 'true', 'select * from "customers" where "customers"."active" = ?', ['1']],
    'nothing when empty' => [Filter::make('N', 'n')->text()->column('customers.name'), '', 'select * from "customers"', []],
    'nothing without column' => [Filter::make('N', 'n')->text(), 'x', 'select * from "customers"', []],
]);

it('applies a date range on both edges', function () {
    [$sql, $bindings] = filterSql(Filter::make('N', 'n')->dateRange()->column('customers.joined_at'), ['from' => '2024-01-01', 'to' => '2024-01-31']);

    expect($sql)->toContain('>= cast(? as text)')->toContain('<= cast(? as text)')
        ->and($bindings)->toBe(['2024-01-01', '2024-01-31']);
});

it('passes the sanitised value and the filter to callbacks', function () {
    $received = [];

    $filter = Filter::make('N', 'n')->multiSelect()->filter(function (Builder $query, array $value, Filter $filter) use (&$received) {
        $received = [$value, $filter->key()];
    });

    $filter->apply(Customer::query(), [' a ', '']);

    expect($received)->toBe([['a'], 'n']);

    $filter->apply(Customer::query(), ['']);

    expect($received)->toBe([['a'], 'n']);
});

it('serialises for the viewer without callbacks', function () {
    $array = Filter::make('Region', 'region')
        ->select(['n' => 'North'])
        ->placeholder('Pick')
        ->customClass('col-6')
        ->dependsOn('country_id')
        ->default('n')
        ->responseTime('300')
        ->filter(fn () => null)
        ->toArray();

    expect($array)->toBe([
        'name' => 'region',
        'title' => 'Region',
        'placeholder' => 'Pick',
        'class' => 'col-6',
        'filter_type' => 'select',
        'component' => null,
        'component_parameters' => [],
        'response_time' => '300',
        'update' => 'defer',
        'wire_model' => 'wire:model',
        'options' => ['n' => 'North'],
        'depends_on' => 'country_id',
        'default' => 'n',
    ]);

    expect(Filter::make('B', 'b')->boolean('On', 'Off')->toArray()['options'])->toBe(['1' => 'On', '0' => 'Off'])
        ->and(Filter::make('C', 'c')->component('selects.city', ['limit' => 5])->toArray())->toMatchArray(['filter_type' => 'component', 'component' => 'selects.city', 'component_parameters' => ['limit' => 5]])
        ->and(Filter::make('C', 'c')->bladeComponent('forms.date')->getType())->toBe('blade_component');
});
