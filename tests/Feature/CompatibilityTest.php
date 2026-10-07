<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Services\ReportResolver;
use Rishadblack\IReports\Support\ReportContext;

beforeEach(function () {
    seedCustomers();
});

it('hides header tables on screen and shows them in print and exports', function (string $export, bool $visible) {
    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => 'customers', 'export' => $export]))->storeGlobally();

    $html = Blade::render('<x-i-reports::table type="header"><tr><td>Company Title</td></tr></x-i-reports::table>');

    expect(str_contains($html, 'Company Title'))->toBe($visible);
})->with([
    'view' => ['view', false],
    'inline' => ['inline', false],
    'print' => ['print', true],
    'pdf' => ['pdf', true],
    'xlsx' => ['xlsx', true],
]);

it('leaves distinct and raw aggregate selects without the primary key', function (Closure $query, bool $addsKey) {
    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => 'customers', 'export' => 'xlsx']))->storeGlobally();
    $report = app(app(ReportResolver::class)->resolve('customers'));
    $report->setBuilder($query());
    (fn () => $this->selectPrimaryKey())->call($report);

    expect(str_contains($report->getBuilder()->toSql(), '"customers"."id"'))->toBe($addsKey);
})->with([
    'plain columns' => [fn () => Customer::query()->select('customers.name'), true],
    'distinct' => [fn () => Customer::query()->distinct()->select('customers.city'), false],
    'raw aggregate' => [fn () => Customer::query()->select(DB::raw('SUM(customers.amount) as total')), false],
    'grouped' => [fn () => Customer::query()->select('customers.city')->groupBy('customers.city'), false],
]);

it('gives custom header views the report and header titles when streaming', function (string $export) {
    config()->set('i-reports.stream_threshold', 0);
    config()->set('i-reports.print.split_after', 0);
    config()->set('i-reports.header_view', 'partials.titled-header');

    $response = $this->get(reportUrl(['export' => $export]))->assertOk();
    $content = $export === 'print' ? $response->streamedContent() : $response->getContent();

    expect($export === 'pdf' ? str_starts_with($content, '%PDF') : str_contains($content, 'Titled: Customer List'))->toBeTrue();
})->with(['print', 'pdf']);

it('orders chunked exports by their own columns so strict mysql accepts them', function (Closure $query, string $expectedOrder) {
    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => 'customers', 'export' => 'csv']))->storeGlobally();
    $report = app(app(ReportResolver::class)->resolve('customers'));

    expect($report->withStableOrder($query())->toSql())->toEndWith($expectedOrder);
})->with([
    'grouped' => [fn () => Customer::query()->select('customers.city')->groupBy('customers.city'), 'order by "customers"."city" asc'],
    'distinct' => [fn () => Customer::query()->distinct()->select('customers.city as town'), 'order by "customers"."city" asc'],
    'plain' => [fn () => Customer::query(), 'order by "customers"."id" asc'],
    'already ordered' => [fn () => Customer::query()->orderBy('name'), 'order by "name" asc'],
]);
