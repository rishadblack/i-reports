# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-10-07

First stable 1.0 release. Everything in 1.0.0-beta.1 below, plus:

### Added

- Page setup dialog for print and PDF: paper, orientation, table font size and scale (`page_setup` config; `setPaperSize()`, `setOrientation()`, `setFontSize()`, `setScale()`).
- Large prints open in parts with Previous / Next (`print.split_after`, `print.rows_per_part`, `setPrintSplitAfter()`).
- Professional header and footer for print, PDF and Excel: logo, name, tagline, address, contact, info band (generated, prepared by, records, filters), running header from page 2, `footer_note`.
- `export_source` (`columns` or `view`, `setExportSource()`): `view` renders the report's own Blade view for every output, as in 0.1.x. Excel and CSV convert the view, PDF never streams, print never splits.
- `columns_hideable` config to turn the column picker off; `eager_load_relations` (default true).
- Filter update modes: `->live($ms)`, `->onChange()`, `->onBlur()`, `->deferred()` and `filter_update` / `filter_debounce` config. By default the report changes only when **Apply filters** is clicked; the dialog edits a draft (`filters`) that Apply copies to `applied_filters`, and Cancel discards it.
- Excel: real dates in the column's format, cleaner sheet (no gridlines, padding, rich-text title block, logo).

### Fixed (compatibility with 0.1.x)

- `<x-i-reports::table type="header">` is hidden on screen again and shown in exports.
- Custom header views receive `$report_title` and `$header_title` in streamed print and PDF.
- `custom()->searchable()` columns are searched again; JSON search fields support nested keys (`col->a.b`) and ignore case.
- The total count includes conditions added in `additionalQuery()`.
- `select()` and `text()` filters pass lists to callbacks that accept arrays (scalar-typed callbacks get the first value).
- Blade filter components receive `key` and `datalist` again; Livewire filter components receive `key`.
- `exportEvent` carries both `event.url` and `event[0].url`; the `exportIframe` listener is back.
- The primary key is not added to `DISTINCT` or raw aggregate selects; chunked exports of grouped queries order by the group columns (MySQL strict mode).
- Page setup keeps the font sizes of `default_style` unless a size is chosen.

### Changed

- `route_throttle` is off by default (`null`).

## [1.0.0-beta.1] - 2026-10-07

First 1.0 beta: a rewrite focused on security, Octane safety, very large exports, a Bootstrap 5.3 viewer and branded exports. Breaking changes from 0.1.x are listed under **Changed** and **Removed**.

### Security

- Cell values are escaped by default. `Column::html()` opts a column into raw output.
- The report route requires a token. Query parameters are no longer read directly.
- Tokens are bound to the user who created them (`token_bind_user`) and expire after `token_ttl` minutes.
- Viewer properties the browser must not change (`report`, `filter_list`, `per_page_list`, `filter_extended_view`, `mode`, `presets_enabled`) are locked.
- Page size is validated against the list and capped by `max_per_page`; search is trimmed and limited; sort direction is validated.
- Sorting only accepts `sortable()` columns.
- Filter values are sanitised by type. Invalid values are ignored instead of raising errors.
- The report route is throttled (`route_throttle`) and uses the `web` middleware by default.
- Unknown reports return 404; `authorize()` returning false returns 403.
- CSV cells starting with `=`, `+`, `-`, `@` are neutralised.

### Added

- Request-scoped `ReportContext` replaces all static state (Octane and queue safe). `ReportHelper` delegates to it.
- `ReportRenderer` contract with a `BladeRenderer` implementation; `renderer` config key.
- Inline viewer mode (`viewer_mode` or `mode="inline"`): one request per interaction, no iframe.
- Column sorting from the viewer (header click in both modes, sort select in the toolbar).
- Column types: `number()`, `money()`, `date()`, `datetime()`, `boolean()`, `badge()`, `link()`, `image()`, plus `align()`, `width()`, `exportFormat()`.
- `hideIn()` is honoured per output.
- Aggregates: `sum()`, `avg()`, `count()`, `min()`, `max()` with `<x-i-reports::aggregates />`, included in CSV and Excel.
- Row grouping with `setGroupBy()` and `<x-i-reports::grouped-tbody />` (group headers and subtotals).
- Filter types: `multiSelect()`, `date()`, `dateRange()`, `number()`, `numberRange()`, `boolean()`; `column()` for automatic constraints; `dependsOn()` for dependent component filters; `default()`.
- Streaming CSV export (no spreadsheet library), chunked Excel export (`excel_mode` query), `setExcelMode('view')` to keep Blade layouts.
- Chunked print and PDF for very large reports (`stream_threshold`, `pdf_chunk_size`, `pdf_chunk_page_break`, `setStreamThreshold()`): rows stream from the database and each chunk is written to mPDF separately (laravel-mpdf `chunkLoadView` style) or flushed to the browser.
- `<x-i-reports::chunk />` separator for custom PDF views (`pdf_chunk_separator`), automatic `pcre.backtrack_limit` raise per piece.
- `<x-i-reports::rows />` fast row renderer; Laravel Debugbar disabled on the report route; `export_memory_limit` and `export_time_limit`.

- Professional Bootstrap 5.3 viewer: card header with title and record count, export dropdown, search, Filters with an active-count badge, removable filter chips, Columns menu, numbered pagination, loading overlay, two-column filter dialog, saved views menu.
- Branded export header and footer (`branding` config: name, tagline, logo, accent colour) for print, PDF, Excel and CSV; `Filter::displayUsing()` and `describe()` for readable filter values; `Report::appliedFilters()` and `branding()`.
- Excel styling: title block, styled heading row, stripes, totals row, frozen headings, autofilter, number formats, print setup and page footer; formula-looking text stored as text.
- Column picker: `hidden_columns` in the viewer, URL, presets and token; `Column::hideable()` and `hiddenByDefault()`; every export honours it.
- Tracked background exports: `i_reports_exports` table and `QueuedExport` model, per-format `queue.thresholds`, status panel with polling and download button, owner-checked download route, `i-reports:prune-exports`.
- The model key is always selected for non-grouped queries, so `$row->id` works in `format()` and links.
- Live export status: `queue.realtime` `poll` (default) or `broadcast` (Reverb, Pusher, Ably via Laravel Echo). `ReportExportUpdated` broadcasts every status change on the private `i-reports.exports.{userId}` channel (authorization registered by the package); the viewer listens with Livewire's Echo listeners, falls back to polling without Echo or for guests, and keeps a slow safety poll. Socket failures never fail an export.
- `i-reports:export-ready` and `i-reports:export-failed` browser events and a "ready" notice in the viewer.

### Fixed

- Relative links are made absolute before mPDF renders them (they are dead in a downloaded PDF).
- PDF page footer now uses `sethtmlpagefooter`, so it actually appears on every page.
- Empty table components no longer write a temporary Blade file per call (slow, and racy on Windows).
- Queued exports: `ExportReportJob`, `ReportExportCompleted` event, `queue` config with threshold.
- `ReportExporter` service (`download()` / `store()`).
- Console commands: `make:report`, `i-reports:export`, `i-reports:list`.
- Saved filter presets per user (`presets` config, migration, `ReportPreset` model).
- `authorize()` hook on reports.
- Report registry (`IReports::register()`, `reports` config).
- PDF header and footer views, `<x-i-reports::page-break />`.
- Bootstrap 5.3 viewer with an Alpine-driven filter dialog (Bootstrap JavaScript is no longer required). Toolbar selects use `wire:model.live`; the export tab is opened by a server-side `$this->js()` call; dependent filter values are passed as numbers so typed `#[Reactive]` props keep a stable hash.
- Postgres-compatible search (`ilike`), no raw SQL.
- GitHub Actions matrix, Pint and Larastan configuration.

### Changed

- Relation columns are joined only; the redundant eager load is gone.
- The viewer runs one COUNT per interaction and passes the total to the iframe request.
- Views publish to `resources/views/vendor/i-reports` (overrides now work).
- Default sort of the page size list includes the report's default page size.
- `WithPagination` is no longer used by the viewer.

### Removed

- Tailwind theme (the viewer targets Bootstrap 5.3 only).
- Unused config keys and dead code (`snappy` option, `IReports` placeholder, `rules()`, `exportIframe` listener).

## [0.1.7] - 2026-10-07

- PHP 8.3 support, filter fixes.

## [0.0.1] - 2025-07-29

- Initial release: report viewing, filters, pagination, PDF and Excel export, module support.
