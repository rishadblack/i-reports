{{-- Branded report header for print and PDF (first page). Table based so mPDF and browsers render it the same. --}}
@php
    $showFilters = (bool) config('i-reports.branding.show_filters', true);
    $records = $branding['records'] ?? null;
    $details = (array) ($branding['details'] ?? []);
    $label = 'font-size: 6.5pt; font-weight: bold; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px; padding-bottom: 2px;';
    $value = 'font-size: 8.5pt; font-weight: bold; color: #0f172a;';
    $cell = 'padding: 6px 10px; vertical-align: top; border-left: 1px solid #e2e8f0;';
@endphp
<table width="100%" style="border-collapse: collapse; margin: 0;">
    <tr>
        @if ($branding['logo'])
            <td style="width: 70px; padding: 0 12px 8px 0; vertical-align: middle;">
                <img src="{{ $branding['logo'] }}" alt="" style="max-height: 52px; max-width: 70px;" />
            </td>
        @endif
        <td style="padding: 0 0 8px 0; vertical-align: middle;">
            <div style="font-size: 15pt; font-weight: bold; color: {{ $branding['accent'] }};">{{ $branding['name'] }}</div>
            @if ($branding['tagline'])
                <div style="font-size: 8.5pt; color: #475569; padding-top: 2px;">{{ $branding['tagline'] }}</div>
            @endif
            @foreach ($details as $detail)
                <div style="font-size: 7.5pt; color: #64748b; padding-top: 1px;">{{ $detail }}</div>
            @endforeach
        </td>
        <td style="padding: 0 0 8px 12px; vertical-align: bottom; text-align: right;">
            <div style="font-size: 7pt; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px;">Report</div>
            <div style="font-size: 16pt; font-weight: bold; color: #0f172a; padding-top: 1px;">{{ $branding['title'] }}</div>
        </td>
    </tr>
</table>
<table width="100%" style="border-collapse: collapse; margin: 0;">
    <tr>
        <td style="border-top: 2.5px solid {{ $branding['accent'] }}; padding: 0; font-size: 1pt; line-height: 1px;">&nbsp;</td>
    </tr>
</table>

<table width="100%" style="border-collapse: collapse; margin: 4px 0 12px 0; background-color: #f8fafc; border: 1px solid #e2e8f0;">
    <tr>
        <td style="padding: 6px 10px; vertical-align: top; width: 20%;">
            <div style="{{ $label }}">Generated</div>
            <div style="{{ $value }}">{{ $branding['generated_at'] }}</div>
        </td>
        @if ($branding['generated_by'])
            <td style="{{ $cell }} width: 17%;">
                <div style="{{ $label }}">Prepared by</div>
                <div style="{{ $value }}">{{ $branding['generated_by'] }}</div>
            </td>
        @endif
        @if ($records !== null)
            <td style="{{ $cell }} width: {{ empty($branding['part']) ? '12%' : '18%' }};">
                <div style="{{ $label }}">Records</div>
                @if (! empty($branding['part']))
                    <div style="{{ $value }}">{{ number_format($branding['part']['from']) }}&ndash;{{ number_format($branding['part']['to']) }} <span style="font-weight: normal; color: #64748b;">of {{ number_format($records) }}</span></div>
                @else
                    <div style="{{ $value }}">{{ number_format($records) }}</div>
                @endif
            </td>
        @endif
        @if ($showFilters)
            <td style="{{ $cell }}">
                <div style="{{ $label }}">Applied filters</div>
                <div style="font-size: 8pt; color: #334155;">
                    @forelse ($branding['filters'] as $applied)
                        {{ $applied['label'] }}: <b style="color: #0f172a;">{{ $applied['value'] }}</b>@if (! $loop->last)<span style="color: #cbd5e1;">&nbsp;&nbsp;|&nbsp;&nbsp;</span>@endif
                    @empty
                        <span style="color: #64748b;">None &mdash; showing all records</span>
                    @endforelse
                </div>
            </td>
        @else
            <td style="padding: 0;">&nbsp;</td>
        @endif
    </tr>
</table>
