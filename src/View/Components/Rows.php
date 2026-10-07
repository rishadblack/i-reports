<?php

namespace Rishadblack\IReports\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Rishadblack\IReports\Support\RowHtml;
use Rishadblack\IReports\Views\Column;

/**
 * Renders table rows for every visible column without instantiating a component per cell.
 *
 * Use it instead of nested tr/td loops when the layout is a plain grid; it is several times
 * faster and uses far less memory on large exports. Output is escaped through Column::render().
 */
class Rows extends BaseComponent
{
    public mixed $rows;

    /** @var array<int, Column>|null */
    public ?array $columns;

    public ?string $rowStyle;

    public string $html = '';

    /**
     * @param  array<int, Column>|null  $columns
     */
    public function __construct(mixed $rows, ?array $columns = null, ?string $rowStyle = null)
    {
        $this->rows = $rows;
        $this->columns = $columns;
        $this->rowStyle = $rowStyle;
    }

    public function render(): View
    {
        $export = $this->export();

        $visibleColumns = collect($this->columns ?? $this->context()->getColumns())
            ->filter(fn (Column $column) => $this->context()->isColumnVisible($column, $export))
            ->values()
            ->all();

        $rows = $this->rows instanceof LengthAwarePaginator ? $this->rows->getCollection() : $this->rows;

        $this->html = (new RowHtml($visibleColumns, $export, $this->rowStyle))->rows(is_iterable($rows) ? $rows : []);

        return view('i-reports::components.rows');
    }
}
