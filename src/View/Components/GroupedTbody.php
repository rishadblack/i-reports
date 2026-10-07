<?php

namespace Rishadblack\IReports\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Views\Column;

/**
 * Renders rows grouped by the report's setGroupBy() column, with a header and subtotals per group.
 */
class GroupedTbody extends BaseComponent
{
    public mixed $rows;

    /** @var array<int, Column>|null */
    public ?array $columns;

    public ?string $groupStyle;

    public ?string $subtotalStyle;

    public string $subtotalLabel;

    public bool $showSubtotals;

    /** @var array<int, array{key: mixed, label: string, rows: Collection<int, mixed>, subtotals: array<string, mixed>}> */
    public array $groups = [];

    /** @var array<int, Column> */
    public array $visibleColumns = [];

    public int $columnCount = 0;

    /**
     * @param  array<int, Column>|null  $columns
     */
    public function __construct(mixed $rows, ?array $columns = null, ?string $groupStyle = null, ?string $subtotalStyle = null, string $subtotalLabel = 'Subtotal', bool $showSubtotals = true)
    {
        $this->rows = $rows;
        $this->columns = $columns;
        $this->groupStyle = $groupStyle;
        $this->subtotalStyle = $subtotalStyle;
        $this->subtotalLabel = $subtotalLabel;
        $this->showSubtotals = $showSubtotals;
    }

    public function render(): View
    {
        $report = $this->context()->get('report');
        $export = $this->export();

        $this->visibleColumns = collect($this->columns ?? $this->context()->getColumns())
            ->filter(fn (Column $column) => $this->context()->isColumnVisible($column, $export))
            ->values()
            ->all();
        $this->columnCount = count($this->visibleColumns);

        $this->groups = $report instanceof BaseReportController
            ? $report->groupRows($this->rows)
            : [['key' => null, 'label' => '', 'rows' => (new Collection($this->rows instanceof \Traversable ? iterator_to_array($this->rows) : (array) $this->rows))->values(), 'subtotals' => []]];

        $this->groupStyle = $this->mergeStyles($this->defaultStyle('group'), $this->groupStyle);
        $this->subtotalStyle = $this->mergeStyles($this->defaultStyle('aggregate'), $this->subtotalStyle);

        return view('i-reports::components.grouped-tbody');
    }

    /**
     * @param  array<string, mixed>  $subtotals
     */
    public function subtotalFor(Column $column, array $subtotals): string
    {
        return array_key_exists($column->getName(), $subtotals) ? $column->formatAggregate($subtotals[$column->getName()]) : '';
    }
}
