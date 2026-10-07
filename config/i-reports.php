<?php

use Rishadblack\IReports\Renderers\BladeRenderer;

return [

    /*
    |--------------------------------------------------------------------------
    | Report resolution
    |--------------------------------------------------------------------------
    |
    | "users" resolves to {livewire.class_namespace}\{report_namespace}\Users{report_suffix}.
    | Explicit names can be mapped in `reports` (name => class) and skip the convention.
    |
    */
    'report_namespace' => 'Reports',
    'report_suffix' => 'Report',
    'reports' => [],

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    |
    | The iframe and every export go through GET {route_prefix}/view?token=...
    | A token is always required. Add 'auth' to the middleware for private data.
    | `route_throttle` is passed to the throttle middleware; null disables it.
    |
    */
    'route_prefix' => 'ireport',
    'route_middleware' => ['web'],
    'route_throttle' => '60,1',

    /*
    |--------------------------------------------------------------------------
    | Tokens
    |--------------------------------------------------------------------------
    |
    | Tokens carry the report request (filters, search, page, export) and expire after
    | `token_ttl` minutes. With `use_cache_token` the state lives in the cache and the URL
    | only holds a random id. `token_bind_user` ties a token to the user who created it.
    |
    */
    'use_cache_token' => false,
    'token_ttl' => 10,
    'token_bind_user' => true,

    /*
    |--------------------------------------------------------------------------
    | Viewer
    |--------------------------------------------------------------------------
    |
    | The viewer markup targets Bootstrap 5.3 (CSS only; the filter dialog uses Alpine).
    | mode: iframe (report rendered in an iframe) or inline (report table rendered inside
    | the Livewire component, one request per interaction).
    |
    */
    'viewer_mode' => 'iframe',
    'default_pagination' => 50,
    'default_pagination_list' => [25, 50, 100, 150, 200, 250, 500],
    'max_per_page' => 1000,
    'show_search' => true,
    'show_reset_button' => true,
    'show_filter_button' => true,
    'show_export_button' => true,
    'show_pagination' => true,
    'export_options' => [
        ['type' => 'print', 'name' => 'Print'],
        ['type' => 'pdf', 'name' => 'PDF'],
        ['type' => 'xlsx', 'name' => 'Excel'],
        ['type' => 'csv', 'name' => 'CSV'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    */
    'renderer' => BladeRenderer::class,
    'header_view' => null, // a custom Blade header replaces the built-in branded header

    /*
    |--------------------------------------------------------------------------
    | Branding (print, PDF, Excel and CSV headers and footers)
    |--------------------------------------------------------------------------
    |
    | name: organisation shown in headers and footers (null = report setHeaderTitle() or app.name).
    | logo: absolute path to a PNG or JPG; embedded in print, PDF and Excel.
    | tagline, address, contact: optional lines shown under the name.
    | footer_note: centre of every page footer (e.g. 'Confidential'); null shows who generated it.
    |
    */
    'branding' => [
        'name' => null,
        'tagline' => null,
        'address' => null,
        'contact' => null,
        'logo' => null,
        'footer_note' => null,
        'accent_color' => '#1f2937',
        'show_filters' => true,
        'show_generated_by' => true,
        'date_format' => 'd M Y, h:i A',
    ],

    'default_style' => [
        'th' => 'text-align: left; font-size: 9pt; font-weight: bold; color: #ffffff; background-color: #1f2937; padding: 6px 8px; border: 1px solid #1f2937;',
        'td' => 'text-align: left; font-size: 9pt; color: #1f2937; padding: 5px 8px; border: 1px solid #e5e7eb;',
        'tr' => '',
        'zebra' => 'background-color: #f8fafc;',
        'group' => 'font-weight: bold; font-size: 9pt; color: #1f2937; background-color: #e8edf3; padding: 6px 8px; border: 1px solid #d5dde6;',
        'aggregate' => 'font-weight: bold; font-size: 9pt; color: #1f2937; background-color: #eef2f6; padding: 6px 8px; border: 1px solid #cbd5e1;',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exports
    |--------------------------------------------------------------------------
    |
    | excel_mode: "query" streams the query in chunks through column definitions (fast,
    | memory safe). "view" renders the Blade view and converts the HTML table (keeps custom
    | layouts). Each report can override it with setExcelMode().
    |
    */
    'default_download_file_name' => 'report',
    // columns: exports are built from the column definitions (fast, streamed, typed Excel cells).
    // view: every output renders the report's own Blade view, like 0.1.x; use it when views compute
    // running balances, totals or extra rows. A report can override it with setExportSource().
    'export_source' => 'columns',
    'columns_hideable' => true, // users may hide columns in the viewer; per column ->hideable(false|true)
    'excel_mode' => 'query',
    'export_chunk_size' => 1000,
    'stream_threshold' => 5000,      // above this many rows print and PDF stream in chunks (0 = always)

    /*
    | Large prints open in parts so the browser does not hang: above split_after rows the print
    | page shows rows_per_part rows with Previous / Next buttons (0 = never split; stream instead).
    */
    'print' => [
        'split_after' => 500,
        'rows_per_part' => 1000,
    ],

    'pdf_chunk_size' => 500,         // rows per mPDF WriteHTML call when streaming
    'pdf_chunk_page_break' => true,  // start each streamed PDF chunk on a new page (clean headers)
    'pdf_chunk_separator' => '<html-separator/>', // split marker for custom views, see <x-i-reports::chunk />
    'export_memory_limit' => '512M', // raised only when lower; null keeps php.ini
    'export_time_limit' => 300,      // seconds; 0 keeps php.ini
    'csv' => [
        'delimiter' => ',',
        'bom' => true,
        'title_rows' => true, // organisation, title, filters and date above the data; false for machine imports
    ],
    'pdf_paper_size' => 'A4',
    'pdf_orientation' => 'portrait',

    /*
    |--------------------------------------------------------------------------
    | Page setup dialog (print and PDF)
    |--------------------------------------------------------------------------
    |
    | Choosing Print or PDF opens a dialog to pick paper, orientation, table font size and
    | scale before exporting. Defaults: pdf_paper_size, pdf_orientation, font_size and scale
    | below (a report can override each with setPaperSize(), setOrientation(), setFontSize()
    | and setScale()). Only values in these lists are accepted from the browser.
    |
    */
    'page_setup' => [
        'enabled' => true,
        'font_size' => null, // null = keep the sizes in default_style; or a size in pt, e.g. 9
        'scale' => 100,
        'papers' => ['A4', 'A3', 'A5', 'Letter', 'Legal'],
        'font_sizes' => [7, 8, 9, 10, 11, 12],
        'scales' => [50, 60, 70, 80, 90, 100, 110, 125, 150],
    ],
    'pdf_header_view' => null, // running header on every PDF page (none by default)
    'pdf_footer_view' => null, // replaces the default footer: organisation, title, date, page X of Y
    'mpdf' => [
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 16,
        'margin_header' => 5,
        'margin_footer' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queued exports
    |--------------------------------------------------------------------------
    |
    | When enabled, exports with at least `threshold` rows are generated by a queued job,
    | stored on `disk` under `path`, and ReportExportCompleted is dispatched when done.
    |
    */
    'queue' => [
        'enabled' => false, // needs a queue worker and the exports table (php artisan migrate)
        'thresholds' => [   // rows at which a format is generated in the background
            'pdf' => 1000,
            'xlsx' => 50000,
            'csv' => 200000,
        ],
        'threshold' => 5000, // fallback for formats without a threshold
        'connection' => null,
        'queue' => null,
        'disk' => 'local',
        'path' => 'i-reports/exports',
        'table' => 'i_reports_exports',
        'keep_days' => 7, // php artisan i-reports:prune-exports
        'poll_seconds' => 3, // how often the viewer refreshes pending exports
        // poll: the viewer polls while an export is pending.
        // broadcast: status changes are pushed over Reverb/Pusher/Ably (Laravel Echo on the page);
        // without window.Echo, or for guests, the viewer falls back to polling automatically.
        'realtime' => env('I_REPORTS_REALTIME', 'poll'),
        'broadcast_channel' => 'i-reports.exports', // private channel prefix: i-reports.exports.{userId}
        'fallback_poll_seconds' => 30, // safety poll in broadcast mode, in case a message is missed
    ],

    /*
    |--------------------------------------------------------------------------
    | Saved filter presets
    |--------------------------------------------------------------------------
    |
    | Lets signed-in users save and reapply filter sets per report. Publish and run the
    | migration with: php artisan vendor:publish --tag=i-reports.migrations
    |
    */
    'presets' => [
        'enabled' => false,
        'table' => 'i_reports_presets',
    ],
];
