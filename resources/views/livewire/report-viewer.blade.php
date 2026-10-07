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

            {{-- 0.1.x: pages may dispatch "exportIframe" with a report url to open it in a new tab. --}}
            window.addEventListener('exportIframe', (event) => {
                const url = event.detail?.url ?? event.detail?.[0]?.url;
                if (url) {
                    window.open(url, '_blank');
                }
            });
        </script>
    @endscript
</div>
