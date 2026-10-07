<?php

namespace Rishadblack\IReports\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Gives an exported worksheet a report look: a branded title block, a styled heading row,
 * padded, striped and lightly bordered data, a totals row, frozen headings, an autofilter,
 * no gridlines, and print setup with a page footer. Styles are applied to ranges, so the
 * cost does not grow per cell.
 */
class ExcelSheetStyler
{
    /** Rows above the column headings: organisation, title, meta line (generated, prepared by, records), filters. */
    public const TITLE_ROWS = 4;

    /**
     * The title block rows as plain text, one value per row in the first column (CSV uses
     * these as they are; Excel shows the same text as rich text).
     *
     * @param  array<string, mixed>  $branding
     * @return array<int, array<int, string>>
     */
    public static function titleRows(array $branding, ?int $total = null): array
    {
        return array_map(
            fn (array $segments): array => [implode('', array_column($segments, 0))],
            self::titleSegments($branding, $total),
        );
    }

    /**
     * The title block as styled text segments per row: [text, style] where style is one of
     * name, title, label, value, muted or separator.
     *
     * @param  array<string, mixed>  $branding
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    public static function titleSegments(array $branding, ?int $total = null): array
    {
        $total ??= isset($branding['records']) && is_int($branding['records']) ? $branding['records'] : null;
        $separator = ['   |   ', 'separator'];

        $first = [[(string) $branding['name'], 'name']];
        $subtitle = implode('  ·  ', array_filter([$branding['tagline'] ?? null, ...(array) ($branding['details'] ?? [])]));

        if ($subtitle !== '') {
            $first[] = ['    '.$subtitle, 'muted'];
        }

        $meta = [['Generated: ', 'label'], [(string) $branding['generated_at'], 'value']];

        if (! empty($branding['generated_by'])) {
            array_push($meta, $separator, ['Prepared by: ', 'label'], [(string) $branding['generated_by'], 'value']);
        }

        if ($total !== null) {
            array_push($meta, $separator, ['Records: ', 'label'], [number_format($total), 'value']);
        }

        $filters = [];

        if (config('i-reports.branding.show_filters', true)) {
            $filters[] = ['Applied filters: ', 'label'];
            $applied = array_values((array) ($branding['filters'] ?? []));

            foreach ($applied as $index => $filter) {
                if ($index > 0) {
                    $filters[] = $separator;
                }

                array_push($filters, [$filter['label'].': ', 'muted'], [(string) $filter['value'], 'value']);
            }

            if ($applied === []) {
                $filters[] = ['None — showing all records', 'muted'];
            }
        }

        return [$first, [[(string) $branding['title'], 'title']], $meta, $filters];
    }

    /**
     * @param  array<string, mixed>  $branding
     * @param  array<int, int>  $widths  Character width per column (1-based index)
     * @param  array<int, string>  $numberFormats  Excel number format per column (1-based index)
     * @param  array<int, string>  $alignments  left|center|right per column (1-based index)
     */
    public function apply(Worksheet $sheet, array $branding, int $columnCount, int $lastRow, bool $hasTotalRow, array $widths = [], array $numberFormats = [], string $orientation = 'portrait', array $alignments = []): void
    {
        $columnCount = max(1, $columnCount);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $accent = strtoupper(ltrim((string) ($branding['accent'] ?? '#1f2937'), '#'));
        $headingRow = self::TITLE_ROWS + 1;
        $firstDataRow = $headingRow + 1;
        $lastDataRow = $hasTotalRow ? $lastRow - 1 : $lastRow;
        $tableEnd = max($headingRow, $lastRow);

        $this->styleWorkbook($sheet, $branding, $accent);
        $this->styleTitleBlock($sheet, $branding, $lastColumn, $accent);
        $this->styleHeading($sheet, "A{$headingRow}:{$lastColumn}{$headingRow}", $accent, $headingRow);

        if ($lastDataRow >= $firstDataRow) {
            $dataRange = "A{$firstDataRow}:{$lastColumn}{$lastDataRow}";
            $sheet->getStyle($dataRange)->applyFromArray([
                'font' => ['size' => 10, 'color' => ['rgb' => '1F2937']],
                'borders' => [
                    'horizontal' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']],
                    'outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            ]);

            $stripe = new Conditional;
            // Stripe every second data row: the row parity opposite to the first data row.
            $stripe->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition('MOD(ROW(),2)='.(($firstDataRow + 1) % 2));
            $stripe->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB('F3F6F9');
            $sheet->getStyle($dataRange)->setConditionalStyles([$stripe]);
        }

        if ($hasTotalRow && $lastRow > $headingRow) {
            $sheet->getStyle("A{$lastRow}:{$lastColumn}{$lastRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '0F172A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EDF3']],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => $accent]],
                    'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => $accent]],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            ]);
            $sheet->getRowDimension($lastRow)->setRowHeight(22);
        }

        foreach ($numberFormats as $index => $format) {
            $letter = Coordinate::stringFromColumnIndex($index);
            $sheet->getStyle("{$letter}{$firstDataRow}:{$letter}".max($firstDataRow, $lastRow))->getNumberFormat()->setFormatCode($format);
        }

        foreach ($alignments as $index => $align) {
            $horizontal = match ($align) {
                'right' => Alignment::HORIZONTAL_RIGHT,
                'center' => Alignment::HORIZONTAL_CENTER,
                default => null,
            };

            if ($horizontal !== null) {
                $letter = Coordinate::stringFromColumnIndex($index);
                $sheet->getStyle("{$letter}{$headingRow}:{$letter}{$tableEnd}")->getAlignment()->setHorizontal($horizontal);
            }
        }

        for ($index = 1; $index <= $columnCount; $index++) {
            $letter = Coordinate::stringFromColumnIndex($index);
            $sheet->getColumnDimension($letter)->setWidth(min(60, max(12, ($widths[$index] ?? 12) + 4)));
        }

        $sheet->freezePane("A{$firstDataRow}");
        $sheet->setSelectedCell("A{$firstDataRow}");

        if ($lastDataRow >= $headingRow) {
            $sheet->setAutoFilter("A{$headingRow}:{$lastColumn}".max($headingRow, $lastDataRow));
        }

        $this->pageSetup($sheet, $branding, $headingRow, $orientation);
    }

    /**
     * Styling for a sheet converted from a free-form report view (view-based exports): the
     * layout comes from the view, so only workbook properties, column widths, print setup and
     * the page footer are added, plus the branded title block when $withTitle.
     *
     * @param  array<string, mixed>  $branding
     */
    public function applyLight(Worksheet $sheet, array $branding, string $orientation = 'portrait', bool $withTitle = false): void
    {
        $lastColumn = $sheet->getHighestDataColumn();
        $accent = strtoupper(ltrim((string) ($branding['accent'] ?? '#1f2937'), '#'));

        $this->styleWorkbook($sheet, $branding, $accent, gridlines: true);

        if ($withTitle) {
            $this->styleTitleBlock($sheet, $branding, $lastColumn, $accent);
        }

        for ($index = 1; $index <= Coordinate::columnIndexFromString($lastColumn); $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        $this->pageSetup($sheet, $branding, 0, $orientation);
    }

    /**
     * @param  array<string, mixed>  $branding
     */
    protected function styleWorkbook(Worksheet $sheet, array $branding, string $accent, bool $gridlines = false): void
    {
        $book = $sheet->getParent();

        if ($book !== null) {
            $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
            $book->getProperties()
                ->setCreator((string) $branding['name'])
                ->setLastModifiedBy((string) $branding['name'])
                ->setCompany((string) $branding['name'])
                ->setTitle((string) $branding['title'])
                ->setSubject((string) $branding['title']);
        }

        $sheet->setShowGridlines($gridlines);
        $sheet->setPrintGridlines(false);
        $sheet->getTabColor()->setRGB($accent);

        if (! $gridlines) {
            $sheet->getDefaultRowDimension()->setRowHeight(18);
        }
    }

    /**
     * Brand rows (name, title) above an accent rule, then a light meta band (generated,
     * prepared by, records, filters), each written as rich text.
     *
     * @param  array<string, mixed>  $branding
     */
    protected function styleTitleBlock(Worksheet $sheet, array $branding, string $lastColumn, string $accent): void
    {
        $fonts = [
            'name' => ['bold' => true, 'size' => 16, 'color' => $accent],
            'title' => ['bold' => true, 'size' => 13, 'color' => '0F172A'],
            'label' => ['bold' => true, 'size' => 9, 'color' => '64748B'],
            'value' => ['bold' => true, 'size' => 9, 'color' => '0F172A'],
            'muted' => ['bold' => false, 'size' => 9, 'color' => '64748B'],
            'separator' => ['bold' => false, 'size' => 9, 'color' => 'CBD5E1'],
        ];

        foreach (self::titleSegments($branding) as $index => $segments) {
            $row = $index + 1;
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");

            if ($segments === []) {
                continue;
            }

            $text = new RichText;

            foreach ($segments as [$content, $style]) {
                $font = $text->createTextRun($content)->getFont();

                if ($font !== null) {
                    $font->setName('Calibri')->setBold($fonts[$style]['bold'])->setSize($fonts[$style]['size'])->getColor()->setRGB($fonts[$style]['color']);
                }
            }

            $sheet->getCell("A{$row}")->setValue($text);
        }

        $sheet->getStyle("A1:{$lastColumn}".self::TITLE_ROWS)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB($accent);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('0F172A');
        $sheet->getStyle("A2:{$lastColumn}2")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB($accent);
        $sheet->getStyle("A3:{$lastColumn}4")->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '334155']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
            'alignment' => ['indent' => 1],
        ]);
        $sheet->getStyle("A4:{$lastColumn}4")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(24);
        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension(4)->setRowHeight(20);

        $this->placeLogo($sheet, $branding);
    }

    /**
     * Put the configured logo at the top left and indent the brand rows past it.
     *
     * @param  array<string, mixed>  $branding
     */
    protected function placeLogo(Worksheet $sheet, array $branding): void
    {
        $path = $branding['logo_path'] ?? null;
        $size = is_string($path) && is_file($path) ? @getimagesize($path) : false;

        if ($size === false || $size[1] === 0) {
            return;
        }

        $height = 54;
        $width = (int) round($size[0] * $height / $size[1]);

        $drawing = new Drawing;
        $drawing->setPath($path);
        $drawing->setName('Logo')->setCoordinates('A1')->setOffsetX(6)->setOffsetY(6);
        $drawing->setResizeProportional(false);
        $drawing->setHeight($height)->setWidth($width);
        $drawing->setWorksheet($sheet);

        $sheet->getStyle('A1:A2')->getAlignment()->setIndent((int) ceil(($width + 16) / 8));
    }

    protected function styleHeading(Worksheet $sheet, string $range, string $accent, int $row): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $accent]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $accent]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true, 'indent' => 1],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(26);
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
            ->setHorizontalCentered(true);

        if ($headingRow > 0) {
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headingRow, $headingRow);
        }

        $sheet->getPageMargins()->setTop(0.6)->setBottom(0.6)->setLeft(0.4)->setRight(0.4)->setHeader(0.3)->setFooter(0.3);

        $escape = fn (string $text): string => str_replace('&', '&&', $text);
        $footer = '&L&8&B'.$escape((string) $branding['name']).'&B · '.$escape((string) $branding['title'])
            .'&C&8'.$escape((string) ($branding['footer_text'] ?? $branding['generated_at']))
            .'&R&8Page &P of &N';
        $header = '&L&8&B'.$escape((string) $branding['name'])
            .'&B&R&8'.$escape($branding['title'].' · '.$branding['generated_at']);

        // Page 1 carries the title block, so the running header starts on page 2.
        $sheet->getHeaderFooter()
            ->setDifferentFirst(true)
            ->setOddHeader($header)
            ->setOddFooter($footer)
            ->setFirstHeader('')
            ->setFirstFooter($footer);
    }
}
