<?php

namespace Rishadblack\IReports\View\Components;

use Illuminate\Contracts\View\View;

class Table extends BaseComponent
{
    public ?string $style;

    public function __construct(?string $style = null)
    {
        $this->style = $style;
    }

    public function render(): View
    {
        return view('i-reports::components.table');
    }
}
