<?php

use Illuminate\Support\Facades\Route;
use Rishadblack\IReports\Http\Controllers\ReportViewController;

Route::get(config('i-reports.route_prefix') . '/view/{report}', ReportViewController::class)->middleware(config('i-reports.route_middleware'))->name('ireport.view');
