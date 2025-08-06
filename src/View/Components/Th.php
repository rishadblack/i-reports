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
    public ?string $custom;

    protected static array $renderedColumns = [];

    public function __construct(?string $name = null, ?Column $column = null, ?string $style = null, ?string $custom = null, ?string $skip = null)
    {
        $this->name = $name;
        $this->column = $column;
        $this->style = $style;
        $this->custom = $custom;
        $this->skip = $skip;
    }

    public static function resetRenderedColumns(): void
    {
        self::$renderedColumns = [];
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

        // If custom passed, only render when it matches
        if ($this->custom && $this->custom !== $this->name) {
            return '';
        }

        // Skip if already rendered
        if ($this->name && in_array($this->name, self::$renderedColumns)) {
            return '';
        }

        // If custom passed, only render when it matches
        if ($this->custom && $this->custom !== $this->name) {
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

        // Mark as rendered
        if ($this->name) {
            self::$renderedColumns[] = $this->name;
        }

        if ($this->skip) {
            return '';
        }

        return view('i-reports::components.th');
    }
}
