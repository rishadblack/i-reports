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
        thead th {
            position: sticky;
            top: 0;
        }
        @endif
    </style>
    @stack('styles')
</head>
<body>
@endif

@if ($export === 'pdf')
    @include('i-reports::partials.pdf-page')
@endif

@if ($headerView && $export !== 'csv')
    @includeIf($headerView)
@elseif (in_array($export, ['print', 'pdf']))
    @include('i-reports::partials.report-header')
@endif

{{ $slot }}

@if ($export === 'print')
    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
@endif

@if (in_array($export, ['view', 'print', 'pdf']))
</body>
</html>
@endif
