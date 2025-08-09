<?php
namespace Rishadblack\IReports\View\Components;

use Illuminate\Database\Eloquent\Model;
use Rishadblack\IReports\Helpers\ReportHelper;
use Rishadblack\IReports\Traits\StyleMergerTrait;
use Rishadblack\IReports\Views\Column;
use Rishadblack\IReports\View\Components\BaseComponent;

class Td extends BaseComponent
{
    use StyleMergerTrait;

    public ?string $name;
    public ?Column $column;
    public ?Model $row;
    public ?string $style;
    public mixed $value = null;
    public ?string $custom;
    public ?string $skip;

    protected static array $renderedColumns = [];

    public function __construct(?string $name = null, ?Column $column = null, ?Model $row = null, ?string $style = null, ?string $custom = null, ?string $skip = null)
    {
        $this->name = $name;
        $this->column = $column;
        $this->row = $row;
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
        // Resolve column
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

        if ($this->column && $this->row) {
            $this->value = $this->column->applyFormat($this->column->getValue($this->row), $this->row, $this->column);
        }

        // Style merging
        $columnStyle = $this->column?->applyStyle($this->row) ?? null;
        $mergedStyle = $this->mergeStyles(config('i-reports.default_style.td'), $columnStyle);
        $mergedStyle = $this->mergeStyles($mergedStyle, $this->style);
        $this->style = $mergedStyle;

        // Mark as rendered
        if ($this->name) {
            self::$renderedColumns[] = $this->name;
        }

        if ($this->skip) {
            return '';
        }

        return view('i-reports::components.td');
    }
}
