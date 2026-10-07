<?php

namespace Rishadblack\IReports\View\Components;

use Illuminate\Contracts\View\View;
use Rishadblack\IReports\Views\Column;

/**
 * Renders a footer row with the aggregates (sum, avg, ...) requested on columns.
 */
class Aggregates extends BaseComponent
{
    /** @var array<string, mixed>|null */
    public ?array $aggregates;

    /** @var array<int, Column>|null */
    public ?array $columns;

    public string $label;

    public ?string $style;

    /** @var array<int, array{column: Column, value: string}> */
    public array $cells = [];

    /**
     * @param  array<string, mixed>|null  $aggregates
     * @param  array<int, Column>|null  $columns
     */
    public function __construct(?array $aggregates = null, ?array $columns = null, string $label = 'Total', ?string $style = null)
    {
        $this->aggregates = $aggregates;
        $this->columns = $columns;
        $this->label = $label;
        $this->style = $style;
    }

    public function render(): View
    {
        $aggregates = $this->aggregates ?? $this->context()->get('aggregates', []);

        if (! is_array($aggregates) || count($aggregates) === 0) {
            return $this->nothing();
        }

        $export = $this->export();
        $columns = collect($this->columns ?? $this->context()->getColumns())
            ->filter(fn (Column $column) => $this->context()->isColumnVisible($column, $export))
            ->values();

        $labelPlaced = false;

        $this->cells = $columns->map(function (Column $column) use ($aggregates, &$labelPlaced) {
            if (array_key_exists($column->getName(), $aggregates)) {
                $value = $column->formatAggregate($aggregates[$column->getName()]);
            } elseif (! $labelPlaced) {
                $value = $this->label;
                $labelPlaced = true;
            } else {
                $value = '';
            }

            return ['column' => $column, 'value' => $value];
        })->all();

        $this->style = $this->mergeStyles($this->defaultStyle('aggregate'), $this->style);

        return view('i-reports::components.aggregates');
    }
}
