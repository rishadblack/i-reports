# rishadblack/i-reports

Paginated, filterable Laravel reports with a Livewire viewer and print, PDF, Excel and CSV export, all from one report class (a Blade view is optional).

- One class describes the query, columns and filters. Reports render through the package's default grid; add a Blade view only to customize the markup.
- A Bootstrap 5.3 Livewire viewer adds search, filters, sorting, paging, saved presets and an export menu.
- Exports stream from the database: CSV is written row by row, Excel in chunks, PDF through mpdf. Large exports can run on the queue.
- Every cell is escaped by default. The report route only accepts short-lived, user-bound tokens.
- Octane and queue safe: no static state.

Requires PHP 8.3+, Laravel 11, 12 or 13, Livewire 3 or 4, Bootstrap 5.3 CSS on the page that embeds the viewer.

## Installation

```bash
composer require rishadblack/i-reports
php artisan vendor:publish --tag=i-reports.config
```

Set the namespace and suffix so reports live in their own folder, and add `auth` when reports hold private data:

```php
// config/i-reports.php
'report_namespace' => 'Reports',          // App\Livewire\Reports\...
'report_suffix' => 'Report',              // "users" resolves to UsersReport
'route_middleware' => ['web', 'auth'],
```

## Quick start

```bash
php artisan make:report users
```

This creates `App\Livewire\Reports\UsersReport` and a Pest test; the report renders through the package's default grid view. Embed the viewer anywhere:

```blade
<livewire:i-reports.report-viewer report="users" />
```

Need custom markup? Copy the default view into the app and edit it — the report picks it up automatically because an existing view at the conventional path always wins over the package default:

```bash
php artisan i-reports:view users          # resources/views/livewire/reports/users-report.blade.php
# or scaffold class + view together:
php artisan make:report users --view
```

### The report class

```php
namespace App\Livewire\Reports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Views\Column;
use Rishadblack\IReports\Views\Filter;

class UsersReport extends BaseReportController
{
    public function builder(): Builder
    {
        return User::query()->where('tenant_id', auth()->user()->tenant_id);
    }

    public function configure(): void
    {
        $this->setReportTitle('User List');
        $this->setDefaultSort('users.name');
        $this->setOrientation('landscape');
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->searchable()->sortable(),
            Column::make('Email', 'email')->searchable()->sortable(),
            Column::make('District', 'district.name')->sortable(),
            Column::make('Balance', 'balance')->money('BDT')->sum()->sortable(),
            Column::make('Active', 'active')->boolean(),
            Column::make('Joined', 'created_at')->date('d M Y')->hideIn('csv'),
            Column::make('Status', 'status')->badge(['active' => ['Active', 'badge bg-success'], 'blocked' => ['Blocked', 'badge bg-danger']]),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::make('Status', 'status')->select(['active' => 'Active', 'blocked' => 'Blocked'])->column('users.status'),
            Filter::make('Joined', 'joined')->dateRange()->column('users.created_at'),
            Filter::make('Balance', 'balance')->numberRange()->column('users.balance'),
            Filter::make('Country', 'country_id')->component('selects.country-select')->column('users.country_id'),
            Filter::make('District', 'district_id')->component('selects.district-select')->dependsOn('country_id')->column('users.district_id'),
        ];
    }

    public function authorize(): bool
    {
        return auth()->user()?->can('view-users-report') ?? false;
    }
}
```

### The view

```blade
<x-i-reports::layout>
    <x-i-reports::table>
        <x-i-reports::thead>
            <x-i-reports::tr>
                @foreach ($columns as $column)
                    <x-i-reports::th :column="$column" />
                @endforeach
            </x-i-reports::tr>
        </x-i-reports::thead>
        <x-i-reports::tbody>
            <x-i-reports::rows :rows="$datas" />
        </x-i-reports::tbody>
        <x-i-reports::aggregates />
    </x-i-reports::table>
</x-i-reports::layout>
```

`x-i-reports::rows` renders every row and visible column in one component. For custom cell markup, loop with `x-i-reports::tr` and `x-i-reports::td :column="$column" :row="$row"` instead.

Full exports (print, PDF, Excel, CSV) raise the PHP memory and time limits to `export_memory_limit` and `export_time_limit`, and Laravel Debugbar is disabled on the report route.

### Very large print and PDF (100k+ rows)

Above `stream_threshold` rows (default 5000), print and PDF no longer render your Blade view. They stream:

- Rows are read from the database in chunks; the whole result set and the whole HTML never sit in memory.
- **PDF:** every `pdf_chunk_size` rows (default 500) become one complete table that is passed to mPDF in its own `WriteHTML` call, like laravel-mpdf's `chunkLoadView`. No call reaches `pcre.backtrack_limit`. With `pdf_chunk_page_break` each chunk starts on a new page, so column headers stay clean.
- **Print:** the page is flushed to the browser chunk by chunk as one continuous table.
- Column types, styles, `hideIn()`, group headers, subtotals and the grand total are kept. The view's custom layout is not used.

Per report: `$this->setStreamThreshold(0)` always streams, `$this->setStreamThreshold(PHP_INT_MAX)` always uses the view.

A custom view can still be split for mPDF the laravel-mpdf way. Put `<x-i-reports::chunk />` between complete blocks (never inside a table); in PDF it prints the `pdf_chunk_separator` marker (`<html-separator/>`), the HTML is cut there, and each piece is written separately. In other outputs it renders nothing.

mPDF itself needs about 3 seconds per 1000 table rows, so a 100k-row PDF takes minutes. Enable `queue.enabled` so large PDFs are generated by `ExportReportJob` instead of the web request.

## How it works

1. The viewer encrypts its state (report, filters, search, sort, page, page size, export type and the row count) into a token that expires after `token_ttl` minutes and is bound to the signed-in user.
2. `GET /ireport/view?token=...` resolves the token, checks `authorize()`, builds the query (joins for relation columns, filters, search, sort, selects, `additionalQuery()`) and renders the view in the iframe, or returns the export.
3. Because the viewer already counted the rows, the iframe request runs no second COUNT.

Set `viewer_mode` to `inline` to render the table inside the Livewire component instead of an iframe. Sorting then works with `wire:click` and each interaction is one request.

## Report settings

All setters are called in `configure()`.

| Setter | Purpose |
|---|---|
| `setReportTitle()`, `setHeaderTitle()` | Page title and organisation name (PDF footer) |
| `setDefaultSort($field, $direction)` | Sort used when the user has not chosen one. Qualify the column. |
| `setPagination()`, `setPaginationList()` | Default and selectable page sizes |
| `setSearchField(['relation.field', 'col->json'])` | Extra search fields besides `searchable()` columns |
| `setAdditionalSelects()` | Extra `addSelect` expressions, for example aggregates read in `map()` |
| `setPaperSize()`, `setOrientation()` | PDF page setup |
| `setFileName()`, `setFileTitle()` | Download file name and PDF title |
| `setHeaderView()`, `setPdfHeaderView()`, `setPdfFooterView()` | Blade views for the report header and the PDF page header/footer |
| `setExcelMode('query'\|'view')` | Stream the query (default) or convert the Blade table |
| `setGroupBy('column')` | Group rows for `x-i-reports::grouped-tbody` |

Hooks you can override: `authorize()`, `filters()`, `additionalQuery()`, `search()`, `map()`, `summaries()`, `additionalData()`.

## Columns

`Column::make('Title', 'field')` or `'relation.field'` for `BelongsTo`, `HasOne` and `MorphOne` relations (left-joined and aliased by the relation name).

| Method | Effect |
|---|---|
| `searchable()` | Included in the search box |
| `sortable()` | Can be sorted from the viewer. Non-sortable columns are rejected server side. |
| `hide()` | Never selected or rendered |
| `hideIn('pdf\|xlsx\|csv\|print\|view\|inline')` | Hidden in some outputs only |
| `custom()` | Not selected from the database. Render it with `format()` or in the view. |
| `format(fn ($value, $row, $column) => ...)` | Display transform. **Escaped** unless `html()` is called. |
| `html()` | Print `format()` output raw. |
| `exportFormat(fn ($value, $row) => ...)` | Value for CSV and Excel |
| `style('css')`, `style(fn ($row) => 'css')`, `align()`, `width()` | Cell styling |
| `number()`, `money('BDT')`, `date()`, `datetime()`, `boolean()`, `badge([...])`, `link(fn)`, `image()` | Column types with display and export formatting |
| `sum()`, `avg()`, `count()`, `min()`, `max()` | Aggregates over the filtered rows, shown by `x-i-reports::aggregates` and in exports |

Because relation columns add joins, qualify base-table columns in filters, sorts and `additionalQuery()`.

## Filters

`Filter::make('Title', 'key')` renders in the viewer's filter dialog and applies through `->filter(fn (Builder $query, $value) => ...)` or automatically on `->column('table.col')`.

| Type | Value | Default constraint |
|---|---|---|
| `text()` | string | `LIKE %value%` |
| `select([...])` | string | `=` |
| `multiSelect([...])` | array | `IN` |
| `date()` | `Y-m-d` | `whereDate =` |
| `dateRange()` | `['from' => ..., 'to' => ...]` | `whereDate >= / <=` |
| `number()`, `numberRange()` | number / `['from', 'to']` | `=` / `>= <=` |
| `boolean()` | `'1'` or `'0'` | `=` |
| `component('livewire.name', [...])` | from the component | callback or column |
| `bladeComponent('x-name', [...])` | from the component | callback or column |

Values are sanitised by type before they reach the query. Invalid values are ignored. `dependsOn('parent_key')` re-mounts a component filter with the parent's value and clears it when the parent changes. `default($value)` pre-fills a filter.

## Exports

| Format | How |
|---|---|
| CSV | Streamed with `fputcsv`, BOM for Excel, formulas neutralised |
| Excel | `query` mode streams the query in chunks through the column types; `view` mode converts the Blade table |
| PDF | mpdf renders the Blade view with optional header/footer views and `<x-i-reports::page-break />` |
| Print | Full page with `window.print()` |

Queued exports: enable `queue.enabled`, set `queue.threshold`. Exports above the threshold are generated by `ExportReportJob`, stored on `queue.disk`, and `ReportExportCompleted` is dispatched.

From the console or the scheduler:

```bash
php artisan i-reports:export users --format=xlsx --filter=status=active --sort=name --disk=s3 --path=reports
php artisan i-reports:export users --format=pdf --queue --user=1
php artisan i-reports:list
```

Direct links without the viewer:

```php
use Rishadblack\IReports\Helpers\RequestHelper;

$url = route('i-reports.view', [
    'token' => (new RequestHelper(['report' => 'users', 'export' => 'pdf', 'filters' => ['status' => 'active']]))->generateToken(),
]);
```

## Branded headers and footers

Every export carries the same branding:

- **Print and PDF:** a header with logo, organisation, tagline, report title, generation time and user, and the applied filters; a footer with organisation, title, date and *Page X of Y* (PDF on every page; print via page margin boxes in Chromium browsers).
- **Excel:** the same title block above a styled heading row, striped and bordered rows, a bold totals row, frozen headings, autofilter, number formats, print setup and a *Page &P of &N* footer. Formula-like text is stored as plain text.
- **CSV:** title rows above the data (`csv.title_rows`; turn off for machine imports) and a `Total` row.

```php
// config/i-reports.php
'branding' => [
    'name' => 'Acme Ltd',                 // null = setHeaderTitle() or app.name
    'tagline' => 'House 12, Road 5, Dhaka',
    'logo' => public_path('images/logo.png'),
    'accent_color' => '#1f2937',
    'show_filters' => true,
    'show_generated_by' => true,
],
```

Filters describe themselves in the header and the viewer's chips. For component filters whose values are ids, add `->displayUsing(fn ($id) => Country::find($id)?->name)`. Set `header_view` to replace the built-in header with your own Blade view.

## Showing and hiding columns

The viewer has a **Columns** menu. Hidden columns are kept in the URL, in saved views and in the token, so the iframe and every export (print, PDF, Excel, CSV, background exports) leave them out.

```php
Column::make('Email', 'email')->hiddenByDefault(), // off until the user switches it on
Column::make('Name', 'name')->hideable(false),     // always shown
```

Hidden columns are also removed from the SQL: their `SELECT` and, for relation columns, their `JOIN` are skipped, which keeps wide reports fast. A hidden column stays in the query only while the active search, sort or `setGroupBy()` needs it. If `map()`, another column's `format()` or a custom view reads a column's value, mark it `->alwaysSelect()`.

The model key is always selected (unless the query is grouped), so `format()` callbacks and links can use `$row->id`.

## Background exports

With `queue.enabled`, an export whose row count reaches its format threshold runs in a queued job instead of the web request:

```php
'queue' => [
    'enabled' => true,
    'thresholds' => ['pdf' => 1000, 'xlsx' => 50000, 'csv' => 200000],
    'disk' => 'local',
    'keep_days' => 7,
],
```

The viewer lists the user's recent exports with their status (queued, preparing, ready, failed), polls while any is pending, and shows a **Download** button when the file is ready. Downloads are only served to the user (or guest session) that requested them.

Setup: run `php artisan migrate` (creates `i_reports_exports`), keep a worker running (`php artisan queue:work`), and schedule `php artisan i-reports:prune-exports` daily to delete old files. `ReportExportCompleted` is dispatched for each finished export if you want to send a notification or email.

### Live updates: polling or sockets (Reverb, Pusher, Ably)

`queue.realtime` decides how the viewer learns that an export changed status:

| Mode | Behaviour |
|---|---|
| `poll` (default) | The viewer polls every `poll_seconds` while an export is pending. Nothing else to set up. |
| `broadcast` | Each status change is pushed with `ReportExportUpdated` (a `ShouldBroadcastNow` event) on the private channel `i-reports.exports.{userId}`, as `.report-export.updated`. The viewer listens through Laravel Echo and updates instantly. |

Broadcast mode falls back to polling by itself when the page has no `window.Echo` or the visitor is a guest (guests cannot join private channels), and keeps a slow safety poll (`fallback_poll_seconds`) in case a message is missed. A failing socket server is reported but never fails the export. The package registers the channel authorization: users only receive their own exports.

To use Laravel Reverb:

```bash
php artisan install:broadcasting   # installs Reverb, laravel-echo and the broadcasting routes
php artisan reverb:start
```

```dotenv
BROADCAST_CONNECTION=reverb
I_REPORTS_REALTIME=broadcast
```

The viewer also dispatches `i-reports:export-ready` (`id`, `format`, `url`) and `i-reports:export-failed` browser events, so you can show your own toast:

```js
window.addEventListener('i-reports:export-ready', (event) => showToast(`Your ${event.detail.format.toUpperCase()} is ready`));
```

## Saved presets

Enable `presets.enabled`, publish and run the migration (`--tag=i-reports.migrations`). Signed-in users can save, apply and delete named filter sets per report from the viewer.

## Registry

Reports resolve by convention. To map explicit names, or to list reports:

```php
use Rishadblack\IReports\Facades\IReports;

IReports::register('people', App\Livewire\Reports\UsersReport::class);
IReports::all();
```

## Security notes

- Cell output is escaped. Call `html()` only on columns whose `format()` returns markup you control.
- The report route refuses requests without a valid token. Tokens expire and are bound to the user who created them.
- Sorting is limited to `sortable()` columns. Page size is capped by `max_per_page`.
- The route is throttled (`route_throttle`). Add `auth` to `route_middleware` for private data and scope `builder()` to the current user or tenant.

## Testing

```bash
composer test
composer lint
composer analyse
```

## License

MIT.
