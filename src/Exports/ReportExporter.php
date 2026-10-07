<?php

namespace Rishadblack\IReports\Exports;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Support\Runtime;
use Rishadblack\IReports\Views\Column;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Produces downloads or stored files for every export format.
 */
class ReportExporter
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    public function download(BaseReportController $report, string $format): Response|StreamedResponse|BinaryFileResponse
    {
        return match ($format) {
            'csv' => $this->csvResponse($report),
            'xlsx' => $this->excelResponse($report),
            'pdf' => $this->pdfResponse($report),
            default => throw new InvalidArgumentException("Unsupported export format [{$format}]"),
        };
    }

    /**
     * Write the export to a disk and return the stored path.
     */
    public function store(BaseReportController $report, string $format, ?string $disk = null, ?string $directory = null): string
    {
        Runtime::prepareForExport();

        $disk ??= (string) config('i-reports.queue.disk', 'local');
        $directory = trim($directory ?? (string) config('i-reports.queue.path', 'i-reports/exports'), '/');
        $path = ($directory === '' ? '' : $directory.'/').$report->getFileName().'.'.$format;

        match ($format) {
            'csv' => $report->usesViewForExports()
                ? ExcelFacade::store($this->viewExport($report), $path, $disk, Excel::CSV)
                : Storage::disk($disk)->put($path, $this->csvContent($report)),
            'xlsx' => $this->storeExcel($report, $disk, $path),
            'pdf' => Storage::disk($disk)->put($path, $report->pdfContent()),
            default => throw new InvalidArgumentException("Unsupported export format [{$format}]"),
        };

        return $path;
    }

    /*
    |--------------------------------------------------------------------------
    | CSV: streamed straight from the query, no spreadsheet library involved
    |--------------------------------------------------------------------------
    */

    public function csvResponse(BaseReportController $report): StreamedResponse|BinaryFileResponse
    {
        $fileName = $report->getFileName().'.csv';

        if ($report->usesViewForExports()) {
            return ExcelFacade::download($this->viewExport($report), $fileName, Excel::CSV, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            $this->writeCsv($report, $handle);
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function csvContent(BaseReportController $report): string
    {
        $handle = fopen('php://temp', 'w+');
        $this->writeCsv($report, $handle);
        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /**
     * @param  resource  $handle
     */
    protected function writeCsv(BaseReportController $report, $handle): void
    {
        $delimiter = (string) config('i-reports.csv.delimiter', ',');
        $columns = $report->getVisibleColumns('csv')->reject(fn (Column $column) => $column->isCustom() && $column->getFormat() === null && $column->getExportFormat() === null)->values();
        $chunkSize = max(100, (int) config('i-reports.export_chunk_size', 1000));

        if (config('i-reports.csv.bom', true)) {
            fwrite($handle, "\xEF\xBB\xBF");
        }

        if (config('i-reports.csv.title_rows', true)) {
            foreach (ExcelSheetStyler::titleRows($report->branding($report->total())) as $titleRow) {
                if ($titleRow[0] !== '') {
                    fputcsv($handle, [$this->csvCell($titleRow[0])], $delimiter);
                }
            }

            fwrite($handle, "\n");
        }

        fputcsv($handle, $columns->map(fn (Column $column) => $column->getTitle())->all(), $delimiter);

        foreach ($report->exportRows($chunkSize)->chunk($chunkSize) as $chunk) {
            $rows = $report->map(new Collection($chunk->all()));

            foreach ($rows as $row) {
                fputcsv($handle, $columns->map(fn (Column $column) => $this->csvCell($column->exportValue($row)))->all(), $delimiter);
            }
        }

        $aggregates = $report->aggregates();

        if (count($aggregates) > 0) {
            $labelPlaced = false;

            fputcsv($handle, $columns->map(function (Column $column) use ($aggregates, &$labelPlaced) {
                if (array_key_exists($column->getName(), $aggregates)) {
                    return $this->csvCell($column->exportValue([$column->getColumnSelectName() => $aggregates[$column->getName()]]));
                }

                if (! $labelPlaced) {
                    $labelPlaced = true;

                    return 'Total';
                }

                return '';
            })->all(), $delimiter);
        }
    }

    protected function csvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /*
    |--------------------------------------------------------------------------
    | Excel
    |--------------------------------------------------------------------------
    */

    public function excelResponse(BaseReportController $report): BinaryFileResponse
    {
        return ExcelFacade::download($this->excelExport($report), $report->getFileName().'.xlsx', Excel::XLSX);
    }

    protected function storeExcel(BaseReportController $report, string $disk, string $path): void
    {
        ExcelFacade::store($this->excelExport($report), $path, $disk, Excel::XLSX);
    }

    public function excelExport(BaseReportController $report): object
    {
        if ($report->getExcelMode() === 'view') {
            return $this->viewExport($report);
        }

        return new QueryReportExport($report, 'xlsx');
    }

    /**
     * The report's Blade view converted to a sheet (Excel view mode, and CSV for view-based reports).
     */
    public function viewExport(BaseReportController $report): ReportExport
    {
        return (new ReportExport)
            ->setCurrentView($report->getViewName())
            ->setCurrentData($report->viewData(true));
    }

    /*
    |--------------------------------------------------------------------------
    | PDF
    |--------------------------------------------------------------------------
    */

    public function pdfResponse(BaseReportController $report): Response
    {
        return $report->pdfDownload();
    }
}
