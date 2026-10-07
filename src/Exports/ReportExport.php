<?php

namespace Rishadblack\IReports\Exports;

use Countable;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Rishadblack\IReports\BaseReportController;

/**
 * Excel export that converts the rendered Blade table ("view" excel mode), then adds the
 * branded title block above it and the shared table styling.
 */
class ReportExport implements FromView, WithCustomValueBinder, WithEvents
{
    protected string $currentView = '';

    /** @var array<string, mixed> */
    protected array $currentData = [];

    public function view(): View
    {
        return view($this->currentView, $this->currentData);
    }

    public function setCurrentView(string $currentView): static
    {
        $this->currentView = $currentView;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $currentData
     */
    public function setCurrentData(array $currentData): static
    {
        $this->currentData = $currentData;

        return $this;
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $branding = $this->currentData['branding'] ?? null;

                if (! is_array($branding)) {
                    return;
                }

                $datas = $this->currentData['datas'] ?? null;
                $branding['records'] ??= $datas instanceof Countable ? count($datas) : null;
                $sheet = $event->sheet->getDelegate();
                $report = $this->currentData['report'] ?? null;
                $orientation = $report instanceof BaseReportController ? $report->getOrientation() : 'portrait';

                // A view-based report keeps its own layout; the title block is added only when the
                // view has no header of its own (the layout leaves the header view out of CSV).
                if ($report instanceof BaseReportController && $report->usesViewForExports()) {
                    $isCsv = ($this->currentData['export'] ?? null) === 'csv';
                    $withTitle = $isCsv ? (bool) config('i-reports.csv.title_rows', true) : $report->getHeaderView() === null;

                    if ($withTitle) {
                        $sheet->insertNewRowBefore(1, ExcelSheetStyler::TITLE_ROWS);

                        foreach (ExcelSheetStyler::titleRows($branding) as $index => $titleRow) {
                            $sheet->setCellValue('A'.($index + 1), $titleRow[0]);
                        }
                    }

                    if (! $isCsv) {
                        (new ExcelSheetStyler)->applyLight($sheet, $branding, $orientation, $withTitle);
                    }

                    return;
                }

                $sheet->insertNewRowBefore(1, ExcelSheetStyler::TITLE_ROWS);

                foreach (ExcelSheetStyler::titleRows($branding) as $index => $titleRow) {
                    $sheet->setCellValue('A'.($index + 1), $titleRow[0]);
                }

                $columnCount = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
                $widths = [];

                for ($index = 1; $index <= $columnCount; $index++) {
                    $widths[$index] = 14;
                }

                (new ExcelSheetStyler)->apply(
                    $sheet,
                    $branding,
                    $columnCount,
                    $sheet->getHighestDataRow(),
                    count((array) ($this->currentData['aggregates'] ?? [])) > 0,
                    $widths,
                    [],
                    $orientation,
                );
            },
        ];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        return (new SafeValueBinder)->bindValue($cell, $value);
    }
}
