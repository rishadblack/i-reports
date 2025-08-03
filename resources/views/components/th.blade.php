@props(['style' => null])

@php
    $defaultStyle =
        'text-align: left; font-size: 14px; color: #ffffff; position: sticky; top: 0; background-color: #727070; padding: 6px;';
    $mergedStyle = $style ?? $defaultStyle;
@endphp

<th {{ $attributes->merge(['style' => $mergedStyle]) }}>
    {{ $slot }}
</th>
