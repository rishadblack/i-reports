{{--
    Default report grid, used whenever a report has no Blade view of its own.
    Copy it into the app with `php artisan i-reports:view {report}` to customize.
--}}
<x-i-reports::layout>
    <x-i-reports::table>
        <x-i-reports::thead>
            <x-i-reports::tr>
                @foreach ($columns as $column)
                    <x-i-reports::th :column="$column" />
                @endforeach
            </x-i-reports::tr>
        </x-i-reports::thead>
        @if ($group_by)
            <x-i-reports::grouped-tbody :rows="$datas" />
        @else
            <x-i-reports::tbody>
                <x-i-reports::rows :rows="$datas" />
            </x-i-reports::tbody>
        @endif
        <x-i-reports::aggregates />
    </x-i-reports::table>
</x-i-reports::layout>
