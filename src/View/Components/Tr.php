<?php

namespace Rishadblack\IReports\View\Components;

use Illuminate\Contracts\View\View;

class Tr extends BaseComponent
{
    public ?string $style;

    public ?string $skip;

    public function __construct(?string $style = null, ?string $skip = null)
    {
        $this->style = $style;
        $this->skip = $skip;
    }

    public function render(): View
    {
        $this->context()->resetRendered('td');
        $this->context()->resetRendered('th');

        if ($this->skip) {
            return $this->nothing();
        }

        $this->style = $this->mergeStyles($this->defaultStyle('tr'), $this->style);

        return view('i-reports::components.tr');
    }
}
