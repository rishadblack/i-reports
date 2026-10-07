<?php

namespace Rishadblack\IReports\View\Components;

use Illuminate\Contracts\View\View;
use Rishadblack\IReports\Views\Column;

class Td extends BaseComponent
{
    public ?string $name;

    public ?Column $column;

    public mixed $row;

    public ?string $style;

    public mixed $value = null;

    public bool $html = false;

    public ?string $custom;

    public ?string $skip;

    /** The escaped (or trusted) HTML to print. */
    public string $output = '';

    public function __construct(?string $name = null, ?Column $column = null, mixed $row = null, ?string $style = null, mixed $value = null, bool $html = false, ?string $custom = null, ?string $skip = null)
    {
        $this->name = $name;
        $this->column = $column;
        $this->row = $row;
        $this->style = $style;
        $this->value = $value;
        $this->html = $html;
        $this->custom = $custom;
        $this->skip = $skip;
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

        if ($this->name && $this->context()->isRendered('td', $this->name)) {
            return $this->nothing();
        }

        if ($this->name) {
            $this->context()->markRendered('td', $this->name);
        }

        if ($this->skip) {
            return $this->nothing();
        }

        if ($this->value !== null) {
            $this->output = $this->html ? (string) $this->value : e((string) $this->value);
        } elseif ($this->column && $this->row !== null) {
            $this->output = $this->column->render($this->row, $this->export());
        }

        $columnStyle = $this->column?->applyStyle($this->row);
        $this->style = $this->mergeStyles($this->mergeStyles($this->defaultStyle('td'), $columnStyle), $this->style);

        return view('i-reports::components.td');
    }
}
