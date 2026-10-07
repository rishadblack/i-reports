{{-- Screen-only toolbar for a print split into parts: which rows are shown, Previous / Next, jump to a part, print. Never printed. --}}
@php
    $partUrl = fn (int $part): string => request()->fullUrlWithQuery(['part' => $part]);
    $current = $printPart['part'];
    $parts = $printPart['parts'];
@endphp
<style>
    .i-reports-parts { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; margin: -24px -24px 16px; padding: 10px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #334155; }
    .i-reports-parts__info { margin-right: auto; }
    .i-reports-parts__info b { color: #0f172a; }
    .i-reports-parts__nav { display: flex; align-items: center; gap: 6px; }
    .i-reports-parts a, .i-reports-parts button, .i-reports-parts span.is-disabled { display: inline-block; padding: 5px 12px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #1e293b; text-decoration: none; font: inherit; line-height: 1.3; cursor: pointer; }
    .i-reports-parts a:hover { background: #f1f5f9; }
    .i-reports-parts span.is-disabled { color: #94a3b8; background: #f8fafc; cursor: default; }
    .i-reports-parts select { padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; background: #fff; }
    .i-reports-parts .is-primary { background: {{ $branding['accent'] }}; border-color: {{ $branding['accent'] }}; color: #fff; }
    @media print { .i-reports-parts { display: none !important; } }
</style>
<nav class="i-reports-parts" aria-label="Report parts">
    <div class="i-reports-parts__info">
        Showing rows <b>{{ number_format($printPart['from']) }}&ndash;{{ number_format($printPart['to']) }}</b> of <b>{{ number_format($printPart['total']) }}</b>
        &middot; part {{ $current }} of {{ $parts }}
    </div>
    <div class="i-reports-parts__nav">
        @if ($current > 1)
            <a href="{{ $partUrl(1) }}" aria-label="First part">&laquo;</a>
            <a href="{{ $partUrl($current - 1) }}" rel="prev" data-part-prev>&lsaquo; Previous {{ number_format($printPart['size']) }}</a>
        @else
            <span class="is-disabled" aria-hidden="true">&laquo;</span>
            <span class="is-disabled">&lsaquo; Previous {{ number_format($printPart['size']) }}</span>
        @endif

        <select aria-label="Go to part" onchange="window.location.href = this.value">
            @for ($part = 1; $part <= $parts; $part++)
                <option value="{{ $partUrl($part) }}" @selected($part === $current)>
                    Part {{ $part }}: {{ number_format(($part - 1) * $printPart['size'] + 1) }}&ndash;{{ number_format(min($printPart['total'], $part * $printPart['size'])) }}
                </option>
            @endfor
        </select>

        @if ($current < $parts)
            <a href="{{ $partUrl($current + 1) }}" rel="next" data-part-next>Next {{ number_format($printPart['size']) }} &rsaquo;</a>
            <a href="{{ $partUrl($parts) }}" aria-label="Last part">&raquo;</a>
        @else
            <span class="is-disabled">Next {{ number_format($printPart['size']) }} &rsaquo;</span>
            <span class="is-disabled" aria-hidden="true">&raquo;</span>
        @endif
    </div>
    <button type="button" class="is-primary" onclick="window.print()">Print this part</button>
</nav>
