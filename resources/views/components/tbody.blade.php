@props(['style' => null])

@php
    $defaultStyle = ''; // typically no default style needed
    $mergedStyle = $style ?? $defaultStyle;
@endphp

<tbody {{ $attributes->merge(['style' => $mergedStyle]) }}>
    {{ $slot }}
</tbody>
