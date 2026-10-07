<x-i-reports::layout>
    <p>Note: {{ $additional_datas['note'] }}</p>
    <p>Rows: {{ $summaries['rows'] }}</p>
    <x-i-reports::table>
        <x-i-reports::thead>
            <x-i-reports::tr>
                <x-i-reports::th name="shout" style="color: red;" />
                @foreach ($columns as $column)
                    <x-i-reports::th :column="$column" />
                @endforeach
            </x-i-reports::tr>
        </x-i-reports::thead>
        <x-i-reports::tbody>
            @foreach ($datas as $row)
                <x-i-reports::tr>
                    <x-i-reports::td name="shout" :row="$row" />
                    @foreach ($columns as $column)
                        <x-i-reports::td :column="$column" :row="$row" />
                    @endforeach
                    <x-i-reports::td value="{{ $row->customer_id }}" />
                </x-i-reports::tr>
            @endforeach
        </x-i-reports::tbody>
    </x-i-reports::table>
</x-i-reports::layout>
