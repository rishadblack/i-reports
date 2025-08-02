<?php
namespace Rishadblack\IReports\Traits;

use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;

trait WithMpdfPdf
{
    public function pdfExportByMpdf(string $view, array $data = [])
    {
        // Load config
        $mpdfConfig = config('i-reports.mpdf');
        $headerConfig = config('i-reports.pdf_header');
        $footerConfig = config('i-reports.pdf_footer');

        // Set paper and orientation
        $options = array_merge([
            'title' => $this->getFileTitle(),
            'format' => $this->getPaperSize(),
            'orientation' => $this->getOrientation() === 'landscape' ? 'L' : 'P',
        ], $mpdfConfig);

        // Create PDF instance
        $pdf = LaravelMpdf::loadView($view, $data, [], $options);

        // Header (text or blade)
        // if ($headerConfig['html_view']) {
        //     $pdf->getMpdf()->SetHTMLHeader(view($headerConfig['html_view'], $data)->render());
        // } else {
        //     $pdf->getMpdf()->SetHTMLHeader($this->buildHeaderFooterTable($headerConfig));
        // }

        // // Footer (text or blade)
        // if ($footerConfig['html_view']) {
        //     $pdf->getMpdf()->SetHTMLFooter(view($footerConfig['html_view'], $data)->render());
        // } else {
        //     $pdf->getMpdf()->SetHTMLFooter($this->buildHeaderFooterTable($footerConfig));
        // }

        return $pdf->download($this->getFileName() . '.pdf');
    }

    protected function buildHeaderFooterTable(array $config): string
    {
        $map = [
            'current_page' => '{PAGENO}',
            'total_page' => '{nbpg}',
            'current_page_and_total_page' => '{PAGENO}/{nbpg}',
            'date' => now()->format('d-m-Y'),
            'time' => now()->format('H:i'),
            'date_and_time' => now()->format('d-m-Y H:i'),
        ];

        $left = $map[$config['left']] ?? $config['left'] ?? '';
        $center = $map[$config['center']] ?? $config['center'] ?? '';
        $right = $map[$config['right']] ?? $config['right'] ?? '';

        return <<<HTML
        <table width="100%" style="font-size: 10pt;">
            <tr>
                <td width="33%">{$left}</td>
                <td width="33%" align="center">{$center}</td>
                <td width="33%" align="right">{$right}</td>
            </tr>
        </table>
        HTML;
    }
}