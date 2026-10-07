<?php

use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Exports\ReportExporter;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Http\Livewire\ReportViewer;
use Rishadblack\IReports\Services\ReportResolver;
use Rishadblack\IReports\Support\ReportContext;
use Rishadblack\IReports\Views\Column;

beforeEach(function () {
    seedCustomers();
});

/**
 * @param  array<string, mixed>  $params
 */
function ledgerReport(array $params = []): BaseReportController
{
    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => 'ledger'] + $params))->storeGlobally();

    $report = app(app(ReportResolver::class)->resolve('ledger'));
    $report->publishToContext();

    return $report;
}

it('exports csv from the report view with values computed in blade', function () {
    $content = $this->get(reportUrl(['export' => 'csv'], 'ledger'))->assertOk()->streamedContent();

    expect($content)->toContain('RB 250.50')
        ->and($content)->toContain('RB 425.50')
        ->and($content)->toContain('Closing balance 425.50')
        ->and($content)->toContain('Ledger')
        ->and(strpos($content, 'Name'))->toBeGreaterThan(strpos($content, 'Ledger'));
});

it('exports excel from the report view, keeping its own header and layout', function () {
    Storage::fake('exports');

    $path = app(ReportExporter::class)->store(ledgerReport(['export' => 'xlsx']), 'xlsx', 'exports');
    $sheet = IOFactory::load(Storage::disk('exports')->path($path))->getActiveSheet();
    $text = collect($sheet->toArray())->flatten()->filter()->implode(' | ');

    expect($text)->toContain('Ledger Header: Ledger')
        ->and($text)->toContain('RB 425.50')
        ->and($text)->toContain('Closing balance 425.50')
        ->and($text)->not->toContain('Generated:')
        ->and($sheet->getMergeCells())->not->toHaveKey('A1:C1')
        ->and($sheet->getHeaderFooter()->getOddFooter())->toContain('Page &P of &N');
});

it('prints and builds pdfs from the view with all rows, never split or streamed', function () {
    config()->set('i-reports.print.split_after', 2);
    config()->set('i-reports.stream_threshold', 0);

    $html = $this->get(reportUrl(['export' => 'print'], 'ledger'))->assertOk()->getContent();
    $report = ledgerReport(['export' => 'pdf']);

    expect($html)->toContain('RB 425.50')
        ->and($html)->toContain('Closing balance 425.50')
        ->and($html)->toContain('Ledger Header: Ledger')
        ->and($html)->not->toContain('Showing rows')
        ->and($report->shouldStream())->toBeFalse()
        ->and($report->printPart())->toBeNull()
        ->and($report->pdfContent())->toStartWith('%PDF');
});

it('lets one report opt back into split prints and column exports', function () {
    $report = ledgerReport(['export' => 'xlsx']);
    $report->setPrintSplitAfter(10);

    expect($report->getPrintSplitAfter())->toBe(10)
        ->and($report->getExcelMode())->toBe('view')
        ->and($report->setExportSource('columns')->getExcelMode())->toBe('query')
        ->and($report->getStreamThreshold())->toBe(5000);

    config()->set('i-reports.export_source', 'view');

    expect(prepareReportFor('customers')->usesViewForExports())->toBeTrue();
});

it('can make columns fixed unless a column opts in', function () {
    config()->set('i-reports.columns_hideable', false);

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSet('hidden_columns', [])
        ->assertDontSeeHtml('data-column="name"');

    expect(Column::make('A', 'a')->isHideable())->toBeFalse()
        ->and(Column::make('A', 'a')->hideable()->isHideable())->toBeTrue();
});

function prepareReportFor(string $name): BaseReportController
{
    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => $name]))->storeGlobally();

    return app(app(ReportResolver::class)->resolve($name));
}
