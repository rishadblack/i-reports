<?php

namespace Rishadblack\IReports\Support;

use Closure;
use Rishadblack\IReports\Traits\StyleMergerTrait;
use Rishadblack\IReports\Views\Column;

/**
 * Builds table HTML for a fixed list of columns without Blade components.
 *
 * Cell styles that do not depend on the row are merged once per column, so rendering
 * cost grows with the number of cells only. All values go through Column::render(),
 * which escapes unless the column allows HTML.
 */
class RowHtml
{
    use StyleMergerTrait;

    /** @var array<int, Column> */
    protected array $columns;

    /** @var array<int, string|null> Precomputed cell style per column; null when it depends on the row. */
    protected array $staticStyles = [];

    /** @var array<int, string|null> The same for striped (even) rows. */
    protected array $stripedStyles = [];

    protected string $tdStyle;

    protected string $trStyle;

    protected string $zebraStyle;

    protected int $rowIndex = 0;

    /**
     * @param  array<int, Column>  $columns
     */
    public function __construct(array $columns, protected string $export, ?string $rowStyle = null)
    {
        $this->columns = array_values($columns);
        $this->tdStyle = (string) config('i-reports.default_style.td', '');
        $this->trStyle = $this->mergeStyles((string) config('i-reports.default_style.tr', ''), $rowStyle);
        $this->zebraStyle = (string) config('i-reports.default_style.zebra', '');

        foreach ($this->columns as $index => $column) {
            if ($column->getStyle() instanceof Closure) {
                $this->staticStyles[$index] = $this->stripedStyles[$index] = null;

                continue;
            }

            $this->staticStyles[$index] = $this->mergeStyles($this->tdStyle, $column->applyStyle());
            $this->stripedStyles[$index] = $this->mergeStyles($this->mergeStyles($this->tdStyle, $this->zebraStyle), $column->applyStyle());
        }
    }

    public function columnCount(): int
    {
        return count($this->columns);
    }

    /**
     * The header row.
     */
    public function header(): string
    {
        $thStyle = (string) config('i-reports.default_style.th', '');
        $html = '<tr>';

        foreach ($this->columns as $column) {
            $html .= '<th style="'.e($this->mergeStyles($thStyle, $column->applyStyle())).'">'.e($column->getTitle()).'</th>';
        }

        return $html.'</tr>';
    }

    /**
     * Body rows for the given records.
     *
     * @param  iterable<int, mixed>  $rows
     */
    public function rows(iterable $rows): string
    {
        $html = '';

        foreach ($rows as $row) {
            $html .= $this->row($row);
        }

        return $html;
    }

    public function row(mixed $row): string
    {
        $striped = $this->zebraStyle !== '' && ($this->rowIndex++ % 2 === 1);
        $styles = $striped ? $this->stripedStyles : $this->staticStyles;
        $base = $striped ? $this->mergeStyles($this->tdStyle, $this->zebraStyle) : $this->tdStyle;
        $html = $this->trStyle === '' ? '<tr>' : '<tr style="'.e($this->trStyle).'">';

        foreach ($this->columns as $index => $column) {
            $style = $styles[$index] ?? $this->mergeStyles($base, $column->applyStyle($row));
            $html .= '<td style="'.e($style).'">'.$column->render($row, $this->export).'</td>';
        }

        return $html.'</tr>';
    }

    /**
     * A full-width row, used for group headers. The label must already be escaped.
     */
    public function spanRow(string $label, string $style): string
    {
        return '<tr><td colspan="'.$this->columnCount().'" style="'.e($style).'">'.$label.'</td></tr>';
    }

    /**
     * A row of aggregate values (totals or subtotals). The label goes in the first non-aggregate cell.
     *
     * @param  array<string, mixed>  $values  Aggregate values keyed by column name
     */
    public function aggregateRow(array $values, string $label, string $style): string
    {
        $html = '<tr>';
        $labelPlaced = false;

        foreach ($this->columns as $column) {
            $align = $column->getAlign() ? ' text-align: '.$column->getAlign().';' : '';

            if (array_key_exists($column->getName(), $values)) {
                $text = e($column->formatAggregate($values[$column->getName()]));
            } elseif (! $labelPlaced) {
                $text = e($label);
                $labelPlaced = true;
            } else {
                $text = '';
            }

            $html .= '<td style="'.e($style.$align).'">'.$text.'</td>';
        }

        return $html.'</tr>';
    }
}
