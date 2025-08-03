@props(['style' => null])

@php
    $defaultStyle =
        'border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; border: 1px solid #cccccc;';
    $mergedStyle = $style ?? $defaultStyle;
@endphp

<table {{ $attributes->merge(['style' => $mergedStyle]) }}>
    {{ $slot }}
</table>
