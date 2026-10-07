<?php

use App\Livewire\Reports\CustomersReport;
use App\Livewire\Reports\GroupedCustomersReport;
use Modules\Billing\Livewire\Reports\InvoicesReport;
use Rishadblack\IReports\Facades\IReports;
use Rishadblack\IReports\Services\ReportResolver;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('resolves reports by convention', function () {
    $resolver = app(ReportResolver::class);

    expect($resolver->find('customers'))->toBe(CustomersReport::class)
        ->and($resolver->find('grouped-customers'))->toBe(GroupedCustomersReport::class)
        ->and($resolver->find('billing::invoices'))->toBe(InvoicesReport::class)
        ->and($resolver->resolve('customers'))->toBe(CustomersReport::class);
});

it('returns null for unknown or malformed names', function (string $name) {
    expect(app(ReportResolver::class)->find($name))->toBeNull();
})->with(['', ' ', 'nope', '../etc', 'customers;drop', 'bad module::invoices', 'billing::../x', 'billing::nope', 'nope::invoices']);

it('throws a 404 for unknown reports', function () {
    expect(fn () => app(ReportResolver::class)->resolve('nope'))->toThrow(NotFoundHttpException::class);
});

it('prefers registered names and ignores classes that are not reports', function () {
    IReports::register('people', CustomersReport::class);
    IReports::register(['bogus' => stdClass::class, 'missing' => 'Nope\\Missing']);
    config()->set('i-reports.reports', ['configured' => GroupedCustomersReport::class, 'people' => GroupedCustomersReport::class]);

    $resolver = app(ReportResolver::class);

    expect($resolver->find('people'))->toBe(CustomersReport::class)
        ->and($resolver->find('configured'))->toBe(GroupedCustomersReport::class)
        ->and($resolver->find('bogus'))->toBeNull()
        ->and($resolver->find('missing'))->toBeNull()
        ->and(IReports::all())->toHaveKeys(['people', 'configured', 'bogus', 'missing'])
        ->and(IReports::collection())->toHaveCount(4);
});

it('resolves the view name from the class', function () {
    expect(app(CustomersReport::class)->getViewName())->toBe('livewire.reports.customers-report')
        ->and(app(GroupedCustomersReport::class)->getViewName())->toBe('livewire.reports.grouped-customers-report');

    config()->set('modules.namespace', 'Modules');

    expect(app(InvoicesReport::class)->getViewName())->toBe('billing::livewire.reports.invoices-report');
});
