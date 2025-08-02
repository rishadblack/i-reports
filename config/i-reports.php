<?php

return [
    'report_namespace' => 'Reports',
    'report_suffix' => '',       // No suffix
    'route_prefix' => 'ireport', // Default route prefix
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
        'margin_left' => 2,
        'margin_right' => 2,
        'margin_top' => 2,
        'margin_bottom' => 5,
    ],
    'export_options' => [
        ['type' => 'print', 'name' => 'Print', 'class' => 'btn-primary'],
        ['type' => 'pdf', 'name' => 'PDF', 'class' => 'btn-success'],
        ['type' => 'xlsx', 'name' => 'Excel', 'class' => 'btn-info'],
        ['type' => 'csv', 'name' => 'CSV', 'class' => 'btn-warning'],
    ],
    'pdf_header' => [
        'html_view' => null, //Header location for html view like pdf_header.blade.php
        'left' => null,      //current_page,total_page,current_page_and_total_page,date,time,date_and_time,custom text
        'center' => null,    //current_page,total_page,current_page_and_total_page,date,time,date_and_time,custom text
        'right' => null,     //current_page,total_page,current_page_and_total_page,date,time,date_and_time,custom text
    ],
    'pdf_footer' => [
        'html_view' => null,                     //Footer location for html view like pdf_footer.blade.php
        'left' => 'current_page_and_total_page', //current_page,total_page,current_page_and_total_page,date,time,date_and_time,custom text
        'center' => 'Wire Report',               //current_page,total_page,current_page_and_total_page,date,time,date_and_time,custom text
        'right' => 'date_and_time',              //current_page,total_page,current_page_and_total_page,date,time,date_and_time,custom text
    ],
];
