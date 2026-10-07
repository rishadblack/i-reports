<?php

namespace Rishadblack\IReports\Exports;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Mpdf\HTMLParserMode;
use Mpdf\Output\Destination;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Support\RowHtml;
use Rishadblack\IReports\Support\Runtime;
use Rishadblack\IReports\Views\Column;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders very large reports for print and PDF without holding every row or the whole
 * HTML document in memory.
 *
 * Rows are read from the database in chunks. Each chunk becomes one complete table that is
 * written straight to mPDF (WriteHTML per chunk, like laravel-mpdf's chunkLoadView, so no
 * piece reaches pcre.backtrack_limit) or flushed straight to the browser for print. Group
 * headers, group subtotals and the grand total row are kept correct across chunk boundaries.
 */
class ChunkedReportWriter
{
    /**
     * The PDF bytes for the report.
     */
    public function pdf(BaseReportController $report): string
    {
        Runtime::prepareForExport();
        $report->publishToContext();

        $mpdf = $report->makeMpdf();
        $mpdf->WriteHTML(view('i-reports::partials.styles')->render(), HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($this->top($report, 'pdf'), HTMLParserMode::HTML_BODY);

        $pageBreak = (bool) config('i-reports.pdf_chunk_page_break', true);
        $first = true;

        $this->tables($report, 'pdf', $this->pdfChunkSize(), function (string $table) use ($mpdf, $pageBreak, $report, &$first) {
            $mpdf->WriteHTML(($first || ! $pageBreak ? '' : '<pagebreak />').$report->absolutizeLinks($table), HTMLParserMode::HTML_BODY);
            $first = false;
        });

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * A print page streamed to the browser chunk by chunk, as one continuous table.
     */
    public function printResponse(BaseReportController $report): StreamedResponse
    {
        Runtime::prepareForExport();
        $report->publishToContext();

        // The callback runs after the controller returns; carry the request state with it.
        $requestData = $report->context()->getRequestData();

        return response()->stream(function () use ($report, $requestData) {
            $report->context()->setRequestData($requestData);
            $report->publishToContext();

            echo $this->top($report, 'print');
            $this->flush();

            $this->tables($report, 'print', $this->chunkSize(), function (string $html) {
                echo $html;
                $this->flush();
            }, continuous: true);

            echo view('i-reports::stream.bottom')->render();
            $this->flush();
        }, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Emit the report body. With $continuous the output is one table split across emits
     * (for browsers); otherwise every emit is a complete table (for mPDF).
     *
     * @param  Closure(string): void  $emit
     */
    public function tables(BaseReportController $report, string $export, int $chunkSize, Closure $emit, bool $continuous = false): void
    {
        $columns = $report->getVisibleColumns($export)->all();
        $html = new RowHtml($columns, $export);
        $groupColumn = $report->getGroupBy() ? $report->getColumnByName($report->getGroupBy()) : null;
        $aggregateColumns = array_values(array_filter($columns, fn (Column $column) => $column->hasAggregate()));

        $tableStyle = 'border-collapse: collapse; width: 100%;';
        $open = '<table class="i-reports-table" style="'.$tableStyle.'"><thead>'.$html->header().'</thead><tbody>';
        $groupStyle = (string) config('i-reports.default_style.group', '');
        $aggregateStyle = (string) config('i-reports.default_style.aggregate', '');

        $currentGroup = null;
        $groupTotals = $this->emptyTotals($aggregateColumns);
        $pending = null;
        $started = false;

        $closeGroup = function () use (&$groupTotals, $aggregateColumns, $html, $aggregateStyle, $groupColumn): string {
            if ($groupColumn === null || count($aggregateColumns) === 0) {
                return '';
            }

            $row = $html->aggregateRow($this->resolveTotals($groupTotals), 'Subtotal', $aggregateStyle);
            $groupTotals = $this->emptyTotals($aggregateColumns);

            return $row;
        };

        foreach ($report->exportBuilder()->lazy($chunkSize)->chunk($chunkSize) as $chunk) {
            $body = '';

            foreach ($report->map(new Collection($chunk->all())) as $row) {
                if ($groupColumn !== null) {
                    $key = (string) $groupColumn->getValue($row);

                    if ($key !== $currentGroup) {
                        if ($currentGroup !== null) {
                            $body .= $closeGroup();
                        }

                        $currentGroup = $key;
                        $body .= $html->spanRow($groupColumn->render($row, $export), $groupStyle);
                    }

                    $this->accumulate($groupTotals, $aggregateColumns, $row);
                }

                $body .= $html->row($row);
            }

            if ($pending !== null) {
                $emit($continuous ? $pending : $open.$pending.'</tbody></table>');
                $started = true;
            } elseif ($continuous && ! $started) {
                $emit($open);
                $started = true;
            }

            $pending = $body;
        }

        $last = ($pending ?? '').($currentGroup !== null ? $closeGroup() : '');
        $aggregates = $report->aggregates();

        // A body row, not <tfoot>: mPDF and browsers repeat a tfoot on every printed page.
        if (count($aggregates) > 0) {
            $last .= $html->aggregateRow($aggregates, 'Total', $aggregateStyle);
        }

        if ($continuous) {
            $emit(($started ? '' : $open).$last.'</tbody></table>');

            return;
        }

        $emit($open.$last.'</tbody></table>');
    }

    protected function top(BaseReportController $report, string $export): string
    {
        return view('i-reports::stream.top', [
            'export' => $export,
            'title' => $report->getReportTitle(),
            'headerTitle' => $report->getHeaderTitle(),
            'headerView' => $report->getHeaderView(),
            'pdfHeaderView' => $report->getPdfHeaderView(),
            'pdfFooterView' => $report->getPdfFooterView(),
            'branding' => $report->branding(),
        ])->render();
    }

    protected function chunkSize(): int
    {
        return max(1, (int) config('i-reports.export_chunk_size', 1000));
    }

    protected function pdfChunkSize(): int
    {
        return max(1, (int) config('i-reports.pdf_chunk_size', 500));
    }

    protected function flush(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }

    /**
     * @param  array<int, Column>  $columns
     * @return array<string, array{aggregate: string, count: int, numeric: int, sum: float, min: float|null, max: float|null}>
     */
    protected function emptyTotals(array $columns): array
    {
        $totals = [];

        foreach ($columns as $column) {
            $totals[$column->getName()] = ['aggregate' => (string) $column->getAggregate(), 'count' => 0, 'numeric' => 0, 'sum' => 0.0, 'min' => null, 'max' => null];
        }

        return $totals;
    }

    /**
     * @param  array<string, array{aggregate: string, count: int, numeric: int, sum: float, min: float|null, max: float|null}>  $totals
     * @param  array<int, Column>  $columns
     */
    protected function accumulate(array &$totals, array $columns, mixed $row): void
    {
        foreach ($columns as $column) {
            $value = $column->getValue($row);

            if ($value === null || $value === '') {
                continue;
            }

            $entry = &$totals[$column->getName()];
            $entry['count']++;

            if (is_numeric($value)) {
                $number = (float) $value;
                $entry['numeric']++;
                $entry['sum'] += $number;
                $entry['min'] = $entry['min'] === null ? $number : min($entry['min'], $number);
                $entry['max'] = $entry['max'] === null ? $number : max($entry['max'], $number);
            }

            unset($entry);
        }
    }

    /**
     * @param  array<string, array{aggregate: string, count: int, numeric: int, sum: float, min: float|null, max: float|null}>  $totals
     * @return array<string, mixed>
     */
    protected function resolveTotals(array $totals): array
    {
        $values = [];

        foreach ($totals as $name => $entry) {
            $values[$name] = match ($entry['aggregate']) {
                'sum' => $entry['sum'],
                'avg' => $entry['numeric'] > 0 ? $entry['sum'] / $entry['numeric'] : null,
                'count' => $entry['count'],
                'min' => $entry['min'],
                'max' => $entry['max'],
                default => null,
            };
        }

        return $values;
    }
}
