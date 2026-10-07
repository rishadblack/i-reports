---
name: i-reports-development
description: "Use this skill when building reports with rishadblack/i-reports. Trigger when creating or editing classes that extend Rishadblack\\IReports\\BaseReportController, defining report columns (Column::make) or filters (Filter::make), writing report Blade views with x-i-reports::* components, embedding <livewire:i-reports.report-viewer>, exporting reports to print, PDF, Excel or CSV, queueing or scheduling exports, configuring config/i-reports.php, using Livewire dropdowns (e.g. wire-tomselect) as report filters, or testing reports with Pest."
license: MIT
metadata:
  author: rishadblack
---

# I-Reports Development

`rishadblack/i-reports` turns an Eloquent query into a report. You write one class (query, columns, filters) and one Blade view. The package provides:

- a Bootstrap 5.3 Livewire viewer with search, a filter dialog, column sorting, pagination, saved presets and an export menu;
- a token-protected route that renders the view inside an iframe (or inline);
- print, PDF (mpdf), Excel and CSV exports, streamed from the query, optionally on the queue.

## How It Fits Together

1. The page renders `<livewire:i-reports.report-viewer report="users" />`.
2. The viewer encrypts its state (report name, search, filters, sort, page, page size, export type, row count) into a token that expires after `token_ttl` minutes (default 10) and is bound to the signed-in user. It loads `route('i-reports.view', ['token' => ...])` in an iframe.
3. The route resolves `users` to a report class, checks `authorize()`, builds the query (joins for relation columns, filters, search, sort, selects, `additionalQuery()`), then paginates (view) or streams (export).
4. The class renders its Blade view. For `pdf`, `xlsx` and `csv` it returns a download. For `print` it renders every row and calls `window.print()`.

## Setup

- PHP 8.3+, Laravel 11–13, Livewire 3 or 4. The viewer needs Bootstrap 5.3 CSS on the page. Bootstrap JavaScript is not required (the filter dialog uses Alpine, bundled with Livewire).
- Publish the config with `php artisan vendor:publish --tag=i-reports.config` and set:

```php
// config/i-reports.php
'report_namespace' => 'Reports',       // App\Livewire\Reports\...
'report_suffix' => 'Report',           // "users" resolves to UsersReport
'route_middleware' => ['web', 'auth'], // default is ['web']; add auth for private data
```

Other keys: `route_prefix`, `route_throttle` (`'60,1'`), `token_ttl`, `token_bind_user`, `use_cache_token`, `viewer_mode` (`iframe` or `inline`), `default_pagination`, `default_pagination_list`, `max_per_page`, `show_*` toolbar toggles, `export_options`, `excel_mode` (`query` or `view`), `export_chunk_size`, `csv.delimiter`/`csv.bom`, `pdf_paper_size`, `pdf_orientation`, `pdf_header_view`, `pdf_footer_view`, `mpdf`, `header_view`, `default_style.th|td|tr|group|aggregate`, `queue.*`, `presets.*`, `reports` (name => class map), `renderer`.

## Scaffolding

```bash
php artisan make:report users                 # class + view + Pest test
php artisan make:report sales.daily --model=App\\Models\\Sale
php artisan i-reports:list                    # registered reports
php artisan i-reports:export users --format=xlsx --filter=status=active --disk=local
```

## Name, Class and View Resolution

| Report name | Class | View |
|---|---|---|
| `users` | `App\Livewire\Reports\UsersReport` | `livewire.reports.users-report` |
| `sales.daily` | `App\Livewire\Reports\Sales\DailyReport` | `livewire.reports.sales.daily-report` |
| `billing::invoices` | `Modules\Billing\Livewire\Reports\InvoicesReport` | `billing::livewire.reports.invoices-report` |

- Names may contain only letters, digits, `.` and `-`. Unknown names give HTTP 404. `IReports::register('alias', SomeReport::class)` or the `reports` config map explicit names.
- The class must extend `BaseReportController`.

## Creating a Report

```php
<?php

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
        return User::query(); // scope to the current user or tenant here on shared data
    }

    public function configure(): void
    {
        $this->setReportTitle('User List');
        $this->setDefaultSort('users.name');
        $this->setOrientation('landscape');
    }

    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<int, Column>
     */
    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->searchable()->sortable(),
            Column::make('Email', 'email')->searchable()->sortable(),
            Column::make('District', 'district.name')->sortable(),
            Column::make('Balance', 'balance')->money('BDT')->sum()->sortable(),
            Column::make('Joined', 'created_at')->date('d M Y')->hideIn('csv'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(): array
    {
        return [
            Filter::make('Status', 'status')->select(['active' => 'Active', 'blocked' => 'Blocked'])->column('users.status'),
            Filter::make('Joined', 'joined')->dateRange()->column('users.created_at'),
        ];
    }
}
```

`configure()` runs in the constructor. Use it only for settings:

| Setter | Default | Purpose |
|---|---|---|
| `setReportTitle(string)` | class name as words | Page title and PDF footer text. |
| `setHeaderTitle(string)` | `config('app.name')` | Organisation name in the PDF footer. |
| `setDefaultSort(string $field, string $dir = 'asc')` | none | Sort when the user has not chosen one. Use a qualified column. |
| `setPagination(int)` / `setPaginationList(array)` | config | Default and selectable page sizes. |
| `setPaperSize(string)` / `setOrientation('landscape'\|'portrait')` | config | PDF page setup. |
| `setFileName(string)` / `setFileTitle(string)` | `users-report-ymd-His` | Download file name and PDF title. |
| `setSearchField(array\|string)` | `[]` | Extra searchable fields besides `->searchable()` columns. `relation.field` is searched with `whereHas`; `col->key` searches JSON. |
| `setAdditionalSelects(array\|string)` | `[]` | Extra `addSelect` expressions, e.g. aggregates read in `map()`. |
| `setHeaderView(string)` | config | Blade view included at the top of the report. |
| `setPdfHeaderView(string)` / `setPdfFooterView(string)` | config | Blade views for the PDF page header and footer. |
| `setExcelMode('query'\|'view')` | config `excel_mode` | Stream the query (fast, uses column types) or convert the Blade table (keeps custom layout). |
| `setGroupBy(string $columnName)` | none | Group rows for `<x-i-reports::grouped-tbody />`. |

Optional hooks you can override:

| Method | When it runs |
|---|---|
| `authorize(): bool` | Before viewing or exporting. `false` gives 403. |
| `additionalQuery(Builder $builder): Builder` | After selects, before pagination. Use it for `withCount`, `groupBy`, etc. |
| `search(Builder $builder, string $search): Builder` | After the default search, only when a term is present. |
| `map(Collection $collection): Collection` | On each page and each export chunk, for computed values. |
| `summaries(Builder $builder): array` | Receives the filtered query. Available as `$summaries` in the view. |
| `additionalData(): array` | Extra variables, available as `$additional_datas`. |

## Columns

`Column::make(string $title, string $name)`. The title comes first.

- `'field'` is a column on the base table. `'relation.field'` (or `'a.b.field'`) follows `BelongsTo`, `HasOne` or `MorphOne` relations with LEFT JOINs aliased by the relation name. The names must be relation methods on the model.
- `->searchable()` includes the column in the search box. `->sortable()` allows sorting from the viewer; other columns are rejected server side.
- `->hide()` removes the column everywhere. `->hideIn('pdf|xlsx|csv|print|view|inline')` hides it in some outputs.
- `->custom()` skips it in selects. Render it with `format()` reading `$row`, or by hand in the view.
- `->format(fn ($value, $row, Column $column) => ...)` transforms the value. **Output is escaped.** Call `->html()` when the callback returns trusted markup. Return an `HtmlString` for the same effect.
- `->exportFormat(fn ($value, $row) => ...)` sets the CSV and Excel value.
- Types: `->number($decimals)`, `->money('BDT', $decimals, 'before'|'after')`, `->date('d/m/Y')`, `->datetime()`, `->boolean('Yes', 'No')`, `->badge(['paid' => ['Paid', 'badge bg-success']])`, `->link(fn ($value, $row) => url)`, `->image($width)`. Types format both display and export (money exports as a number, badge as its label).
- `->sum()`, `->avg()`, `->count()`, `->min()`, `->max()` compute an aggregate over all filtered rows. Render with `<x-i-reports::aggregates />`; CSV and Excel append the row.
- `->style('color: red;')` or `->style(fn ($row) => ...)`, `->align('right')`, `->width('10%')` add inline CSS, merged over `default_style.td`.

Because relation columns add joins, **always qualify base-table columns** in filters, sorts and `additionalQuery()` (`users.country_id`, not `country_id`).

## Filters

`Filter::make(string $title, string $key)`. Each filter is bound to `filters.{key}` in the viewer's filter dialog. Values are sanitised by type; invalid values are ignored.

```php
Filter::make('Name contains', 'name')->text()->column('users.name'),
Filter::make('Status', 'status')->select(['active' => 'Active'])->column('users.status'),
Filter::make('Roles', 'roles')->multiSelect(['admin' => 'Admin', 'staff' => 'Staff'])->column('users.role'),
Filter::make('Joined', 'joined')->dateRange()->column('users.created_at'),          // value: ['from' => 'Y-m-d', 'to' => 'Y-m-d']
Filter::make('Balance', 'balance')->numberRange()->column('users.balance'),
Filter::make('Active', 'active')->boolean()->column('users.active'),                // value: '1' or '0'
Filter::make('Status', 'status')->select([...])->filter(fn (Builder $query, string $status) => $query->where('users.status', $status)),

// Any Livewire component bound with wire:model (e.g. a rishadblack/wire-tomselect SearchComponent):
Filter::make('Country', 'country_id')->component('selects.country-select')->column('users.country_id'),
// Dependent: the child is re-mounted with ['country_id' => value] and cleared when the parent changes.
// Declare #[Reactive] public ?int $country_id on the child component.
Filter::make('District', 'district_id')->component('selects.district-select')->dependsOn('country_id')->column('users.district_id'),

Filter::make('Joined', 'joined')->bladeComponent('forms.date-range', ['options' => []]),
```

- `->column('table.col')` applies the default constraint for the type (`like`, `=`, `in`, `whereDate`, ranges). `->filter()` replaces it and receives the sanitised value.
- `->default($value)` pre-fills the filter; `->placeholder()`, `->customClass('col-md-6')` tune the UI.
- Inside the report, `$this->getFilter('key')` returns the current value or `false`.

## The Report View

Variables: `$datas` (a `LengthAwarePaginator` when viewing, a `Collection` when exporting), `$columns` (visible in this output), `$all_columns`, `$aggregates`, `$summaries`, `$additional_datas`, `$group_by`, `$export` (`view`, `inline`, `print`, `pdf`, `xlsx` or `csv`), `$report`, `$report_title`, `$header_title`, `$options`.

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
            @foreach ($datas as $row)
                <x-i-reports::tr>
                    @foreach ($columns as $column)
                        <x-i-reports::td :column="$column" :row="$row" />
                    @endforeach
                </x-i-reports::tr>
            @endforeach
        </x-i-reports::tbody>
        <x-i-reports::aggregates label="Total" />
    </x-i-reports::table>
    <x-i-reports::page-break />
</x-i-reports::layout>
```

- `x-i-reports::layout` outputs the HTML document for view, print and PDF, and only the slot for inline, Excel and CSV. It adds the PDF header/footer and calls `window.print()` when printing.
- `<x-i-reports::rows :rows="$datas" />` renders every row and visible column in one component. Prefer it for plain grids; it is several times faster and lighter than a `tr`/`td` loop on large exports. Use the `tr`/`td` loop only when cells need custom markup.
- `th` and `td` accept `:column`, or `name="field"` to look the column up. `td` also accepts `:row`, a slot, or `value` (escaped unless `:html="true"`). Hidden columns render nothing. Within one `tr`, a column already rendered is skipped, so you can render a few columns by hand first and then loop over all of them.
- Sortable headers become links (postMessage in the iframe, `wire:click` inline).
- `<x-i-reports::grouped-tbody :rows="$datas" />` renders group header rows and subtotals when `setGroupBy()` is set.
- Do not call `$datas->links()`. Keep Excel markup simple (no nested tables) when using `excel_mode` `view`.

## Embedding the Viewer

```blade
<livewire:i-reports.report-viewer report="users" />
<livewire:i-reports.report-viewer report="users" mode="inline" filter_extended_view="reports.users-extra-filters" />
```

- Toolbar: search, Reset, Filter dialog, sort select, saved presets (when enabled and signed in), export menu, page size; iframe or inline table; First / Prev / Next / Last / jump-to-page.
- Exports open in a new tab through a fresh token. With `queue.enabled` and `queue.threshold`, large exports are queued (`ExportReportJob`) and `ReportExportCompleted` is dispatched when the file is stored.
- To link straight to a report without the viewer, build the token yourself:

```php
use Rishadblack\IReports\Helpers\RequestHelper;

$url = route('i-reports.view', [
    'token' => (new RequestHelper(['report' => 'users', 'export' => 'pdf', 'filters' => ['district_id' => 5]]))->generateToken(),
]);
```

## Branding, Columns and Background Exports

- `branding` config (name, tagline, logo path, accent colour, show_filters, show_generated_by) drives the header and footer of print, PDF, Excel and CSV. Give component filters `->displayUsing(fn ($id) => Model::find($id)?->name)` so headers and chips show names instead of ids.
- Users show and hide columns from the viewer's Columns menu; the choice travels in the token, so all exports respect it. Use `->hiddenByDefault()` for optional columns and `->hideable(false)` for columns that must always show. The model key is selected automatically for non-grouped queries, so `$row->id` is available in `format()`.
- With `queue.enabled`, exports at or above `queue.thresholds[format]` rows run as `ExportReportJob`; the viewer tracks them in `i_reports_exports` (migrate first), polls, and offers a download when ready. Needs a queue worker; prune with `i-reports:prune-exports`.
- `queue.realtime` = `broadcast` (env `I_REPORTS_REALTIME`) pushes status changes with `ReportExportUpdated` on private channel `i-reports.exports.{userId}` (event name `.report-export.updated`) for Reverb/Pusher/Ably. Requires `php artisan install:broadcasting` and Echo on the page; without Echo or for guests the viewer polls automatically. Listen for `i-reports:export-ready` / `i-reports:export-failed` browser events to show toasts.

## Very Large Print and PDF

- Above `stream_threshold` rows (default 5000) print and PDF stream from the database in chunks and do not render the report view. PDF writes each `pdf_chunk_size` chunk (default 500) as its own table with its own `WriteHTML` call (laravel-mpdf `chunkLoadView` style); print flushes one continuous table to the browser. Types, styles, `hideIn()`, group headers, subtotals and totals are kept.
- Per report: `setStreamThreshold(0)` always streams; `setStreamThreshold(PHP_INT_MAX)` always uses the view.
- In a custom view, `<x-i-reports::chunk />` between complete blocks (never inside a `<table>`) splits the PDF HTML at that point so each `WriteHTML` call stays under `pcre.backtrack_limit`.
- mPDF needs roughly 3 seconds per 1000 rows; enable `queue.enabled` for 100k-row PDFs.

## PDF Notes

- mpdf renders the same view with `autoScriptToLang` and `autoLangToFont`, so Bangla and other complex scripts work. Temp files go to `storage/app/i-reports/mpdf`.
- mpdf supports a subset of CSS: prefer inline table styles, avoid flexbox, grid and `position: sticky`.
- Add mpdf options under `config('i-reports.mpdf')`; set `pdf_header_view` / `pdf_footer_view` or the per-report setters for page headers and footers.

## Testing with Pest

```php
use Livewire\Livewire;
use Rishadblack\IReports\Helpers\RequestHelper;

function reportUrl(array $params = []): string
{
    return route('i-reports.view', [
        'token' => (new RequestHelper($params + ['report' => 'users', 'per_page' => 50]))->generateToken(),
    ]);
}

it('filters users by district', function () {
    $this->actingAs(User::factory()->create());

    $this->get(reportUrl(['filters' => ['district_id' => 2]]))->assertOk()->assertSee('Karim')->assertDontSee('Rahim');
});

it('exports to csv', function () {
    $content = $this->get(reportUrl(['export' => 'csv']))->assertOk()->streamedContent();

    expect($content)->toContain('Name,Email');
});

it('counts the filtered rows in the viewer', function () {
    Livewire::test('i-reports.report-viewer', ['report' => 'users'])
        ->set('filters.district_id', '2')
        ->call('filterSubmit')
        ->assertSet('total', 1)
        ->call('sortBy', 'name')
        ->assertSet('sort_field', 'name');
});
```

- Generate the token as the same user that performs the request (tokens are user-bound).
- Excel content can be checked by storing through `app(ReportExporter::class)->store($report, 'xlsx', 'disk')` and loading the file with PhpSpreadsheet. PDF responses start with `%PDF`.
- Queue tests: `Queue::fake()` then `->call('queueExport', 'csv')` and `Queue::assertPushed(ExportReportJob::class)`.

## Common Pitfalls

- **403 "A report token is required" / "Invalid or expired":** the URL has no token, the token is older than `token_ttl`, or a different user created it.
- **404 Report not found:** `report_namespace`/`report_suffix` don't match where the class lives, or the name has the wrong case or separators. Use `sales.daily` for `Sales\DailyReport`.
- **"Column ... is ambiguous":** a filter, sort or `additionalQuery()` uses an unqualified column while relation columns add joins.
- **A relation column is empty:** the name segment is not a `BelongsTo`/`HasOne`/`MorphOne` method on the model.
- **Formatted HTML shows as text:** add `->html()` to the column.
- **Sorting a column does nothing:** it is not `->sortable()`.
- **Private data reachable:** `route_middleware` lacks `auth`, `authorize()` returns true, or `builder()` is not scoped. Tokens stop forged parameters; they are not an access check.
