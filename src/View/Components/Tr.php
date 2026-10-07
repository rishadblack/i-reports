<?php

namespace Rishadblack\IReports\View\Components;

use Illuminate\Database\Eloquent\Collection;
use Rishadblack\IReports\Traits\StyleMergerTrait;

class Tr extends BaseComponent
{
    use StyleMergerTrait;

    public $rows;

    public $style;

    public ?string $skip;

    public function __construct(?Collection $rows = null, ?string $style = null, ?string $skip = null)
    {
        $this->rows = $rows;
        $this->style = $style;
        $this->skip = $skip;
    }

    public function render()
    {
        Td::resetRenderedColumns();

        $mergedStyle = $this->mergeStyles(config('i-reports.default_style.tr'), $this->style);
        $this->style = $mergedStyle;

        Td::resetRenderedColumns();

        if ($this->skip) {
            return '';
        }

        return view('i-reports::components.tr');
    }
}
