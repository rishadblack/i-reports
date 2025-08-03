@props([
    'header' => null,
    'summary' => null,
])
@if (!in_array($export, ['csv', 'xlsx']))
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Document</title>
        <style>
            @page {
                header: page-header;
                footer: page-footer;
            }

            @font-face {
                font-family: 'SolaimanLipi';
                src: url('{{ url('fonts/SolaimanLipi.ttf') }}') format('truetype');
            }

            body {
                font-family: 'SolaimanLipi', sans-serif;
                font-size: 12px;
            }
        </style>
        @stack('styles')
    </head>

    <body>
@endif
@if ($export)
    {{ $header }}
@endif

{{ $summary }}
{{ $slot }}


@if (in_array($export, ['pdf', 'print']))
    @if (in_array($export, ['pdf']))
        <htmlpagefooter name="page-footer">
            <table width="100%" style="font-size: 8pt;">
                <tr>
                    <td width="33%">{PAGENO}/{nbpg}</td>
                    <td width="33%" align="center">{{ config('app.name') }}</td>
                    <td width="33%" align="right">{{ now()->format('d-m-Y H:i') }}</td>
                </tr>
            </table>
        </htmlpagefooter>
    @endif

    @if (in_array($export, ['print']))
        <script>
            window.print();
        </script>
    @endif
    </body>

    </html>
@endif
