<?php

namespace Rishadblack\IReports\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Rishadblack\IReports\Support\ReportContext;
use Rishadblack\IReports\Traits\StyleMergerTrait;

abstract class BaseComponent extends Component
{
    use StyleMergerTrait;

    protected function context(): ReportContext
    {
        return app(ReportContext::class);
    }

    protected function export(): string
    {
        return $this->context()->getExport();
    }

    protected function defaultStyle(string $key): string
    {
        return $this->context()->defaultStyle($key);
    }

    /**
     * Render nothing. A real (compiled once, cached) view: returning an empty string would make
     * Laravel write a temporary Blade file per call, which is slow and races on Windows.
     */
    protected function nothing(): View
    {
        return view('i-reports::components.empty');
    }
}
