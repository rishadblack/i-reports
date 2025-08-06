<?php
namespace Rishadblack\IReports\View\Components;

use Rishadblack\IReports\Helpers\ReportHelper;
use Rishadblack\IReports\View\Components\BaseComponent;

class Table extends BaseComponent
{
    public $type;
    public $style;

    public function __construct(?string $type = 'table', ?string $style = null)
    {
        $this->type = $type;
        $this->style = $style;
    }

    public function render()
    {
        if ($this->type == 'header') {
            $export = ReportHelper::getExport();

            if (in_array($export, ['view'])) {
                return '';
            }

        }
        return view('i-reports::components.table');
    }
}
