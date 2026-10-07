<?php

use App\Livewire\Reports\CustomersReport;
use App\Models\Customer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Rishadblack\IReports\Events\ReportExportCompleted;
use Rishadblack\IReports\Exports\ReportExporter;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Jobs\ExportReportJob;
use Rishadblack\IReports\Services\ReportResolver;
use Rishadblack\IReports\Support\ReportContext;

beforeEach(function () {
    seedCustomers();
});

/**
 * @return array<int, array<int, mixed>>
 */
function sheetRows(string $disk, string $path): array
{
    return IOFactory::load(Storage::disk($disk)->path($path))->getActiveSheet()->toArray(null, true, false);
}

function prepareReport(string $report = 'customers', array $params = []): CustomersReport
{
    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => $report] + $params))->storeGlobally();

    $instance = app(app(ReportResolver::class)->resolve($report));
    $instance->publishToContext();

    return $instance;
}

/*
|--------------------------------------------------------------------------
| CSV
|--------------------------------------------------------------------------
*/

it('streams a csv with headings, typed values and the aggregate row', function () {
    $response = $this->get(reportUrl(['export' => 'csv']))->assertOk();
    $content = $response->streamedContent();

    expect($response->headers->get('content-disposition'))->toContain('customers-report-')->toContain('.csv')
        ->and($response->headers->get('content-type'))->toContain('text/csv')
        ->and($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain("Name,City,Country,Amount,Active,Actions\n")
        ->and($content)->not->toContain('Joined')
        ->and($content)->toContain("Alice,Khulna,Bangladesh,250.5,Inactive,Edit\n")
        ->and($content)->toContain("Bob,Dhaka,India,75,Active,Edit\n")
        ->and($content)->toContain("Charlie,Dhaka,Bangladesh,100,Active,Edit\n")
        ->and($content)->toContain(',,,425.5,,');
});

it('exports every row to csv regardless of pagination and honours filters and sort', function () {
    $content = $this->get(reportUrl(['export' => 'csv', 'per_page' => 1, 'filters' => ['city' => 'Dhaka'], 'sort_field' => 'amount', 'sort_direction' => 'desc']))
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain('Charlie')->toContain('Bob')->not->toContain('Alice')
        ->and(strpos($content, 'Charlie'))->toBeLessThan(strpos($content, 'Bob'));
});

it('neutralises spreadsheet formulas in csv cells', function () {
    Customer::create(['name' => '=HYPERLINK("http://evil")', 'city' => 'Dhaka', 'amount' => 0]);

    $content = $this->get(reportUrl(['export' => 'csv', 'search' => 'HYPERLINK']))->assertOk()->streamedContent();

    expect($content)->toContain("\"'=HYPERLINK(\"\"http://evil\"\")\"")->not->toContain("\n=HYPERLINK");
});

/*
|--------------------------------------------------------------------------
| Excel
|--------------------------------------------------------------------------
*/

it('downloads an xlsx file', function () {
    $response = $this->get(reportUrl(['export' => 'xlsx']))->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('customers-report-')->toContain('.xlsx');
});

it('writes xlsx in query mode with headings, types, hideIn and aggregates', function () {
    Storage::fake('exports');

    $path = app(ReportExporter::class)->store(prepareReport(), 'xlsx', 'exports');
    $rows = sheetRows('exports', $path);

    expect($path)->toEndWith('.xlsx')
        ->and($rows[0][0])->toBe(config('app.name'))
        ->and($rows[1][0])->toBe('Customer List')
        ->and($rows[2][0])->toStartWith('Generated: ')->toContain('Records: 3')
        ->and($rows[4])->toBe(['Name', 'City', 'Country', 'Amount', 'Active', 'Joined', 'Actions'])
        ->and(array_values(array_diff_key($rows[5], [5 => true])))->toBe(['Alice', 'Khulna', 'Bangladesh', 250.5, 'Inactive', 'Edit'])
        ->and(Date::excelToDateTimeObject($rows[5][5])->format('Y-m-d'))->toBe('2024-02-15')
        ->and($rows[6][0])->toBe('Bob')
        ->and($rows[7][0])->toBe('Charlie')
        ->and($rows[8][0])->toBe('Total')
        ->and($rows[8][3])->toBe(425.5);
});

it('styles the xlsx sheet with a title block, frozen headings, autofilter, number formats and a page footer', function () {
    Storage::fake('exports');

    $path = app(ReportExporter::class)->store(prepareReport('customers', ['filters' => ['city' => 'Dhaka']]), 'xlsx', 'exports');
    $sheet = IOFactory::load(Storage::disk('exports')->path($path))->getActiveSheet();

    expect($sheet->getTitle())->toBe('Customer List')
        ->and($sheet->getMergeCells())->toHaveKey('A1:G1')
        ->and((string) $sheet->getCell('A4')->getValue())->toBe('Applied filters: City: Dhaka')
        ->and($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getStyle('A5')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getStyle('A5')->getFill()->getStartColor()->getRGB())->toBe('1F2937')
        ->and($sheet->getFreezePane())->toBe('A6')
        ->and($sheet->getAutoFilter()->getRange())->toBe('A5:G7')
        ->and($sheet->getStyle('D6')->getNumberFormat()->getFormatCode())->toBe('#,##0.00')
        ->and($sheet->getStyle('A8')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getHeaderFooter()->getOddFooter())->toContain('Page &P of &N')
        ->and(array_map('intval', $sheet->getPageSetup()->getRowsToRepeatAtTop()))->toBe([5, 5]);
});

it('stores formula-looking text as plain text in xlsx', function () {
    Storage::fake('exports');
    Customer::create(['name' => '=HYPERLINK("http://evil","x")', 'city' => 'Dhaka', 'amount' => 0]);

    $path = app(ReportExporter::class)->store(prepareReport('customers', ['search' => 'HYPERLINK']), 'xlsx', 'exports');
    $cell = IOFactory::load(Storage::disk('exports')->path($path))->getActiveSheet()->getCell('A6');

    expect($cell->getDataType())->toBe('s')
        ->and($cell->getValue())->toBe('=HYPERLINK("http://evil","x")');
});

it('puts title rows above csv data and can turn them off', function () {
    $content = $this->get(reportUrl(['export' => 'csv', 'filters' => ['city' => 'Khulna']]))->assertOk()->streamedContent();

    expect($content)->toContain("\"Customer List\"\n")
        ->and($content)->toContain("\"Applied filters: City: Khulna\"\n")
        ->and(strpos($content, 'Customer List'))->toBeLessThan(strpos($content, 'Name,City'))
        ->and($content)->toContain("Total,,,250.5,,\n");

    config()->set('i-reports.csv.title_rows', false);

    $plain = $this->get(reportUrl(['export' => 'csv']))->assertOk()->streamedContent();

    expect($plain)->toStartWith("\xEF\xBB\xBFName,City,");
});

it('writes the xlsx title block as rich text with a logo and a running page header', function () {
    Storage::fake('exports');
    $logo = tempnam(sys_get_temp_dir(), 'logo').'.png';
    file_put_contents($logo, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    config()->set('i-reports.branding.name', 'Acme & Co');
    config()->set('i-reports.branding.tagline', 'Wholesale');
    config()->set('i-reports.branding.logo', $logo);
    config()->set('i-reports.branding.footer_note', 'Confidential');

    try {
        $path = app(ReportExporter::class)->store(prepareReport(), 'xlsx', 'exports');
        $sheet = IOFactory::load(Storage::disk('exports')->path($path))->getActiveSheet();
        $footer = $sheet->getHeaderFooter();

        expect($sheet->getCell('A1')->getValue())->toBeInstanceOf(RichText::class)
            ->and((string) $sheet->getCell('A1')->getValue())->toBe('Acme & Co    Wholesale')
            ->and($sheet->getDrawingCollection())->toHaveCount(1)
            ->and($footer->getDifferentFirst())->toBeTrue()
            ->and($footer->getOddHeader())->toContain('Acme && Co')
            ->and($footer->getOddFooter())->toContain('Confidential')->toContain('Page &P of &N')
            ->and($footer->getFirstHeader())->toBe('');
    } finally {
        @unlink($logo);
    }
});

it('adds the branded header to xlsx view mode', function () {
    Storage::fake('exports');

    $path = app(ReportExporter::class)->store(prepareReport('grouped-customers'), 'xlsx', 'exports');
    $sheet = IOFactory::load(Storage::disk('exports')->path($path))->getActiveSheet();

    expect((string) $sheet->getCell('A2')->getValue())->toBe('Grouped Customers')
        ->and($sheet->getCell('A5')->getValue())->toBe('Name')
        ->and($sheet->getFreezePane())->toBe('A6');
});

it('writes xlsx in view mode from the blade table', function () {
    Storage::fake('exports');

    $path = app(ReportExporter::class)->store(prepareReport('grouped-customers'), 'xlsx', 'exports');
    $flat = collect(sheetRows('exports', $path))->flatten()->filter()->values()->all();

    expect($flat)->toContain('Name')->toContain('Dhaka')->toContain('Subtotal')->toContain('Grand total')->toContain('Alice');
});

it('applies filters to xlsx exports', function () {
    Storage::fake('exports');

    $path = app(ReportExporter::class)->store(prepareReport('customers', ['filters' => ['city' => 'Khulna']]), 'xlsx', 'exports');
    $flat = collect(sheetRows('exports', $path))->flatten()->filter()->values()->all();

    expect($flat)->toContain('Alice')->not->toContain('Bob');
});

/*
|--------------------------------------------------------------------------
| PDF and print
|--------------------------------------------------------------------------
*/

it('downloads a pdf', function () {
    $response = $this->get(reportUrl(['export' => 'pdf']))->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('.pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

it('renders a pdf with custom header and footer views and page breaks', function () {
    config()->set('i-reports.pdf_header_view', 'pdf.header');
    config()->set('i-reports.pdf_footer_view', 'pdf.header');

    $response = $this->get(reportUrl(['export' => 'pdf'], 'grouped-customers'))->assertOk();

    expect($response->getContent())->toStartWith('%PDF');
});

it('stores a pdf through the exporter', function () {
    Storage::fake('exports');

    $path = app(ReportExporter::class)->store(prepareReport(), 'pdf', 'exports');

    expect($path)->toEndWith('.pdf')
        ->and(Storage::disk('exports')->get($path))->toStartWith('%PDF');
});

it('renders a printable page with every row and a page break', function () {
    $this->get(reportUrl(['export' => 'print']))
        ->assertOk()
        ->assertSee(['Alice', 'Bob', 'Charlie'])
        ->assertSee('window.print()', false)
        ->assertSee('Joined');

    $this->get(reportUrl(['export' => 'print'], 'grouped-customers'))
        ->assertOk()
        ->assertSee('page-break-after', false)
        ->assertSee('Summary page');
});

it('rejects unsupported export formats', function () {
    $this->get(reportUrl(['export' => 'exe']))->assertOk()->assertSee('Alice')->assertDontSee('Charlie');

    expect(fn () => app(ReportExporter::class)->download(prepareReport(), 'exe'))->toThrow(InvalidArgumentException::class);
});

/*
|--------------------------------------------------------------------------
| Queued exports
|--------------------------------------------------------------------------
*/

it('generates an export in a queued job and announces it', function () {
    Storage::fake('exports');
    Event::fake([ReportExportCompleted::class]);

    $request = (new RequestHelper(['report' => 'customers', 'filters' => ['city' => 'Khulna']]))->toArray();

    ExportReportJob::dispatchSync($request, 'csv', null, 'exports', 'queued');

    $files = Storage::disk('exports')->files('queued');

    expect($files)->toHaveCount(1)
        ->and($files[0])->toEndWith('.csv')
        ->and(Storage::disk('exports')->get($files[0]))->toContain('Alice')->not->toContain('Bob');

    Event::assertDispatched(ReportExportCompleted::class, fn (ReportExportCompleted $event) => $event->format === 'csv' && $event->disk === 'exports' && $event->path === $files[0]);
});

it('does not export in a job when the report refuses authorization', function () {
    Storage::fake('exports');
    Event::fake([ReportExportCompleted::class]);

    ExportReportJob::dispatchSync((new RequestHelper(['report' => 'secret']))->toArray(), 'pdf', null, 'exports');

    expect(Storage::disk('exports')->allFiles())->toBeEmpty();
    Event::assertNotDispatched(ReportExportCompleted::class);
});

it('runs a queued export as the requesting user', function () {
    Storage::fake('exports');
    $user = makeUser();

    ExportReportJob::dispatchSync((new RequestHelper(['report' => 'customers']))->toArray(), 'xlsx', $user->id, 'exports');

    expect(Storage::disk('exports')->allFiles())->toHaveCount(1);
});

it('gives the xlsx a clean report look: no gridlines, padding, real dates and a record count', function () {
    Storage::fake('exports');

    $path = app(ReportExporter::class)->store(prepareReport(), 'xlsx', 'exports');
    $sheet = IOFactory::load(Storage::disk('exports')->path($path))->getActiveSheet();

    $zip = new ZipArchive;
    $zip->open(Storage::disk('exports')->path($path));
    $sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    expect($sheetXml)->toContain('showGridLines="false"')
        ->and((string) $sheet->getCell('A3')->getValue())->toContain('Records: 3')
        ->and((string) $sheet->getCell('A4')->getValue())->toBe('Applied filters: None — showing all records')
        ->and($sheet->getStyle('A6')->getAlignment()->getIndent())->toBe(1)
        ->and($sheet->getStyle('D5')->getAlignment()->getHorizontal())->toBe('right')
        ->and($sheet->getCell('F6')->getFormattedValue())->toBe('15/02/2024')
        ->and($sheet->getStyle('F5')->getAlignment()->getHorizontal())->toBe('center')
        ->and($sheet->getRowDimension(5)->getRowHeight())->toBe(26.0)
        ->and($sheet->getTabColor()->getRGB())->toBe('1F2937')
        ->and($sheet->getParent()->getProperties()->getTitle())->toBe('Customer List');
});
