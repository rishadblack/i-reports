<?php

namespace Rishadblack\IReports\Exports;

use Generator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Views\Column;

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

        foreach ($this->report->exportBuilder()->lazy($chunkSize)->chunk($chunkSize) as $chunk) {
            foreach ($this->report->map(new Collection($chunk->all())) as $row) {
                yield $this->counted($columns->map(fn (Column $column) => $column->exportValue($row))->all());
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

                foreach ($columns->values() as $index => $column) {
                    if (in_array($column->getType(), ['number', 'money'], true)) {
                        $decimals = (int) ($column->getTypeOptions()['decimals'] ?? 2);
                        $formats[$index + 1] = '#,##0'.($decimals > 0 ? '.'.str_repeat('0', $decimals) : '');
                    }
                }

                (new ExcelSheetStyler)->apply(
                    $event->sheet->getDelegate(),
                    $this->branding(),
                    $columns->count(),
                    $this->rowCount,
                    $this->hasTotalRow,
                    $this->widths,
                    $formats,
                    $this->report->getOrientation(),
                );
            },
        ];
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
        return $this->branding ??= $this->report->branding();
    }

    /**
     * @return SupportCollection<int, Column>
     */
    protected function columns(): SupportCollection
    {
        return $this->report->getVisibleColumns($this->format)->reject(fn (Column $column) => $column->isCustom() && $column->getFormat() === null && $column->getExportFormat() === null)->values();
    }
}
