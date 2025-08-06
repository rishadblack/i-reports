<?php

return [
    'report_namespace' => null,
    'report_suffix' => null,     // No suffix
    'route_prefix' => 'ireport', // Default route prefix
    'use_cache_token' => false,
    'route_middleware' => [], // Default route middleware
    'default_pagination_list' => [50, 100, 150, 200, 250, 500],
    'header_view' => null,           // Default header view
    'pdf_export_by' => 'mpdf',       // snappy or mpdf
    'pdf_paper_size' => 'A4',        // A3, A4, A5, Legal, Letter, Tabloid
    'pdf_orientation' => 'portrait', // landscape or portrait
    'default_download_file_name' => 'report',
    'default_pagination' => 50,
    'show_reset_button' => true,
    'show_export_button' => true,
    'show_pagination' => true,
    'mpdf' => [
        'no-outline' => true,
        'margin_left' => 3,
        'margin_right' => 3,
        'margin_top' => 3,
        'margin_bottom' => 5,
    ],
    'export_options' => [
        ['type' => 'print', 'name' => 'Print', 'class' => 'btn-primary'],
        ['type' => 'pdf', 'name' => 'PDF', 'class' => 'btn-success'],
        ['type' => 'xlsx', 'name' => 'Excel', 'class' => 'btn-info'],
        ['type' => 'csv', 'name' => 'CSV', 'class' => 'btn-warning'],
    ],
    'default_style' => [
        'th' => "text-align: left; font-size: 14px; color: #ffffff; position: sticky; top: 0; background-color: #727070; padding: 6px;",
        'td' => "text-align: left; padding: 5px; border: 1px solid #cccccc;",
        'tr' => "border: 1px solid #cccccc;",
    ],
];
