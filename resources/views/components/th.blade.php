@props(['style' => null, 'column' => null])

<th style="{{ $style ?? '' }}" {{ $attributes }}>
    {{ $slot->isEmpty() && $column ? $column->getTitle() : $slot }}
</th>
