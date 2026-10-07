<?php

namespace Rishadblack\IReports\Traits;

use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Rishadblack\IReports\Exports\ReportExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait WithExcel
{
    /**
     * Export the given Blade view as a spreadsheet (kept for custom layouts).
     *
     * @param  array<string, mixed>  $data
     */
    protected function exportExcelFromView(string $view, array $data = [], string $type = 'xlsx', string $filename = ''): BinaryFileResponse
    {
        $export = (new ReportExport)->setCurrentView($view)->setCurrentData($data);
        $filename = $filename ?: $this->getFileName();
        $writer = strtoupper($type) === 'CSV' ? Excel::CSV : Excel::XLSX;

        return ExcelFacade::download($export, "{$filename}.{$type}", $writer);
    }
}
