{{-- Shared filter fields. $ui holds the theme's class names. --}}
@foreach ($filter_list as $filter)
    @php
        $wrapperClass = $filter['class'] ?? $ui['filter_wrapper'];
        // Numeric strings become numbers: a child with a typed #[Reactive] ?int prop coerces the value,
        // and Livewire compares hashes of the passed-in and the stored value when the child dehydrates.
        $parentValue = $filter['depends_on'] ? ($filters[$filter['depends_on']] ?? null) : null;
        $parentValue = is_string($parentValue) && is_numeric($parentValue) ? $parentValue + 0 : ($parentValue === '' ? null : $parentValue);
        $fieldKey = 'filter-'.$filter['name'].'-'.$loop->index.($filter['depends_on'] ? '-'.md5(json_encode($parentValue)) : '');
    @endphp

    <div class="{{ $wrapperClass }}" wire:key="{{ $fieldKey }}-wrapper">
        @if ($filter['filter_type'] === 'select')
            <label for="filters_{{ $filter['name'] }}" class="{{ $ui['label'] }}">{{ $filter['title'] }}</label>
            <select id="filters_{{ $filter['name'] }}" wire:model="filters.{{ $filter['name'] }}" class="{{ $ui['select'] }}">
                <option value="">{{ $filter['placeholder'] ?? 'Select '.$filter['title'] }}</option>
                @foreach ($filter['options'] as $optionKey => $optionValue)
                    <option value="{{ $optionKey }}">{{ $optionValue }}</option>
                @endforeach
            </select>
        @elseif ($filter['filter_type'] === 'multi_select')
            <label for="filters_{{ $filter['name'] }}" class="{{ $ui['label'] }}">{{ $filter['title'] }}</label>
            <select id="filters_{{ $filter['name'] }}" wire:model="filters.{{ $filter['name'] }}" class="{{ $ui['select'] }}" multiple>
                @foreach ($filter['options'] as $optionKey => $optionValue)
                    <option value="{{ $optionKey }}">{{ $optionValue }}</option>
                @endforeach
            </select>
        @elseif ($filter['filter_type'] === 'boolean')
            <label for="filters_{{ $filter['name'] }}" class="{{ $ui['label'] }}">{{ $filter['title'] }}</label>
            <select id="filters_{{ $filter['name'] }}" wire:model="filters.{{ $filter['name'] }}" class="{{ $ui['select'] }}">
                <option value="">{{ $filter['placeholder'] ?? 'Any' }}</option>
                @foreach ($filter['options'] as $optionKey => $optionValue)
                    <option value="{{ $optionKey }}">{{ $optionValue }}</option>
                @endforeach
            </select>
        @elseif ($filter['filter_type'] === 'text')
            <label for="filters_{{ $filter['name'] }}" class="{{ $ui['label'] }}">{{ $filter['title'] }}</label>
            <input id="filters_{{ $filter['name'] }}" type="text" wire:model="filters.{{ $filter['name'] }}" class="{{ $ui['input'] }}" placeholder="{{ $filter['placeholder'] }}" />
        @elseif ($filter['filter_type'] === 'number')
            <label for="filters_{{ $filter['name'] }}" class="{{ $ui['label'] }}">{{ $filter['title'] }}</label>
            <input id="filters_{{ $filter['name'] }}" type="number" step="any" wire:model="filters.{{ $filter['name'] }}" class="{{ $ui['input'] }}" placeholder="{{ $filter['placeholder'] }}" />
        @elseif ($filter['filter_type'] === 'date')
            <label for="filters_{{ $filter['name'] }}" class="{{ $ui['label'] }}">{{ $filter['title'] }}</label>
            <input id="filters_{{ $filter['name'] }}" type="date" wire:model="filters.{{ $filter['name'] }}" class="{{ $ui['input'] }}" />
        @elseif ($filter['filter_type'] === 'date_range')
            <label class="{{ $ui['label'] }}">{{ $filter['title'] }}</label>
            <div class="{{ $ui['range'] }}">
                <input id="filters_{{ $filter['name'] }}_from" type="date" wire:model="filters.{{ $filter['name'] }}.from" class="{{ $ui['input'] }}" aria-label="{{ $filter['title'] }} from" />
                <span class="input-group-text">to</span>
                <input id="filters_{{ $filter['name'] }}_to" type="date" wire:model="filters.{{ $filter['name'] }}.to" class="{{ $ui['input'] }}" aria-label="{{ $filter['title'] }} to" />
            </div>
        @elseif ($filter['filter_type'] === 'number_range')
            <label class="{{ $ui['label'] }}">{{ $filter['title'] }}</label>
            <div class="{{ $ui['range'] }}">
                <input id="filters_{{ $filter['name'] }}_from" type="number" step="any" wire:model="filters.{{ $filter['name'] }}.from" class="{{ $ui['input'] }}" placeholder="Min" aria-label="{{ $filter['title'] }} minimum" />
                <span class="input-group-text">to</span>
                <input id="filters_{{ $filter['name'] }}_to" type="number" step="any" wire:model="filters.{{ $filter['name'] }}.to" class="{{ $ui['input'] }}" placeholder="Max" aria-label="{{ $filter['title'] }} maximum" />
            </div>
        @elseif ($filter['filter_type'] === 'component')
            @php
                $componentParams = [
                    'wire:model' => 'filters.'.$filter['name'],
                    'name' => 'filters.'.$filter['name'],
                    'label' => $filter['title'],
                    'placeholder' => $filter['placeholder'],
                ] + $filter['component_parameters'];

                if ($filter['depends_on']) {
                    $componentParams[$filter['depends_on']] = $parentValue;
                }
            @endphp
            @livewire($filter['component'], $componentParams, key($fieldKey))
        @elseif ($filter['filter_type'] === 'blade_component')
            @php
                $componentParams = $filter['component_parameters'] ?? [];
            @endphp
            <x-dynamic-component :component="$filter['component']" wire:model="filters.{{ $filter['name'] }}" :name="'filters.'.$filter['name']" :label="$filter['title']" :placeholder="$filter['placeholder']" :options="$componentParams['options'] ?? null" :params="$componentParams" :parent-value="$parentValue" />
        @endif
    </div>
@endforeach

@if ($filter_extended_view)
    @includeIf($filter_extended_view)
@endif
