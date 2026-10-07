<?php

namespace Rishadblack\IReports\View\Components;

use Illuminate\Support\HtmlString;

/**
 * A page break for PDF and print output. Nothing is rendered in other outputs.
 */
class PageBreak extends BaseComponent
{
    public function render(): HtmlString
    {
        return new HtmlString(match ($this->export()) {
            'pdf' => '<pagebreak />',
            'print' => '<div style="page-break-after: always;"></div>',
            default => '',
        });
    }
}
