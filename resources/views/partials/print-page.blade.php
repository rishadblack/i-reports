@php
    $cssString = fn (string $text): string => '"'.addcslashes(str_replace(["\r", "\n", '<', '>'], ' ', $text), '"\\').'"';
    $pageSetup = app(\Rishadblack\IReports\Support\ReportContext::class)->get('page_setup');
@endphp
{{-- Printed page margins, a running header from page 2 and a footer with page numbers (CSS page margin boxes, Chromium based browsers). --}}
@page {
    size: {{ $pageSetup instanceof \Rishadblack\IReports\Support\PageSetup ? $pageSetup->cssPageSize() : 'auto' }};
    margin: 14mm 10mm 14mm 10mm;

    @top-left {
        content: {!! $cssString($branding['name']) !!};
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 7.5pt;
        font-weight: bold;
        color: {{ $branding['accent'] }};
        vertical-align: bottom;
        padding-bottom: 2mm;
    }

    @top-right {
        content: {!! $cssString($branding['title'].'  ·  '.$branding['generated_at']) !!};
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 7.5pt;
        color: #64748b;
        vertical-align: bottom;
        padding-bottom: 2mm;
    }

    @bottom-left {
        content: {!! $cssString($branding['name'].' · '.$branding['title']) !!};
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 7.5pt;
        color: #64748b;
        vertical-align: top;
        padding-top: 2mm;
        border-top: 0.5pt solid #cbd5e1;
    }

    @bottom-center {
        content: {!! $cssString((string) ($branding['footer_text'] ?? $branding['generated_at'])) !!};
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 7.5pt;
        color: #64748b;
        vertical-align: top;
        padding-top: 2mm;
        border-top: 0.5pt solid #cbd5e1;
    }

    @bottom-right {
        content: "Page " counter(page) " of " counter(pages);
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 7.5pt;
        color: #64748b;
        vertical-align: top;
        padding-top: 2mm;
        border-top: 0.5pt solid #cbd5e1;
    }
}

@page :first {
    @top-left { content: none; }
    @top-right { content: none; }
}

@if ($pageSetup instanceof \Rishadblack\IReports\Support\PageSetup && $pageSetup->scale !== 100)
body { zoom: {{ $pageSetup->factor() }}; }
@endif

@media screen {
    body { max-width: 1280px; margin: 0 auto; padding: 24px; }
}

@media print {
    body { padding: 0; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th, td, table { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
