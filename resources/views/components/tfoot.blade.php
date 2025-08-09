@props(['style' => null])

@php
    $defaultStyle = ''; // typically no default style needed
    $mergedStyle = $style ?? $defaultStyle;
@endphp

<tfoot {{ $attributes->merge(['style' => $mergedStyle]) }}>
    {{ $slot }}
</tfoot>
