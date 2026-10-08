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
            <x-i-reports::rows :rows="$datas" />
        </x-i-reports::tbody>
    </x-i-reports::table>
</x-i-reports::layout>
