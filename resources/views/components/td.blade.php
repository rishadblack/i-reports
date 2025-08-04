@props(['style' => null, 'value' => null])

<td {{ $attributes->merge(['style' => $style]) }}>
    {!! $slot->isEmpty() ? $value : $slot !!}
</td>
