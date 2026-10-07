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
            @foreach ($datas as $row)
                <x-i-reports::tr>
                    @foreach ($columns as $column)
                        <x-i-reports::td :column="$column" :row="$row" />
                    @endforeach
                </x-i-reports::tr>
            @endforeach
        </x-i-reports::tbody>
    </x-i-reports::table>
</x-i-reports::layout>
