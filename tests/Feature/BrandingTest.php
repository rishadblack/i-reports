<?php

use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Services\ReportResolver;
use Rishadblack\IReports\Support\ReportContext;
use Rishadblack\IReports\Views\Filter;

beforeEach(function () {
    seedCustomers();
});

/**
 * @param  array<string, mixed>  $params
 */
function brandedReport(array $params = []): BaseReportController
{
    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => 'customers'] + $params))->storeGlobally();

    return app(app(ReportResolver::class)->resolve('customers'));
}

it('collects branding with organisation, title, filters, date and user', function () {
    config()->set('i-reports.branding.name', 'Acme Ltd');
    config()->set('i-reports.branding.tagline', 'Dhaka, Bangladesh');
    $this->actingAs(makeUser('Rina'));

    $branding = brandedReport(['filters' => ['city' => 'Khulna', 'amount' => ['from' => '10']], 'search' => 'ali', 'sort_field' => 'amount', 'sort_direction' => 'desc'])->branding();

    expect($branding['name'])->toBe('Acme Ltd')
        ->and($branding['tagline'])->toBe('Dhaka, Bangladesh')
        ->and($branding['title'])->toBe('Customer List')
        ->and($branding['generated_by'])->toBe('Rina')
        ->and($branding['logo'])->toBeNull()
        ->and(collect($branding['filters'])->pluck('value', 'label')->all())->toBe([
            'City' => 'Khulna',
            'Amount' => 'from 10',
            'Search' => 'ali',
            'Sorted by' => 'Amount (descending)',
        ]);
});

it('falls back to the report header title and hides optional parts', function () {
    config()->set('i-reports.branding.show_filters', false);
    config()->set('i-reports.branding.show_generated_by', false);
    $this->actingAs(makeUser('Rina'));

    $branding = brandedReport(['filters' => ['city' => 'Khulna']])->setHeaderTitle('Head Office')->branding();

    expect($branding['name'])->toBe('Head Office')
        ->and($branding['filters'])->toBe([])
        ->and($branding['generated_by'])->toBeNull();
});

it('embeds the logo as a data uri', function () {
    $logo = tempnam(sys_get_temp_dir(), 'logo').'.png';
    file_put_contents($logo, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    config()->set('i-reports.branding.logo', $logo);

    try {
        $branding = brandedReport()->branding();

        expect($branding['logo'])->toStartWith('data:image/png;base64,')
            ->and($branding['logo_path'])->toBe($logo);

        $this->get(reportUrl(['export' => 'print']))->assertOk()->assertSee('src="data:image/png;base64,', false);
    } finally {
        @unlink($logo);
    }
});

it('prints a branded header with applied filters and a paged footer', function () {
    config()->set('i-reports.branding.name', 'Acme Ltd');

    $this->get(reportUrl(['export' => 'print', 'filters' => ['city' => 'Dhaka']]))
        ->assertOk()
        ->assertSeeInOrder(['Acme Ltd', 'Customer List', 'Generated', 'Records', '1', 'Applied filters', 'City:', 'Dhaka', 'Bob'])
        ->assertSee('counter(page)', false)
        ->assertSee('"Acme Ltd · Customer List"', false)
        ->assertSee('@page :first', false);
});

it('prints the same branded header when streaming', function () {
    config()->set('i-reports.branding.name', 'Acme Ltd');
    config()->set('i-reports.stream_threshold', 0);

    $html = $this->get(reportUrl(['export' => 'print', 'filters' => ['city' => 'Dhaka']]))->assertOk()->streamedContent();

    expect($html)->toContain('Acme Ltd')
        ->and($html)->toContain('Applied filters')
        ->and($html)->toContain('Records')
        ->and($html)->toContain('counter(page)')
        ->and(strpos($html, 'Acme Ltd'))->toBeLessThan(strpos($html, 'class="i-reports-table"'));
});

it('keeps the plain iframe view free of the export header', function () {
    config()->set('i-reports.branding.name', 'Acme Ltd');

    $this->get(reportUrl())->assertOk()->assertDontSee('Acme Ltd')->assertDontSee('Applied filters:');
});

it('uses a custom header view instead of the built-in one', function () {
    config()->set('i-reports.header_view', 'partials.report-header');

    $this->get(reportUrl(['export' => 'print']))->assertOk()->assertSee('Company Header')->assertDontSee('Applied filters');
});

it('shows address, contact and the footer note in print and pdf', function () {
    config()->set('i-reports.branding.name', 'Acme Ltd');
    config()->set('i-reports.branding.address', '12 Lake Road, Dhaka');
    config()->set('i-reports.branding.contact', 'hello@acme.test');
    config()->set('i-reports.branding.footer_note', 'Confidential - internal use only');

    $this->get(reportUrl(['export' => 'print']))
        ->assertOk()
        ->assertSeeInOrder(['12 Lake Road, Dhaka', 'hello@acme.test', 'Customer List'])
        ->assertSee('"Confidential - internal use only"', false);

    $html = view('i-reports::partials.pdf-page', ['branding' => brandedReport()->branding(3), 'pdfHeaderView' => null, 'pdfFooterView' => null])->render();

    expect($html)->toContain('Confidential - internal use only')
        ->and($html)->toContain('show-this-page="0"')
        ->and($html)->toContain('{PAGENO}');
});

it('falls back to who generated the report in the footer', function () {
    $this->actingAs(makeUser('Rina'));

    expect(brandedReport()->branding()['footer_text'])->toStartWith('Generated ')->toEndWith(' by Rina');
});

it('escapes branding values', function () {
    config()->set('i-reports.branding.name', '<script>alert(1)</script>');

    $this->get(reportUrl(['export' => 'print']))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('stripes streamed rows and keeps static cell styles', function () {
    config()->set('i-reports.stream_threshold', 0);

    $html = $this->get(reportUrl(['export' => 'print']))->assertOk()->streamedContent();

    expect(substr_count($html, 'background-color: #f8fafc'))->toBeGreaterThan(0)
        ->and($html)->not->toContain('style=";"');
});

it('describes filter values for chips and headers', function (Filter $filter, mixed $value, ?string $expected) {
    expect($filter->describe($value))->toBe($expected);
})->with([
    'select label' => [Filter::make('City', 'city')->select(['dk' => 'Dhaka']), 'dk', 'Dhaka'],
    'select unknown' => [Filter::make('City', 'city')->select(['dk' => 'Dhaka']), 'zz', 'zz'],
    'multi labels' => [Filter::make('City', 'city')->multiSelect(['dk' => 'Dhaka', 'kh' => 'Khulna']), ['dk', 'kh'], 'Dhaka, Khulna'],
    'boolean' => [Filter::make('Active', 'active')->boolean('On', 'Off'), '0', 'Off'],
    'range both' => [Filter::make('Joined', 'joined')->dateRange(), ['from' => '2024-01-01', 'to' => '2024-02-01'], '2024-01-01 – 2024-02-01'],
    'range to' => [Filter::make('Amount', 'amount')->numberRange(), ['to' => '9'], 'up to 9'],
    'text' => [Filter::make('Name', 'name')->text(), ' al ', 'al'],
    'empty' => [Filter::make('Name', 'name')->text(), '', null],
    'callback' => [Filter::make('Country', 'country_id')->component('x')->displayUsing(fn ($id) => "Country #{$id}"), '7', 'Country #7'],
    'callback null' => [Filter::make('Country', 'country_id')->component('x')->displayUsing(fn () => null), '7', null],
]);
