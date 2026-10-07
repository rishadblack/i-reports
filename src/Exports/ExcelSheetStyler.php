<?php

namespace Rishadblack\IReports\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Gives an exported worksheet the branded title block, a styled heading row, striped and
 * bordered data, a totals row, frozen headings, an autofilter and print setup with a page
 * footer. All styles are applied to ranges, so the cost does not grow per cell.
 */
class ExcelSheetStyler
{
    /** Rows above the column headings: organisation, title, generated line, filters. */
    public const TITLE_ROWS = 4;

    /**
     * The title block rows, one value per row in the first column.
     *
     * @param  array<string, mixed>  $branding
     * @return array<int, array<int, string>>
     */
    public static function titleRows(array $branding): array
    {
        $generated = 'Generated '.$branding['generated_at'].($branding['generated_by'] ? ' by '.$branding['generated_by'] : '');
        $filters = implode('  ·  ', array_map(
            fn (array $applied): string => $applied['label'].': '.$applied['value'],
            (array) ($branding['filters'] ?? []),
        ));

        return [
            [(string) $branding['name']],
            [(string) $branding['title']],
            [$generated],
            [$filters === '' ? '' : 'Applied filters: '.$filters],
        ];
    }

    /**
     * @param  array<string, mixed>  $branding
     * @param  array<int, int>  $widths  Character width per column (1-based index)
     * @param  array<int, string>  $numberFormats  Excel number format per column (1-based index)
     */
    public function apply(Worksheet $sheet, array $branding, int $columnCount, int $lastRow, bool $hasTotalRow, array $widths = [], array $numberFormats = [], string $orientation = 'portrait'): void
    {
        $columnCount = max(1, $columnCount);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $accent = ltrim((string) ($branding['accent'] ?? '#1f2937'), '#');
        $headingRow = self::TITLE_ROWS + 1;
        $firstDataRow = $headingRow + 1;
        $lastDataRow = $hasTotalRow ? $lastRow - 1 : $lastRow;

        $this->styleTitleBlock($sheet, $lastColumn, $accent);
        $this->styleHeading($sheet, "A{$headingRow}:{$lastColumn}{$headingRow}", $accent, $headingRow);

        if ($lastDataRow >= $firstDataRow) {
            $dataRange = "A{$firstDataRow}:{$lastColumn}{$lastDataRow}";
            $sheet->getStyle($dataRange)->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '1F2937']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $stripe = new Conditional;
            // Stripe every second data row: the row parity opposite to the first data row.
            $stripe->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition('MOD(ROW(),2)='.(($firstDataRow + 1) % 2));
            $stripe->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB('F8FAFC');
            $sheet->getStyle($dataRange)->setConditionalStyles([$stripe]);
        }

        if ($hasTotalRow && $lastRow > $headingRow) {
            $sheet->getStyle("A{$lastRow}:{$lastColumn}{$lastRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2F6']],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '94A3B8']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
                ],
            ]);
        }

        foreach ($numberFormats as $index => $format) {
            $letter = Coordinate::stringFromColumnIndex($index);
            $sheet->getStyle("{$letter}{$firstDataRow}:{$letter}".max($firstDataRow, $lastRow))->getNumberFormat()->setFormatCode($format);
            $sheet->getStyle("{$letter}{$headingRow}:{$letter}".max($firstDataRow, $lastRow))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        for ($index = 1; $index <= $columnCount; $index++) {
            $letter = Coordinate::stringFromColumnIndex($index);
            $sheet->getColumnDimension($letter)->setWidth(min(60, max(10, ($widths[$index] ?? 12) + 3)));
        }

        $sheet->freezePane("A{$firstDataRow}");

        if ($lastDataRow >= $headingRow) {
            $sheet->setAutoFilter("A{$headingRow}:{$lastColumn}".max($headingRow, $lastDataRow));
        }

        $this->pageSetup($sheet, $branding, $headingRow, $orientation);
    }

    protected function styleTitleBlock(Worksheet $sheet, string $lastColumn, string $accent): void
    {
        foreach (range(1, self::TITLE_ROWS) as $row) {
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        }

        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => strtoupper($accent)]]]);
        $sheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '0F172A']]]);
        $sheet->getStyle('A3:A4')->applyFromArray(['font' => ['size' => 9, 'color' => ['rgb' => '64748B']]]);
        $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => strtoupper($accent)]]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);
    }

    protected function styleHeading(Worksheet $sheet, string $range, string $accent, int $row): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => strtoupper($accent)]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => strtoupper($accent)]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    /**
     * @param  array<string, mixed>  $branding
     */
    protected function pageSetup(Worksheet $sheet, array $branding, int $headingRow, string $orientation): void
    {
        $sheet->getPageSetup()
            ->setOrientation($orientation === 'landscape' ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($headingRow, $headingRow);

        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);

        $footerText = fn (string $text): string => str_replace('&', '&&', $text);
        $sheet->getHeaderFooter()->setOddFooter(
            '&L&8'.$footerText($branding['name'].' · '.$branding['title'])
            .'&C&8'.$footerText((string) $branding['generated_at'])
            .'&R&8Page &P of &N'
        );
    }
}
