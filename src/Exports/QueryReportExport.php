<?php

namespace Rishadblack\IReports\Exports;

use Generator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Views\Column;
use Throwable;

/**
 * Excel export that streams the query in chunks through the column definitions, under a
 * branded title block. Honours column types, exportFormat(), hideIn('xlsx'), map() and
 * aggregates; numbers stay numeric with an Excel number format.
 */
class QueryReportExport implements FromGenerator, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithTitle
{
    protected int $rowCount = 0;

    protected bool $hasTotalRow = false;

    /** @var array<int, int> */
    protected array $widths = [];

    /** @var array<string, mixed>|null */
    protected ?array $branding = null;

    public function __construct(
        protected BaseReportController $report,
        protected string $format = 'xlsx',
    ) {}

    public function generator(): Generator
    {
        $columns = $this->columns();
        $chunkSize = max(100, (int) config('i-reports.export_chunk_size', 1000));

        foreach (ExcelSheetStyler::titleRows($this->branding()) as $titleRow) {
            yield $this->counted($titleRow);
        }

        yield $this->counted($columns->map(fn (Column $column) => $column->getTitle())->all());

        foreach ($this->report->exportRows($chunkSize)->chunk($chunkSize) as $chunk) {
            foreach ($this->report->map(new Collection($chunk->all())) as $row) {
                yield $this->counted($columns->map(fn (Column $column) => $this->cellValue($column, $row))->all());
            }
        }

        $aggregates = $this->report->aggregates();

        if (count($aggregates) > 0) {
            $this->hasTotalRow = true;
            $labelPlaced = false;

            yield $this->counted($columns->map(function (Column $column) use ($aggregates, &$labelPlaced) {
                if (array_key_exists($column->getName(), $aggregates)) {
                    return $column->exportValue([$column->getColumnSelectName() => $aggregates[$column->getName()]]);
                }

                if (! $labelPlaced) {
                    $labelPlaced = true;

                    return 'Total';
                }

                return null;
            })->all());
        }
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $columns = $this->columns();
                $formats = [];
                $alignments = [];
                $widths = $this->widths;

                foreach ($columns->values() as $index => $column) {
                    $position = $index + 1;

                    if (in_array($column->getType(), ['number', 'money'], true)) {
                        $decimals = (int) ($column->getTypeOptions()['decimals'] ?? 2);
                        $formats[$position] = '#,##0'.($decimals > 0 ? '.'.str_repeat('0', $decimals) : '');
                    } elseif ($this->isExcelDate($column)) {
                        $formats[$position] = self::excelDateFormat((string) ($column->getTypeOptions()['format'] ?? 'Y-m-d'));
                        $widths[$position] = max($widths[$position] ?? 0, strlen($formats[$position]) + 2);
                        $alignments[$position] = $column->getAlign() ?? 'center';
                    }

                    if ($column->getAlign() !== null && ! isset($alignments[$position])) {
                        $alignments[$position] = $column->getAlign();
                    }
                }

                (new ExcelSheetStyler)->apply(
                    $event->sheet->getDelegate(),
                    $this->branding(),
                    $columns->count(),
                    $this->rowCount,
                    $this->hasTotalRow,
                    $widths,
                    $formats,
                    $this->report->getOrientation(),
                    $alignments,
                );
            },
        ];
    }

    /**
     * The cell value: real Excel dates for date columns (sortable and filterable in Excel),
     * otherwise the column's export value.
     */
    protected function cellValue(Column $column, mixed $row): mixed
    {
        if ($this->isExcelDate($column)) {
            $raw = $column->getValue($row);

            if ($raw === null || $raw === '') {
                return null;
            }

            try {
                return ExcelDate::PHPToExcel(Carbon::parse($raw));
            } catch (Throwable) {
                return $column->exportValue($row);
            }
        }

        return $column->exportValue($row);
    }

    /**
     * Translate a PHP date format (as given to Column::date()/datetime()) into an Excel number
     * format, so the cell shows the same text the report shows but stays a real date.
     */
    public static function excelDateFormat(string $phpFormat): string
    {
        $map = [
            'd' => 'dd', 'j' => 'd', 'D' => 'ddd', 'l' => 'dddd',
            'm' => 'mm', 'n' => 'm', 'M' => 'mmm', 'F' => 'mmmm',
            'Y' => 'yyyy', 'y' => 'yy',
            'H' => 'hh', 'G' => 'h', 'h' => 'hh', 'g' => 'h',
            'i' => 'mm', 's' => 'ss', 'A' => 'AM/PM', 'a' => 'am/pm',
        ];

        $format = '';
        $escaped = false;

        foreach (str_split($phpFormat) as $char) {
            if ($escaped) {
                $format .= '\\'.$char;
                $escaped = false;
            } elseif ($char === '\\') {
                $escaped = true;
            } elseif (isset($map[$char])) {
                $format .= $map[$char];
            } elseif (ctype_alpha($char)) {
                continue;
            } else {
                $format .= $char === ' ' || $char === '-' || $char === '/' || $char === ':' || $char === '.' || $char === ',' ? $char : '\\'.$char;
            }
        }

        return trim($format) === '' ? 'dd mmm yyyy' : $format;
    }

    protected function isExcelDate(Column $column): bool
    {
        return in_array($column->getType(), ['date', 'datetime'], true) && $column->getExportFormat() === null && $column->getFormat() === null;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        return (new SafeValueBinder)->bindValue($cell, $value);
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $this->report->getReportTitle()) ?? 'Report', 0, 31);
    }

    /**
     * Count the row and track column widths (title rows do not set widths; they are merged).
     *
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     */
    protected function counted(array $values): array
    {
        $this->rowCount++;

        if ($this->rowCount > ExcelSheetStyler::TITLE_ROWS) {
            foreach (array_values($values) as $index => $value) {
                $length = is_scalar($value) ? mb_strlen((string) $value) : 0;
                $this->widths[$index + 1] = max($this->widths[$index + 1] ?? 0, $length);
            }
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    protected function branding(): array
    {
        return $this->branding ??= $this->report->branding($this->report->total());
    }

    /**
     * @return SupportCollection<int, Column>
     */
    protected function columns(): SupportCollection
    {
        return $this->report->getVisibleColumns($this->format)->reject(fn (Column $column) => $column->isCustom() && $column->getFormat() === null && $column->getExportFormat() === null)->values();
    }
}
