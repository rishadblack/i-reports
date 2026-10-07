<?php

use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Http\Livewire\ReportViewer;
use Rishadblack\IReports\Jobs\ExportReportJob;
use Rishadblack\IReports\Services\ReportResolver;
use Rishadblack\IReports\Services\ReportTokenManager;
use Rishadblack\IReports\Support\PageSetup;
use Rishadblack\IReports\Support\ReportContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    seedCustomers();
});

/**
 * @param  array<string, mixed>  $params
 */
function setupReport(array $params = []): BaseReportController
{
    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => 'customers'] + $params))->storeGlobally();

    $report = app(app(ReportResolver::class)->resolve('customers'));
    $report->publishToContext();

    return $report;
}

/**
 * @return array<string, mixed>
 */
function exportTokenData(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return ReportTokenManager::resolve((string) $query['token']) ?? [];
}

it('uses the report defaults and accepts only whitelisted choices', function () {
    $report = setupReport();
    $report->setPaperSize('Legal')->setOrientation('landscape')->setFontSize(8.5)->setScale(95);

    expect(PageSetup::resolve($report)->toArray())->toBe(['paper' => 'Legal', 'orientation' => 'landscape', 'font_size' => 8.5, 'scale' => 95])
        ->and(PageSetup::resolve($report, ['paper' => 'a3', 'orientation' => 'portrait', 'font_size' => '11', 'scale' => '80'])->toArray())
        ->toBe(['paper' => 'A3', 'orientation' => 'portrait', 'font_size' => 11.0, 'scale' => 80])
        ->and(PageSetup::resolve($report, ['paper' => 'A0', 'orientation' => 'sideways', 'font_size' => 40, 'scale' => 5])->toArray())
        ->toBe(['paper' => 'Legal', 'orientation' => 'landscape', 'font_size' => 8.5, 'scale' => 95])
        ->and(PageSetup::options($report)['font_sizes'])->toContain(8.5)
        ->and(PageSetup::options($report)['scales'])->toContain(95);
});

it('sets table font sizes and scales css sizes', function () {
    $setup = new PageSetup('A4', 'portrait', 11, 50);

    expect($setup->tableStyle('color: red; font-size: 9pt; padding: 4px;'))->toBe('color: red; font-size: 11pt; padding: 4px;')
        ->and($setup->tableStyle('color: red;'))->toBe('color: red; font-size: 11pt;')
        ->and((new PageSetup)->tableStyle('font-size: 14px;'))->toBe('font-size: 14px;')
        ->and($setup->scaleCss('font-size: 9pt; padding: 6px 8px; width: 100%; border: 1px solid #000;'))->toBe('font-size: 4.5pt; padding: 3px 4px; width: 100%; border: 1px solid #000;')
        ->and($setup->scaleHtml('<style>td { font-size: 10pt; }</style><td style="font-size: 8pt;">font-size: 8pt</td>'))->toBe('<style>td { font-size: 5pt; }</style><td style="font-size: 4pt;">font-size: 8pt</td>')
        ->and((new PageSetup)->scaleHtml('<td style="font-size: 8pt;">x</td>'))->toBe('<td style="font-size: 8pt;">x</td>')
        ->and($setup->cssPageSize())->toBe('a4 portrait');
});

it('prints with the chosen paper, orientation, font size and scale', function (int $streamThreshold) {
    config()->set('i-reports.stream_threshold', $streamThreshold);

    $response = $this->get(reportUrl(['export' => 'print', 'page_setup' => ['paper' => 'A3', 'orientation' => 'landscape', 'font_size' => 11, 'scale' => 80]]))->assertOk();
    $html = $response->baseResponse instanceof StreamedResponse ? $response->streamedContent() : $response->getContent();

    expect($html)->toContain('size: a3 landscape;')
        ->and($html)->toContain('zoom: 0.8;')
        ->and($html)->toContain('font-size: 11pt')
        ->and($html)->not->toContain('font-size: 9pt; color: #1f2937; padding');
})->with(['rendered' => 5000, 'streamed' => 0]);

it('keeps print defaults without a page setup', function () {
    $html = $this->get(reportUrl(['export' => 'print']))->assertOk()->getContent();

    expect($html)->toContain('size: a4 portrait;')
        ->and($html)->not->toContain('zoom:');
});

it('builds the pdf on the chosen paper and orientation', function (int $streamThreshold) {
    config()->set('i-reports.stream_threshold', $streamThreshold);
    $report = setupReport(['export' => 'pdf', 'page_setup' => ['paper' => 'A3', 'orientation' => 'landscape', 'scale' => 80]]);

    $mpdf = $report->makeMpdf();

    expect(round($mpdf->w))->toBe(420.0)
        ->and(round($mpdf->h))->toBe(297.0)
        ->and($report->preparePdfHtml('<td style="font-size: 10pt;">x</td>'))->toBe('<td style="font-size: 8pt;">x</td>')
        ->and($report->pdfContent())->toStartWith('%PDF');
})->with(['rendered' => 5000, 'streamed' => 0]);

it('sends the dialog choices with print and pdf exports only', function () {
    $setup = ['paper' => 'A3', 'orientation' => 'landscape', 'font_size' => 10, 'scale' => 90];

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('exportAs', 'pdf', $setup)
        ->assertDispatched('exportEvent', fn (string $name, array $params) => exportTokenData($params['url'])['page_setup'] == ['paper' => 'A3', 'orientation' => 'landscape', 'font_size' => 10.0, 'scale' => 90])
        ->call('exportAs', 'print', ['paper' => 'A0', 'scale' => 1000])
        ->assertDispatched('exportEvent', fn (string $name, array $params) => exportTokenData($params['url'])['page_setup'] == ['paper' => 'A4', 'orientation' => 'portrait', 'scale' => 100]);

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('exportAs', 'csv', $setup)
        ->assertDispatched('exportEvent', fn (string $name, array $params) => exportTokenData($params['url'])['page_setup'] === []);
});

it('carries the page setup into queued pdf exports', function () {
    config()->set('i-reports.queue.enabled', true);
    config()->set('i-reports.queue.thresholds', ['pdf' => 1]);
    Queue::fake();

    Livewire::test(ReportViewer::class, ['report' => 'customers'])->call('exportAs', 'pdf', ['orientation' => 'landscape']);

    Queue::assertPushed(ExportReportJob::class, fn (ExportReportJob $job) => $job->request['page_setup']['orientation'] === 'landscape');
});

it('opens the setup dialog for print and pdf, and can be turned off', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSeeHtml('id="i-reports-page-setup"')
        ->assertSeeHtml("setupFormat = 'pdf'")
        ->assertSeeHtml("wire:click=\"exportAs('xlsx')\"")
        ->assertDontSeeHtml("wire:click=\"exportAs('pdf')\"");

    config()->set('i-reports.page_setup.enabled', false);

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertDontSeeHtml('id="i-reports-page-setup"')
        ->assertSeeHtml("wire:click=\"exportAs('pdf')\"")
        ->call('exportAs', 'pdf', ['orientation' => 'landscape'])
        ->assertDispatched('exportEvent', fn (string $name, array $params) => exportTokenData($params['url'])['page_setup'] === []);
});

it('keeps the designed font sizes unless a size is chosen', function () {
    config()->set('i-reports.default_style.th', 'font-size: 14px; color: #fff;');
    config()->set('i-reports.default_style.td', 'padding: 5px;');

    $designed = $this->get(reportUrl(['export' => 'print']))->assertOk()->getContent();
    $chosen = $this->get(reportUrl(['export' => 'print', 'page_setup' => ['font_size' => 10]]))->assertOk()->getContent();

    expect($designed)->toContain('font-size: 14px; color: #fff;')
        ->and($designed)->not->toContain('padding: 5px; font-size')
        ->and($chosen)->toContain('font-size: 10pt; color: #fff;')
        ->and($chosen)->toContain('padding: 5px; font-size: 10pt;');
});
