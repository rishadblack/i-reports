<?php

namespace Rishadblack\IReports\Traits;

use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

trait WithMpdfPdf
{
    public function pdfExportByMpdf(string $view, array $data = []): Response
    {
        // Load config
        $mpdfConfig = config('i-reports.mpdf', []);
        $headerConfig = config('i-reports.pdf_header');
        $footerConfig = config('i-reports.pdf_footer');

        // Set paper and orientation
        $options = array_merge([
            'mode' => 'utf-8',
            'format' => $this->getPaperSize(),
            'orientation' => $this->getOrientation() === 'landscape' ? 'L' : 'P',
            'tempDir' => storage_path('app/i-reports/mpdf'),
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ], $mpdfConfig);

        // Create PDF instance
        $mpdf = new Mpdf($options);
        $mpdf->SetTitle($this->getFileTitle());

        // Header (text or blade)
        // if ($headerConfig['html_view']) {
        //     $mpdf->SetHTMLHeader(view($headerConfig['html_view'], $data)->render());
        // } else {
        //     $mpdf->SetHTMLHeader($this->buildHeaderFooterTable($headerConfig));
        // }

        // // Footer (text or blade)
        // if ($footerConfig['html_view']) {
        //     $mpdf->SetHTMLFooter(view($footerConfig['html_view'], $data)->render());
        // } else {
        //     $mpdf->SetHTMLFooter($this->buildHeaderFooterTable($footerConfig));
        // }

        $mpdf->WriteHTML(view($view, $data)->render());

        return new Response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->getFileName().'.pdf"',
        ]);
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
