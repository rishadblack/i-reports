<div>
    @include('i-reports::viewer.bootstrap')

    @script
        <script>
            {{-- Sort requests posted by the report page inside the iframe. --}}
            window.addEventListener('message', (event) => {
                if (event.origin !== window.location.origin) {
                    return;
                }
                if (event.data && event.data.type === 'i-reports:sort' && typeof event.data.field === 'string') {
                    $wire.sortBy(event.data.field);
                }
            });
        </script>
    @endscript
</div>
