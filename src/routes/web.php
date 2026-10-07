<?php

use Illuminate\Support\Facades\Route;
use Rishadblack\IReports\Http\Controllers\ExportDownloadController;
use Rishadblack\IReports\Http\Controllers\ReportViewController;

$middleware = (array) config('i-reports.route_middleware', ['web']);

if ($throttle = config('i-reports.route_throttle')) {
    $middleware[] = 'throttle:'.$throttle;
}

$prefix = config('i-reports.route_prefix', 'ireport');

Route::get($prefix.'/view', ReportViewController::class)
    ->middleware($middleware)
    ->name('i-reports.view');

Route::get($prefix.'/exports/{export}/download', ExportDownloadController::class)
    ->whereNumber('export')
    ->middleware($middleware)
    ->name('i-reports.exports.download');
