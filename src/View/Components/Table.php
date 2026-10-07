<?php

namespace Rishadblack\IReports\View\Components;

use Illuminate\Contracts\View\View;

class Table extends BaseComponent
{
    public ?string $style;

    public ?string $type;

    /**
     * @param  string|null  $type  "header" marks a title table for print and exports only: it is not shown in the on-screen viewer.
     */
    public function __construct(?string $style = null, ?string $type = null)
    {
        $this->style = $style;
        $this->type = $type;
    }

    public function render(): View
    {
        if ($this->type === 'header' && in_array($this->export(), ['view', 'inline'], true)) {
            return $this->nothing();
        }

        return view('i-reports::components.table');
    }
}
