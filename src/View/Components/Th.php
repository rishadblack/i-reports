<?php
namespace Rishadblack\IReports\View\Components;

use Rishadblack\IReports\Helpers\ReportHelper;
use Rishadblack\IReports\Traits\StyleMergerTrait;
use Rishadblack\IReports\Views\Column;
use Rishadblack\IReports\View\Components\BaseComponent;

class Th extends BaseComponent
{
    use StyleMergerTrait;

    public ?string $name;
    public ?Column $column;
    public ?string $style;
    public ?string $skip;

    public function __construct(?string $name = null, ?Column $column = null, ?string $style = null, ?string $skip = null)
    {
        $this->name = $name;
        $this->column = $column;
        $this->style = $style;
        $this->skip = $skip;
    }

    public function render()
    {
        if ($this->name && ! $this->column) {
            $this->column = ReportHelper::getColumnByName($this->name);
        }
        $this->name = $this->name ?? $this->column?->getName();

        if ($this->column?->isHidden()) {
            return '';
        }

        $columnStyle = null;
        if ($this->column) {
            $columnStyle = $this->column->applyStyle($this->row ?? null);
        }

        // Merge styles: default < column < passed style
        $mergedStyle = $this->mergeStyles(config('i-reports.default_style.th'), $columnStyle);
        $mergedStyle = $this->mergeStyles($mergedStyle, $this->style);
        $this->style = $mergedStyle;

        if ($this->skip) {
            return '';
        }

        return view('i-reports::components.th');
    }
}
