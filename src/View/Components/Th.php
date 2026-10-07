<?php

namespace Rishadblack\IReports\View\Components;

use Illuminate\Contracts\View\View;
use Rishadblack\IReports\Views\Column;

class Th extends BaseComponent
{
    public ?Column $column;

    public ?string $name;

    public ?string $style;

    public ?string $custom;

    public ?string $skip;

    public bool $sortable = true;

    public ?string $sortDirection = null;

    public string $mode = 'none';

    public function __construct(?string $name = null, ?Column $column = null, ?string $style = null, ?string $custom = null, ?string $skip = null, bool $sortable = true)
    {
        $this->name = $name;
        $this->column = $column;
        $this->style = $style;
        $this->custom = $custom;
        $this->skip = $skip;
        $this->sortable = $sortable;
    }

    public function render(): View
    {
        if ($this->name && ! $this->column) {
            $this->column = $this->context()->getColumnByName($this->name);
        }

        $this->name = $this->name ?? $this->column?->getName();

        if ($this->column !== null && ! $this->context()->isColumnVisible($this->column)) {
            return $this->nothing();
        }

        if ($this->custom && $this->custom !== $this->name) {
            return $this->nothing();
        }

        if ($this->name && $this->context()->isRendered('th', $this->name)) {
            return $this->nothing();
        }

        if ($this->name) {
            $this->context()->markRendered('th', $this->name);
        }

        if ($this->skip) {
            return $this->nothing();
        }

        $columnStyle = $this->column?->applyStyle();
        $this->style = $this->mergeStyles($this->mergeStyles($this->defaultStyle('th'), $columnStyle), $this->style);

        $export = $this->export();

        if ($this->sortable && $this->column?->isSortable() && in_array($export, ['view', 'inline'], true)) {
            $this->mode = $export === 'inline' ? 'wire' : 'message';

            if ($this->context()->getSortField() === $this->column->getName()) {
                $this->sortDirection = $this->context()->getSortDirection();
            }
        }

        return view('i-reports::components.th');
    }
}
