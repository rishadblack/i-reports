<x-i-reports::layout>
    <x-i-reports::table>
        <x-i-reports::thead>
            <x-i-reports::tr>
                @foreach ($columns as $column)
                    <x-i-reports::th :column="$column" />
                @endforeach
            </x-i-reports::tr>
        </x-i-reports::thead>
        <x-i-reports::tbody>
            @php($running = 0)
            @foreach ($datas as $row)
                @php($running += $row->amount)
                <x-i-reports::tr>
                    @foreach ($columns as $column)
                        <x-i-reports::td :column="$column" custom="balance">RB {{ number_format($running, 2) }}</x-i-reports::td>
                        <x-i-reports::td :column="$column" :row="$row" />
                    @endforeach
                </x-i-reports::tr>
            @endforeach
        </x-i-reports::tbody>
        <x-i-reports::tfoot>
            <x-i-reports::tr>
                <x-i-reports::th colspan="3">Closing balance {{ number_format($running, 2) }}</x-i-reports::th>
            </x-i-reports::tr>
        </x-i-reports::tfoot>
    </x-i-reports::table>
</x-i-reports::layout>
