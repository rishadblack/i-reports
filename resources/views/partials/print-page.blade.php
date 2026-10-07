@php
    $cssString = fn (string $text): string => '"'.addcslashes(str_replace(["\r", "\n", '<', '>'], ' ', $text), '"\\').'"';
@endphp
{{-- Printed page margins and a footer with page numbers (CSS page margin boxes, Chromium based browsers). --}}
@page {
    size: auto;
    margin: 12mm 10mm 14mm 10mm;

    @bottom-left {
        content: {!! $cssString($branding['name'].' · '.$branding['title']) !!};
        font-size: 7.5pt;
        color: #64748b;
    }

    @bottom-center {
        content: {!! $cssString($branding['generated_at']) !!};
        font-size: 7.5pt;
        color: #64748b;
    }

    @bottom-right {
        content: "Page " counter(page) " of " counter(pages);
        font-size: 7.5pt;
        color: #64748b;
    }
}

@media print {
    body { padding: 0; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
