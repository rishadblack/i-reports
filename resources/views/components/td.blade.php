@props(['style' => null, 'odd' => false])

@php
    $bgColor = $odd ? 'background-color: #f9f9f9;' : '';
    $defaultStyle = 'padding: 5px; border: 1px solid #cccccc;' . $bgColor;
    $mergedStyle = $style ?? $defaultStyle;
@endphp

<td {{ $attributes->merge(['style' => $mergedStyle]) }}>
    {{ $slot }}
</td>
