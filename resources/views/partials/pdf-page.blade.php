{{-- mPDF page header (optional) and footer, defined then switched on for every page. --}}
@if ($pdfHeaderView)
    <htmlpageheader name="i-reports-header">
        @include($pdfHeaderView)
    </htmlpageheader>
    <sethtmlpageheader name="i-reports-header" value="on" show-this-page="1" />
@endif

<htmlpagefooter name="i-reports-footer">
    @if ($pdfFooterView)
        @include($pdfFooterView)
    @else
        <table width="100%" style="border-collapse: collapse; border-top: 1px solid #cbd5e1; font-size: 7.5pt; color: #64748b;">
            <tr>
                <td width="40%" style="padding-top: 4px;">{{ $branding['name'] }} &middot; {{ $branding['title'] }}</td>
                <td width="30%" style="padding-top: 4px; text-align: center;">{{ $branding['generated_at'] }}</td>
                <td width="30%" style="padding-top: 4px; text-align: right;">Page {PAGENO} of {nbpg}</td>
            </tr>
        </table>
    @endif
</htmlpagefooter>
<sethtmlpagefooter name="i-reports-footer" value="on" />
