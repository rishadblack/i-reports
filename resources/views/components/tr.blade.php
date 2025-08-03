@props(['style' => null])

@php
    $defaultStyle = ''; // optionally you can add hover or background styles here
    $mergedStyle = $style ?? $defaultStyle;
@endphp

<tr {{ $attributes->merge(['style' => $mergedStyle]) }}>
    {{ $slot }}
</tr>
