<tbody {{ $attributes }}>
    @foreach ($groups as $group)
        @if ($group['key'] !== null)
            <tr>
                <td colspan="{{ $columnCount }}" style="{{ $groupStyle }}">{!! $group['label'] !!}</td>
            </tr>
        @endif

        <x-i-reports::rows :rows="$group['rows']" :columns="$visibleColumns" />

        @if ($showSubtotals && count($group['subtotals']) > 0)
            <tr>
                @foreach ($visibleColumns as $index => $column)
                    <td style="{{ $subtotalStyle }} {{ $column->getAlign() ? 'text-align: '.$column->getAlign().';' : '' }}">
                        {{ array_key_exists($column->getName(), $group['subtotals']) ? $subtotalFor($column, $group['subtotals']) : ($index === 0 ? $subtotalLabel : '') }}
                    </td>
                @endforeach
            </tr>
        @endif
    @endforeach
</tbody>
