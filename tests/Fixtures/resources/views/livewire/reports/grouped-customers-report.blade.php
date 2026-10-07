<x-i-reports::layout>
    <x-i-reports::table>
        <x-i-reports::thead>
            <x-i-reports::tr>
                @foreach ($columns as $column)
                    <x-i-reports::th :column="$column" />
                @endforeach
            </x-i-reports::tr>
        </x-i-reports::thead>
        <x-i-reports::grouped-tbody :rows="$datas" />
        <x-i-reports::aggregates label="Grand total" />
    </x-i-reports::table>
    <x-i-reports::chunk />
    <x-i-reports::page-break />
    <p>Summary page</p>
</x-i-reports::layout>
