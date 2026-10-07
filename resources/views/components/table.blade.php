<table {{ $attributes->merge(['class' => 'i-reports-table', 'style' => $style ?? 'border-collapse: collapse; width: 100%;']) }}>
    {{ $slot }}
</table>
