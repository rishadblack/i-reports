<?php

use App\Models\Customer;
use Illuminate\Support\HtmlString;
use Rishadblack\IReports\Views\Column;

it('parses relation names', function () {
    $column = Column::make('Country', 'country.name');

    expect($column->getField())->toBe('name')
        ->and($column->getRelationString())->toBe('country')
        ->and($column->hasRelations())->toBeTrue()
        ->and($column->getColumnSelectName())->toBe('country.name')
        ->and($column->setTable('country')->getColumn())->toBe('country.name')
        ->and(Column::make('Name', 'name')->setTable('customers')->getColumn())->toBe('customers.name')
        ->and(Column::make('Full Name')->getName())->toBe('full_name');
});

it('escapes values by default and allows html on request', function () {
    $row = new Customer(['name' => '<b>x</b>']);

    expect(Column::make('Name', 'name')->render($row))->toBe('&lt;b&gt;x&lt;/b&gt;')
        ->and(Column::make('Name', 'name')->html()->render($row))->toBe('<b>x</b>')
        ->and(Column::make('Name', 'name')->format(fn ($value) => "<i>{$value}</i>")->render($row))->toBe('&lt;i&gt;&lt;b&gt;x&lt;/b&gt;&lt;/i&gt;')
        ->and(Column::make('Name', 'name')->format(fn ($value) => new HtmlString('<i>safe</i>'))->render($row))->toBe('<i>safe</i>')
        ->and(Column::make('Name', 'name')->format(fn () => null)->render($row))->toBe('');
});

it('passes value, row and column to format callbacks', function () {
    $row = new Customer(['name' => 'Alice', 'city' => 'Khulna']);
    $column = Column::make('Name', 'name')->format(fn ($value, $r, Column $c) => "{$value}-{$r->city}-{$c->getTitle()}");

    expect($column->render($row))->toBe('Alice-Khulna-Name')
        ->and($column->applyFormat('Bob', $row))->toBe('Bob-Khulna-Name');
});

it('formats every column type for display and export', function (Column $column, mixed $value, string $display, mixed $export) {
    $row = new Customer(['name' => $value]);

    expect($column->render($row))->toBe($display)
        ->and($column->exportValue($row))->toBe($export);
})->with([
    'number' => [Column::make('N', 'name')->number(), '1234.567', '1,234.57', 1234.57],
    'number custom' => [Column::make('N', 'name')->number(0, ',', '.'), '1234.567', '1.235', 1235.0],
    'money before' => [Column::make('N', 'name')->money('BDT'), '1234.5', 'BDT 1,234.50', 1234.5],
    'money after' => [Column::make('N', 'name')->money('€', 2, 'after'), '10', '10.00 €', 10.0],
    'money no currency' => [Column::make('N', 'name')->money(), '10', '10.00', 10.0],
    'non numeric' => [Column::make('N', 'name')->number(), 'abc', 'abc', 'abc'],
    'date' => [Column::make('N', 'name')->date('d/m/Y'), '2024-02-15 10:00:00', '15/02/2024', '15/02/2024'],
    'datetime' => [Column::make('N', 'name')->datetime('Y-m-d H:i'), '2024-02-15 10:30:00', '2024-02-15 10:30', '2024-02-15 10:30'],
    'bad date' => [Column::make('N', 'name')->date(), 'not a date', 'not a date', 'not a date'],
    'boolean true' => [Column::make('N', 'name')->boolean('Yes', 'No'), '1', 'Yes', 'Yes'],
    'boolean false' => [Column::make('N', 'name')->boolean('Yes', 'No'), '0', 'No', 'No'],
    'badge mapped' => [Column::make('N', 'name')->badge(['paid' => ['Paid', 'badge bg-success']]), 'paid', '<span class="badge bg-success">Paid</span>', 'Paid'],
    'badge class only' => [Column::make('N', 'name')->badge(['due' => 'badge bg-warning']), 'due', '<span class="badge bg-warning">due</span>', 'due'],
    'badge default' => [Column::make('N', 'name')->badge(), '<x>', '<span class="badge bg-secondary">&lt;x&gt;</span>', '<x>'],
    'link' => [Column::make('N', 'name')->link(fn ($value) => "/go/{$value}", '_self'), 'a"b', '<a href="/go/a&quot;b" target="_self">a&quot;b</a>', '/go/a"b'],
    'image' => [Column::make('N', 'name')->image(20), 'pic.png', '<img src="pic.png" width="20" alt="" />', 'pic.png'],
    'image src' => [Column::make('N', 'name')->image(20, fn ($value) => "/img/{$value}"), 'pic.png', '<img src="/img/pic.png" width="20" alt="" />', '/img/pic.png'],
    'null' => [Column::make('N', 'name')->money(), null, '', null],
]);

it('uses exportFormat over format and strips html for exports', function () {
    $row = new Customer(['name' => 'Alice']);

    expect(Column::make('N', 'name')->format(fn ($v) => "<b>{$v}</b>")->html()->exportValue($row))->toBe('Alice')
        ->and(Column::make('N', 'name')->format(fn ($v) => "{$v}!")->exportValue($row))->toBe('Alice!')
        ->and(Column::make('N', 'name')->format(fn ($v) => "{$v}!")->exportFormat(fn ($v) => strtoupper($v))->exportValue($row))->toBe('ALICE')
        ->and(Column::make('N', 'name')->money('BDT')->format(fn ($v) => "[{$v}]")->exportValue(new Customer(['name' => '5'])))->toBe(5.0);
});

it('formats aggregates with the column type', function () {
    expect(Column::make('A', 'amount')->money('BDT')->sum()->formatAggregate(1234.5))->toBe('BDT 1,234.50')
        ->and(Column::make('A', 'amount')->number(1)->avg()->formatAggregate('2.25'))->toBe('2.3')
        ->and(Column::make('A', 'amount')->count()->formatAggregate(3))->toBe('3')
        ->and(Column::make('A', 'amount')->max()->formatAggregate('x'))->toBe('x')
        ->and(Column::make('A', 'amount')->min()->formatAggregate(null))->toBe('')
        ->and(Column::make('A', 'amount')->hasAggregate())->toBeFalse()
        ->and(fn () => Column::make('A', 'amount')->aggregate('median'))->toThrow(InvalidArgumentException::class);
});

it('tracks visibility flags', function () {
    $column = Column::make('J', 'joined_at')->hideIn('pdf|xlsx');

    expect($column->isHiddenIn('pdf'))->toBeTrue()
        ->and($column->isHiddenIn('xlsx'))->toBeTrue()
        ->and($column->isHiddenIn('csv'))->toBeFalse()
        ->and($column->isHiddenIn(null))->toBeFalse()
        ->and(Column::make('J', 'joined_at')->hideIn(['csv'])->getHideIn())->toBe(['csv'])
        ->and(Column::make('S', 'secret')->hide()->isHiddenIn('view'))->toBeTrue()
        ->and(Column::make('S', 'secret')->isHidden())->toBeFalse();
});

it('builds inline styles from align, width and style', function () {
    $row = new Customer(['amount' => 5]);

    expect(Column::make('A', 'amount')->money()->applyStyle())->toBe('text-align: right;')
        ->and(Column::make('A', 'amount')->align('center')->width('10%')->style('color: red;')->applyStyle())->toBe('text-align: center; width: 10%; color: red;')
        ->and(Column::make('A', 'amount')->style(fn ($r) => $r->amount > 1 ? 'font-weight: bold;' : '')->applyStyle($row))->toBe('font-weight: bold;')
        ->and(Column::make('A', 'amount')->applyStyle())->toBeNull()
        ->and(fn () => Column::make('A', 'amount')->align('middle'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Column::make('A', 'amount')->type('fancy'))->toThrow(InvalidArgumentException::class);
});

it('reads values from models and arrays', function () {
    $column = Column::make('Country', 'country.name');

    expect($column->getValue(['country.name' => 'BD']))->toBe('BD')
        ->and($column->getValue((new Customer)->setRawAttributes(['country.name' => 'IN'])))->toBe('IN')
        ->and($column->getValue('scalar'))->toBeNull()
        ->and(Column::make('Name', 'name')->getValue(['other' => 1]))->toBeNull();
});

it('serialises to an array without callbacks', function () {
    $array = Column::make('Amount', 'amount')->money('BDT')->sum()->sortable()->searchable()->hideIn('csv')->style(fn () => 'x')->toArray();

    expect($array)->toMatchArray(['title' => 'Amount', 'name' => 'amount', 'type' => 'money', 'sortable' => true, 'searchable' => true, 'hide_in' => ['csv'], 'aggregate' => 'sum', 'align' => 'right', 'html' => false, 'style' => '[callback]']);
});
