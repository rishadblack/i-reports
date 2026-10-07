{{-- mPDF page header and footer. A custom header view shows on every page; otherwise a slim running header starts on page 2 (page 1 has the full report header). --}}
@if ($pdfHeaderView)
    <htmlpageheader name="i-reports-header">
        @include($pdfHeaderView)
    </htmlpageheader>
    <sethtmlpageheader name="i-reports-header" value="on" show-this-page="1" />
@else
    <htmlpageheader name="i-reports-header">
        <table width="100%" style="border-collapse: collapse; border-bottom: 1px solid #cbd5e1; font-size: 7.5pt; color: #64748b;">
            <tr>
                <td width="50%" style="padding-bottom: 3px;"><b style="color: {{ $branding['accent'] }};">{{ $branding['name'] }}</b></td>
                <td width="50%" style="padding-bottom: 3px; text-align: right;"><b style="color: #0f172a;">{{ $branding['title'] }}</b>&nbsp;&nbsp;&middot;&nbsp;&nbsp;{{ $branding['generated_at'] }}</td>
            </tr>
        </table>
    </htmlpageheader>
    <sethtmlpageheader name="i-reports-header" value="on" show-this-page="0" />
@endif

<htmlpagefooter name="i-reports-footer">
    @if ($pdfFooterView)
        @include($pdfFooterView)
    @else
        <table width="100%" style="border-collapse: collapse; border-top: 1px solid #cbd5e1; font-size: 7.5pt; color: #64748b;">
            <tr>
                <td width="36%" style="padding-top: 4px;"><b style="color: #334155;">{{ $branding['name'] }}</b>&nbsp;&middot;&nbsp;{{ $branding['title'] }}</td>
                <td width="38%" style="padding-top: 4px; text-align: center;{{ ($branding['footer_note'] ?? null) ? ' font-weight: bold; color: #334155;' : '' }}">{{ $branding['footer_text'] ?? $branding['generated_at'] }}</td>
                <td width="26%" style="padding-top: 4px; text-align: right;">Page <b style="color: #334155;">{PAGENO}</b> of {nbpg}</td>
            </tr>
        </table>
    @endif
</htmlpagefooter>
<sethtmlpagefooter name="i-reports-footer" value="on" />
