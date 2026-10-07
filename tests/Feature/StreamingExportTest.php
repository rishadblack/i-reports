<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rishadblack\IReports\Exports\ReportExporter;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Services\ReportResolver;
use Rishadblack\IReports\Support\ReportContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    seedCustomers();
});

function pdfPageCount(string $pdf): int
{
    return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
}

/*
|--------------------------------------------------------------------------
| Print
|--------------------------------------------------------------------------
*/

it('streams print in chunks as one continuous table above the threshold', function () {
    config()->set('i-reports.stream_threshold', 2);
    config()->set('i-reports.export_chunk_size', 2);

    $response = $this->get(reportUrl(['export' => 'print']))->assertOk();
    $html = $response->streamedContent();

    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('content-type'))->toContain('text/html')
        ->and(substr_count($html, 'class="i-reports-table"'))->toBe(1)
        ->and(substr_count($html, '<thead>'))->toBe(1)
        ->and($html)->toContain('window.print()')
        ->and($html)->toContain('Joined')
        ->and($html)->not->toContain('alice-secret')
        ->and(strpos($html, 'Alice'))->toBeLessThan(strpos($html, 'Bob'))
        ->and(strpos($html, 'Bob'))->toBeLessThan(strpos($html, 'Charlie'))
        ->and(strpos($html, 'Charlie'))->toBeLessThan(strpos($html, 'BDT 425.50'));
});

it('renders the report view for print below the threshold', function () {
    $response = $this->get(reportUrl(['export' => 'print']))->assertOk();

    expect($response->baseResponse)->not->toBeInstanceOf(StreamedResponse::class);
});

it('reads streamed rows from the database in chunks', function () {
    config()->set('i-reports.stream_threshold', 0);
    config()->set('i-reports.export_chunk_size', 1);
    DB::enableQueryLog();

    $this->get(reportUrl(['export' => 'print']))->assertOk()->streamedContent();

    $chunkQueries = collect(DB::getQueryLog())->filter(fn (array $query) => preg_match('/limit 1 offset \d+/i', $query['query']));

    expect($chunkQueries->count())->toBeGreaterThanOrEqual(3);
});

it('applies filters, search and sort to streamed print', function () {
    config()->set('i-reports.stream_threshold', 0);

    $html = $this->get(reportUrl(['export' => 'print', 'filters' => ['city' => 'Dhaka'], 'sort_field' => 'amount', 'sort_direction' => 'desc']))
        ->assertOk()
        ->streamedContent();

    expect($html)->not->toContain('Alice')
        ->and(strpos($html, 'Charlie'))->toBeLessThan(strpos($html, 'Bob'))
        ->and($html)->toContain('BDT 175.00');
});

it('escapes streamed cells and keeps html columns raw', function () {
    config()->set('i-reports.stream_threshold', 0);
    Customer::create(['name' => '<b onmouseover=alert(1)>Mallory</b>', 'city' => 'Dhaka', 'amount' => 1]);

    $html = $this->get(reportUrl(['export' => 'print']))->assertOk()->streamedContent();

    expect($html)->toContain('&lt;b onmouseover=alert(1)&gt;Mallory&lt;/b&gt;')
        ->and($html)->not->toContain('<b onmouseover')
        ->and($html)->toContain('<a href="/customers/');
});

it('streams an empty report with headers and no rows', function () {
    config()->set('i-reports.stream_threshold', 0);

    $html = $this->get(reportUrl(['export' => 'print', 'filters' => ['city' => 'Nowhere']]))->assertOk()->streamedContent();

    expect(substr_count($html, 'class="i-reports-table"'))->toBe(1)
        ->and($html)->toContain('<th')
        ->and($html)->not->toContain('Alice')
        ->and($html)->toContain('</table>');
});

it('keeps group headers and subtotals right across chunk boundaries', function () {
    config()->set('i-reports.stream_threshold', 0);
    config()->set('i-reports.export_chunk_size', 1);

    $html = $this->get(reportUrl(['export' => 'print'], 'grouped-customers'))->assertOk()->streamedContent();

    expect(substr_count($html, '>Dhaka</td></tr>'))->toBe(1)
        ->and(substr_count($html, '>Khulna</td></tr>'))->toBe(1)
        ->and(substr_count($html, 'Subtotal'))->toBe(2)
        ->and(strpos($html, 'BDT 175.00'))->toBeLessThan(strpos($html, '>Khulna</td></tr>'))
        ->and(strpos($html, '>Khulna</td></tr>'))->toBeLessThan(strpos($html, 'BDT 250.50'))
        ->and(strpos($html, 'BDT 250.50'))->toBeLessThan(strpos($html, 'BDT 425.50'));
});

it('lets a report override the stream threshold', function () {
    config()->set('i-reports.stream_threshold', 0);

    $report = app(app(ReportResolver::class)->resolve('customers'));

    expect($report->setStreamThreshold(100)->getStreamThreshold())->toBe(100)
        ->and($report->shouldStream())->toBeFalse()
        ->and($report->setStreamThreshold(null)->shouldStream())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| PDF
|--------------------------------------------------------------------------
*/

it('writes a streamed pdf one chunk per WriteHTML call, each chunk on a new page', function () {
    config()->set('i-reports.stream_threshold', 0);
    config()->set('i-reports.pdf_chunk_size', 1);

    $pdf = $this->get(reportUrl(['export' => 'pdf']))->assertOk()->getContent();

    expect($pdf)->toStartWith('%PDF')->and(pdfPageCount($pdf))->toBe(3);
});

it('can stream pdf chunks without page breaks', function () {
    config()->set('i-reports.stream_threshold', 0);
    config()->set('i-reports.pdf_chunk_size', 1);
    config()->set('i-reports.pdf_chunk_page_break', false);

    $pdf = $this->get(reportUrl(['export' => 'pdf']))->assertOk()->getContent();

    expect(pdfPageCount($pdf))->toBe(1);
});

it('streams grouped reports to pdf', function () {
    config()->set('i-reports.stream_threshold', 0);
    config()->set('i-reports.pdf_chunk_size', 2);

    $pdf = $this->get(reportUrl(['export' => 'pdf'], 'grouped-customers'))->assertOk()->getContent();

    expect($pdf)->toStartWith('%PDF')->and(pdfPageCount($pdf))->toBe(2);
});

it('stores a streamed pdf through the exporter and the queue path', function () {
    Storage::fake('exports');
    config()->set('i-reports.stream_threshold', 0);

    app(ReportContext::class)->reset();
    (new RequestHelper(['report' => 'customers', 'export' => 'pdf']))->storeGlobally();
    $report = app(app(ReportResolver::class)->resolve('customers'));

    $path = app(ReportExporter::class)->store($report, 'pdf', 'exports');

    expect(Storage::disk('exports')->get($path))->toStartWith('%PDF');
});

it('splits a custom pdf view at chunk markers and survives a tiny pcre.backtrack_limit', function () {
    $limit = ini_get('pcre.backtrack_limit');
    ini_set('pcre.backtrack_limit', '1000');

    try {
        $pdf = $this->get(reportUrl(['export' => 'pdf', 'per_page' => 10], 'grouped-customers'))->assertOk()->getContent();

        expect($pdf)->toStartWith('%PDF')->and(pdfPageCount($pdf))->toBe(2);
    } finally {
        ini_set('pcre.backtrack_limit', (string) $limit);
    }
});

it('renders the chunk marker only for pdf', function (string $export, string $expected) {
    app(ReportContext::class)->setRequestData(['export' => $export]);

    expect(trim(Blade::render('<x-i-reports::chunk />')))->toBe($expected);
})->with([
    ['pdf', '<html-separator/>'],
    ['print', ''],
    ['view', ''],
    ['xlsx', ''],
]);

it('makes relative links absolute before writing to mpdf', function () {
    $report = app(app(ReportResolver::class)->resolve('customers'));
    $base = rtrim(url('/'), '/');

    expect($report->absolutizeLinks('<a href="/customers/1">a</a><a href="edit/2">b</a>'))
        ->toBe('<a href="'.$base.'/customers/1">a</a><a href="'.$base.'/edit/2">b</a>')
        ->and($report->absolutizeLinks("<a href='/x'>c</a>"))->toBe("<a href='".$base."/x'>c</a>")
        ->and($report->absolutizeLinks('<a href="https://example.com/a">d</a><a href="mailto:a@b.c">e</a><a href="tel:+880">f</a><a href="#top">g</a><a href="//cdn.test/x">h</a>'))
        ->toBe('<a href="https://example.com/a">d</a><a href="mailto:a@b.c">e</a><a href="tel:+880">f</a><a href="#top">g</a><a href="//cdn.test/x">h</a>')
        ->and($report->absolutizeLinks('<td>no links</td>'))->toBe('<td>no links</td>');
});
