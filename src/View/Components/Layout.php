<?php

namespace Rishadblack\IReports\View\Components;

use Illuminate\Contracts\View\View;
use Rishadblack\IReports\BaseReportController;

class Layout extends BaseComponent
{
    public ?string $title;

    public ?string $headerView;

    public ?string $pdfHeaderView;

    public ?string $pdfFooterView;

    public function __construct(?string $title = null, ?string $headerView = null, ?string $pdfHeaderView = null, ?string $pdfFooterView = null)
    {
        $this->title = $title;
        $this->headerView = $headerView;
        $this->pdfHeaderView = $pdfHeaderView;
        $this->pdfFooterView = $pdfFooterView;
    }

    public function render(): View
    {
        $report = $this->context()->get('report');

        if ($report instanceof BaseReportController) {
            $this->headerView ??= $report->getHeaderView();
            $this->pdfHeaderView ??= $report->getPdfHeaderView();
            $this->pdfFooterView ??= $report->getPdfFooterView();
            $branding = $report->branding();
        } else {
            $this->headerView ??= config('i-reports.header_view');
            $this->pdfHeaderView ??= config('i-reports.pdf_header_view');
            $this->pdfFooterView ??= config('i-reports.pdf_footer_view');
            $branding = $this->fallbackBranding();
        }

        $this->title ??= $this->context()->getReportTitle();

        return view('i-reports::components.layout', [
            'headerTitle' => $this->context()->getHeaderTitle(),
            'branding' => $branding,
        ]);
    }

    /**
     * Branding when the layout is rendered outside a report (e.g. a standalone view).
     *
     * @return array<string, mixed>
     */
    protected function fallbackBranding(): array
    {
        $config = (array) config('i-reports.branding', []);

        return [
            'name' => (string) (($config['name'] ?? null) ?: ($this->context()->getHeaderTitle() ?? config('app.name'))),
            'tagline' => $config['tagline'] ?? null,
            'logo' => null,
            'logo_path' => null,
            'title' => (string) ($this->title ?? $this->context()->getReportTitle()),
            'accent' => (string) ($config['accent_color'] ?? '#1f2937'),
            'filters' => [],
            'generated_at' => now()->format((string) ($config['date_format'] ?? 'd M Y, h:i A')),
            'generated_by' => null,
        ];
    }
}
