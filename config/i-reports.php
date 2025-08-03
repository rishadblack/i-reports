<?php

return [
    'report_namespace' => 'Reports',
    'report_suffix' => '',       // No suffix
    'route_prefix' => 'ireport', // Default route prefix
    'route_middleware' => [],    // Default route middleware
    'default_pagination_list' => [50, 100, 150, 200, 250, 500],
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
];
