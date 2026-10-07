@props(['name' => null, 'label' => null, 'placeholder' => null, 'options' => null, 'datalist' => null, 'params' => [], 'parentValue' => null])
<input type="text" data-pick="{{ $name }}" {{ $attributes->whereStartsWith('wire:model') }} />
