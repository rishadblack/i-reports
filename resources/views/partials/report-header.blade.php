{{-- Branded report header for print and PDF. Table based so mPDF and browsers render it the same. --}}
<table width="100%" style="border-collapse: collapse; margin: 0 0 6px 0;">
    <tr>
        @if ($branding['logo'])
            <td style="width: 64px; padding: 0 10px 4px 0; vertical-align: middle;">
                <img src="{{ $branding['logo'] }}" alt="" style="max-height: 46px; max-width: 64px;" />
            </td>
        @endif
        <td style="padding: 0 0 4px 0; vertical-align: middle;">
            <div style="font-size: 13pt; font-weight: bold; color: {{ $branding['accent'] }};">{{ $branding['name'] }}</div>
            @if ($branding['tagline'])
                <div style="font-size: 8pt; color: #64748b; padding-top: 2px;">{{ $branding['tagline'] }}</div>
            @endif
        </td>
        <td style="padding: 0 0 4px 0; vertical-align: middle; text-align: right;">
            <div style="font-size: 14pt; font-weight: bold; color: #0f172a;">{{ $branding['title'] }}</div>
            <div style="font-size: 8pt; color: #64748b; padding-top: 2px;">
                Generated {{ $branding['generated_at'] }}@if ($branding['generated_by']) &middot; by {{ $branding['generated_by'] }}@endif
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="{{ $branding['logo'] ? 3 : 2 }}" style="border-bottom: 2px solid {{ $branding['accent'] }}; padding: 0; font-size: 2pt;">&nbsp;</td>
    </tr>
</table>

@if (count($branding['filters']) > 0)
    <table width="100%" style="border-collapse: collapse; margin: 0 0 10px 0;">
        <tr>
            <td style="background-color: #f1f5f9; border: 1px solid #e2e8f0; padding: 5px 8px; font-size: 8pt; color: #334155;">
                <b>Applied filters:</b>
                @foreach ($branding['filters'] as $applied)
                    {{ $applied['label'] }}: <b>{{ $applied['value'] }}</b>@if (! $loop->last) &nbsp;&middot;&nbsp; @endif
                @endforeach
            </td>
        </tr>
    </table>
@else
    <div style="height: 6px; font-size: 2pt;">&nbsp;</div>
@endif
