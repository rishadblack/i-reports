<?php

namespace Rishadblack\IReports\Traits;

use Illuminate\Http\Response;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Rishadblack\IReports\Exports\ChunkedReportWriter;

trait WithMpdfPdf
{
    protected ?int $stream_threshold = null;

    /**
     * Above this many rows, print and PDF are streamed in chunks from the database
     * instead of rendering the report view (0 = always stream, null = use config).
     * Streaming keeps memory flat for 100k+ rows; the view's custom layout is not used.
     */
    public function setStreamThreshold(?int $rows): static
    {
        $this->stream_threshold = $rows;

        return $this;
    }

    public function getStreamThreshold(): int
    {
        return max(0, $this->stream_threshold ?? (int) config('i-reports.stream_threshold', 5000));
    }

    /**
     * Whether the current print or PDF export should be streamed in chunks.
     */
    public function shouldStream(): bool
    {
        $threshold = $this->getStreamThreshold();

        return $threshold === 0 || $this->total() > $threshold;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function pdfExportByMpdf(string $view, array $data = []): Response
    {
        return $this->pdfResponse($this->renderPdf($view, $data));
    }

    /**
     * The PDF download for the current context, streamed in chunks for large reports.
     */
    public function pdfDownload(): Response
    {
        return $this->pdfResponse($this->pdfContent());
    }

    /**
     * The PDF bytes for the current context.
     */
    public function pdfContent(): string
    {
        $this->publishToContext();

        if ($this->shouldStream()) {
            return app(ChunkedReportWriter::class)->pdf($this);
        }

        return $this->renderPdf($this->getViewName(), $this->viewData(true));
    }

    /**
     * A configured mPDF instance for this report.
     */
    public function makeMpdf(): Mpdf
    {
        $tempDir = storage_path('app/i-reports/mpdf');

        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf(array_merge([
            'mode' => 'utf-8',
            'format' => $this->getPaperSize(),
            'orientation' => $this->getOrientation() === 'landscape' ? 'L' : 'P',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ], (array) config('i-reports.mpdf', [])));

        $mpdf->SetTitle($this->getFileTitle());

        return $mpdf;
    }

    /**
     * Render the report view to PDF. The HTML is cut at every chunk separator
     * (<x-i-reports::chunk />) and each piece is written on its own, like laravel-mpdf's
     * chunkLoadView, so no single WriteHTML call exceeds pcre.backtrack_limit.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderPdf(string $view, array $data = []): string
    {
        $mpdf = $this->makeMpdf();
        $html = $this->renderReport($view, $data)->render();
        $separator = (string) config('i-reports.pdf_chunk_separator', '<html-separator/>');
        $chunks = $separator !== '' && str_contains($html, $separator) ? explode($separator, $html) : [$html];
        unset($html);

        foreach ($chunks as $index => $chunk) {
            $chunk = $this->absolutizeLinks($chunk);
            $this->ensureBacktrackLimit(strlen($chunk));
            $mpdf->WriteHTML($chunk, HTMLParserMode::DEFAULT_MODE);
            unset($chunks[$index]);
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * Make relative link targets absolute. mPDF slows down quadratically with the number of
     * relative hrefs (5k rows with one link each: minutes instead of seconds); absolute URLs,
     * schemes (mailto:, tel:), protocol-relative and #anchor links are left alone.
     */
    public function absolutizeLinks(string $html): string
    {
        if (! str_contains($html, 'href=')) {
            return $html;
        }

        $base = rtrim(url('/'), '/');

        return (string) preg_replace_callback(
            '/\bhref=(["\'])(?![a-z][a-z0-9+.\-]*:|\/\/|#)([^"\']*)\1/i',
            fn (array $match): string => 'href='.$match[1].$base.'/'.ltrim($match[2], '/').$match[1],
            $html,
        );
    }

    /**
     * mPDF refuses HTML longer than pcre.backtrack_limit; raise it for a piece that needs more.
     */
    protected function ensureBacktrackLimit(int $length): void
    {
        if ($length >= (int) ini_get('pcre.backtrack_limit')) {
            @ini_set('pcre.backtrack_limit', (string) ($length + 1024));
        }
    }

    protected function pdfResponse(string $content): Response
    {
        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->getFileName().'.pdf"',
        ]);
    }
}
