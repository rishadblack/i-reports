@if (in_array($export, ['view', 'print', 'pdf']))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        @include('i-reports::partials.styles')

        @if ($export === 'print')
            @include('i-reports::partials.print-page')
        @endif

        @if ($export === 'view')
        @include('i-reports::partials.screen-theme')

        html,
        body {
            background-color: var(--ir-page-bg);
            color: var(--ir-text);
        }

        [data-bs-theme="dark"] {
            color-scheme: dark;
        }

        thead th {
            position: sticky;
            top: 0;
        }
        @endif
    </style>
    @if ($export === 'view')
        <script>
            {{-- Follow the data-bs-theme of the page embedding this report; opened on its own, follow the OS. --}}
            (function () {
                var root = document.documentElement;
                var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
                var host = null;

                try {
                    host = window.parent !== window ? window.parent.document : null;
                } catch (e) {}

                var apply = function () {
                    var theme = host
                        ? (host.documentElement.getAttribute('data-bs-theme') || (host.body && host.body.getAttribute('data-bs-theme')) || 'light')
                        : (media && media.matches ? 'dark' : 'light');

                    root.setAttribute('data-bs-theme', theme === 'dark' ? 'dark' : 'light');
                };

                apply();

                if (host && window.MutationObserver) {
                    var observer = new MutationObserver(apply);
                    observer.observe(host.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
                    if (host.body) {
                        observer.observe(host.body, { attributes: true, attributeFilter: ['data-bs-theme'] });
                    }
                } else if (media && media.addEventListener) {
                    media.addEventListener('change', apply);
                }
            })();
        </script>
    @endif
    @stack('styles')
</head>
<body>
@endif

@if ($export === 'pdf')
    @include('i-reports::partials.pdf-page')
@endif

@if ($export === 'print' && $printPart)
    @include('i-reports::partials.print-parts', ['printPart' => $printPart])
@endif

@if ($headerView && $export !== 'csv')
    @includeIf($headerView)
@elseif (in_array($export, ['print', 'pdf']))
    @include('i-reports::partials.report-header')
@endif

{{ $slot }}

@if ($export === 'print' && ($printPart === null || $printPart['auto_print']))
    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
@endif

@if (in_array($export, ['view', 'print', 'pdf']))
</body>
</html>
@endif
