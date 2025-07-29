<?php
namespace Rishadblack\IReports\Traits;

use Maatwebsite\Excel\Facades\Excel;
use Rishadblack\IReports\Exports\ReportExport;

trait WithExcel
{
    /**
     * Export to Excel using the given Blade HTML view.
     *
     * @param string $view     View path (e.g., 'reports.user-report')
     * @param array  $data     Data to pass to the view
     * @param string $type     File type: xlsx, csv, etc.
     * @param string $filename Custom filename (without extension)
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    protected function exportExcelFromView(
        string $view,
        array $data = [],
        string $type = 'xlsx',
        string $filename = ''
    ) {
        $export = new ReportExport();
        $export->setCurrentView($view);
        $export->setCurrentData($data);

        $filename = $filename ?: $this->getFileName();

        return Excel::download($export, "{$filename}.{$type}", constant(\Maatwebsite\Excel\Excel::class . '::' . strtoupper($type)));
    }
}
