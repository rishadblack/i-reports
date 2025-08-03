@props(['style' => null])

@php
    $defaultStyle = ''; // usually no style on thead itself, style tr/th
    $mergedStyle = $style ?? $defaultStyle;
@endphp

<thead {{ $attributes->merge(['style' => $mergedStyle]) }}>
    {{ $slot }}
</thead>
