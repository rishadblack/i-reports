<tfoot {{ $attributes }}>
    <tr>
        @foreach ($cells as $cell)
            <td style="{{ $style }} {{ $cell['column']->getAlign() ? 'text-align: '.$cell['column']->getAlign().';' : '' }}">{{ $cell['value'] }}</td>
        @endforeach
    </tr>
</tfoot>
