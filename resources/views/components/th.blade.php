@php
    $label = $slot->isEmpty() && $column ? $column->getTitle() : $slot;
    $arrow = $sortDirection === 'asc' ? ' &#9650;' : ($sortDirection === 'desc' ? ' &#9660;' : '');
@endphp
<th style="{{ $style }}" {{ $attributes }}>
    @if ($mode === 'wire')
        <a class="i-reports-sort" href="#" wire:click.prevent="sortBy('{{ $column->getName() }}')">{{ $label }}{!! $arrow !!}</a>
    @elseif ($mode === 'message')
        <a class="i-reports-sort" href="#" onclick="window.parent.postMessage({type: 'i-reports:sort', field: @js($column->getName())}, window.location.origin); return false;">{{ $label }}{!! $arrow !!}</a>
    @else
        {{ $label }}
    @endif
</th>
