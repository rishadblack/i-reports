{{-- Opening of a streamed (chunked) print page or PDF. --}}
@if ($export === 'print')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        @include('i-reports::partials.styles')
        @include('i-reports::partials.print-page')
    </style>
</head>
<body>
@else
    @include('i-reports::partials.pdf-page')
@endif

@if ($headerView)
    @includeIf($headerView)
@else
    @include('i-reports::partials.report-header')
@endif
