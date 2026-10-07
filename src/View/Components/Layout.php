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
            $branding = $report->branding(in_array($this->context()->getExport(), ['print', 'pdf'], true) ? $report->total() : null);
        } else {
            $this->headerView ??= config('i-reports.header_view');
            $this->pdfHeaderView ??= config('i-reports.pdf_header_view');
            $this->pdfFooterView ??= config('i-reports.pdf_footer_view');
            $branding = $this->fallbackBranding();
        }

        $this->title ??= $this->context()->getReportTitle();

        $printPart = $report instanceof BaseReportController ? $report->printPart() : null;
        $branding['part'] = $printPart;

        if ($printPart !== null) {
            $this->title .= ' ('.__('part :part of :parts', ['part' => $printPart['part'], 'parts' => $printPart['parts']]).')';
        }

        return view('i-reports::components.layout', [
            'headerTitle' => $this->context()->getHeaderTitle(),
            'branding' => $branding,
            'printPart' => $printPart,
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

        $generatedAt = now()->format((string) ($config['date_format'] ?? 'd M Y, h:i A'));

        return [
            'name' => (string) (($config['name'] ?? null) ?: ($this->context()->getHeaderTitle() ?? config('app.name'))),
            'tagline' => $config['tagline'] ?? null,
            'address' => null,
            'contact' => null,
            'details' => [],
            'logo' => null,
            'logo_path' => null,
            'title' => (string) ($this->title ?? $this->context()->getReportTitle()),
            'accent' => (string) ($config['accent_color'] ?? '#1f2937'),
            'filters' => [],
            'generated_at' => $generatedAt,
            'generated_by' => null,
            'records' => null,
            'footer_note' => $config['footer_note'] ?? null,
            'footer_text' => (string) (($config['footer_note'] ?? null) ?: 'Generated '.$generatedAt),
        ];
    }
}
