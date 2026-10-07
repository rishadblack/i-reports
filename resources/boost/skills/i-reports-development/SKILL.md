---
name: i-reports-development
description: "Use this skill when building reports with rishadblack/i-reports. Trigger when creating or editing classes that extend Rishadblack\\IReports\\BaseReportController, defining report columns (Column::make) or filters (Filter::make), writing report Blade views with x-i-reports::* components, embedding <livewire:i-reports.report-viewer>, exporting reports to print, PDF, Excel or CSV, page setup (paper, orientation, font size, scale), splitting large prints, branding report headers and footers, hiding or showing columns, queueing exports with polling or Reverb broadcasting, saved views (presets), configuring config/i-reports.php, using Livewire dropdowns (e.g. wire-tomselect) as report filters, or testing reports with Pest."
license: MIT
metadata:
  author: rishadblack
---

# I-Reports Development

`rishadblack/i-reports` turns an Eloquent query into a report. You write **one class** (query, columns, filters) and **one Blade view**. The package provides everything else:

- a Bootstrap 5.3 Livewire viewer: search, filter dialog with chips, sorting, pagination, a column picker, saved views, an export menu and a background exports panel;
- a token-protected route that renders the report in an iframe (or inline);
- print, PDF (mPDF), Excel and CSV exports with a branded header and footer, streamed from the query;
- a page setup dialog for print and PDF (paper, orientation, font size, scale);
- large prints split into parts of 1,000 rows, so the browser never hangs;
- background exports on the queue, with progress by polling or Reverb/Pusher broadcasting.

## Rules That Matter Most

1. **Never write controllers, routes, export classes or JavaScript for a report.** One report class plus one Blade view, shown with `<livewire:i-reports.report-viewer report="..." />`.
2. **`builder()` and `authorize()` are the security boundary.** Scope `builder()` to the current user or tenant on shared tables. Add `auth` to `i-reports.route_middleware` for private data. Tokens stop tampering with parameters, but they are not an access check.
3. **Qualify every base-table column** (`users.country_id`, not `country_id`) in filters, `setDefaultSort()`, `additionalQuery()` and `search()`. Relation columns add joins, so unqualified names become ambiguous.
4. **Cell output is escaped.** Use `->html()` only when `format()` returns trusted markup, and wrap user data in `e()` inside it.
5. **Columns the user hides are removed from the SQL `SELECT`.** If `map()`, another column's `format()` or a custom view reads a field, mark that column `->alwaysSelect()`, or add the field with `setAdditionalSelects()`.
6. Put all settings in `configure()`. It runs in the constructor, on every request.

## Quick Start

```bash
php artisan make:report users                       # class + Blade view + Pest test
php artisan make:report sales.daily --model=App\\Models\\Sale
php artisan i-reports:list                          # registered reports
```

```blade
{{-- any page that has Bootstrap 5.3 CSS --}}
<livewire:i-reports.report-viewer report="users" />
```

That page now has search, filters, sorting, paging, a column picker and exports. No route or controller is needed.

## How It Fits Together

1. The viewer keeps its state (report, search, filters, sort, page, page size, hidden columns) in Livewire, and the URL keeps it across reloads.
2. To show or export, it encrypts that state into a **token**. The token is user-bound and expires after `token_ttl` minutes (default 10). The viewer then loads `route('i-reports.view', ['token' => ...])`.
3. The route resolves the report name to a class and checks `authorize()`. It then builds the query: relation joins, the selected columns only, filters, search, sort and `additionalQuery()`.
4. Viewing renders one page. Exports read the whole query in chunks:

| Export | What happens |
|---|---|
| `view` / `inline` | One page of rows inside the viewer. |
| `print` | Up to `print.split_after` rows (500): one page that calls `window.print()`. Above that: parts of `print.rows_per_part` (1,000) rows with a Previous / Next toolbar. |
| `pdf` | mPDF download. Above `stream_threshold` (5,000) rows the PDF is written in chunks. Above `queue.thresholds.pdf` (1,000) rows it is queued when the queue is enabled. |
| `xlsx` | Styled workbook streamed from the query (`excel_mode` `query`) or converted from the Blade table (`view`). |
| `csv` | Streamed with a BOM and title rows, with formulas neutralised. |

## Report Name → Class → View

| Report name | Class | View |
|---|---|---|
| `users` | `App\Livewire\Reports\UsersReport` | `livewire.reports.users-report` |
| `sales.daily` | `App\Livewire\Reports\Sales\DailyReport` | `livewire.reports.sales.daily-report` |
| `billing::invoices` | `Modules\Billing\Livewire\Reports\InvoicesReport` | `billing::livewire.reports.invoices-report` |

- The class is `{livewire.class_namespace}\{i-reports.report_namespace}\{StudlyName}{i-reports.report_suffix}`. Names may contain letters, digits, `.` and `-`; an unknown name returns 404.
- To map a name explicitly, use `IReports::register('alias', SomeReport::class)` or the `reports` config array.

## The Report Class

```php
<?php

namespace App\Livewire\Reports;

use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Views\Column;
use Rishadblack\IReports\Views\Filter;

class UsersReport extends BaseReportController
{
    public function builder(): Builder
    {
        return User::query(); // ->where('users.team_id', auth()->user()->team_id) on shared data
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
            Column::make('ID', 'id')->sortable()->align('right')->hideIn('pdf'),
            Column::make('Name', 'name')->searchable()->sortable()->hideable(false),
            Column::make('Email', 'email')->searchable()->sortable(),
            Column::make('District', 'district.name')->sortable(),
            Column::make('Balance', 'balance')->money('BDT')->sum()->sortable(),
            Column::make('Joined', 'created_at')->datetime('d M Y')->sortable(),
            Column::make('Notes', 'notes')->hiddenByDefault(),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(): array
    {
        return [
            Filter::make('Country', 'country_id')
                ->component('selects.country-select')
                ->column('users.country_id')
                ->displayUsing(fn (int|string $id) => Country::find($id)?->name),
            Filter::make('Status', 'status')->select(['active' => 'Active', 'blocked' => 'Blocked'])->column('users.status'),
            Filter::make('Joined', 'joined')->dateRange()->column('users.created_at'),
        ];
    }
}
```

### Settings for `configure()`

| Setter | Default | Purpose |
|---|---|---|
| `setReportTitle(string)` | class name as words | Viewer title, export headers, sheet name. |
| `setHeaderTitle(string)` | `config('app.name')` | Organisation name when `branding.name` is not set. |
| `setDefaultSort(string $field, string $dir = 'asc')` | none | Sort used before the user picks one. Qualify the column. |
| `setPagination(int)` / `setPaginationList(array)` | config | Default and selectable page sizes. |
| `setPaperSize('A4')` / `setOrientation('landscape')` | `pdf_paper_size` / `pdf_orientation` | Default print/PDF paper and orientation. |
| `setFontSize(float $pt)` / `setScale(int $percent)` | `page_setup.font_size` (null = keep `default_style` sizes) / `page_setup.scale` | Default table font size and scale for print/PDF. |
| `setFileName(string)` / `setFileTitle(string)` | `users-report-Ymd-His` | Download file name and PDF title. |
| `setSearchField(array\|string)` | `[]` | Extra searched fields. `relation.field` uses `whereHas`; `col->key` searches JSON. |
| `setAdditionalSelects(array\|string)` | `[]` | Extra `addSelect` expressions, e.g. values read in `map()`. |
| `setExportSource('columns'\|'view')` | `export_source` (`columns`) | `view` makes every output render the report's own Blade view, as in 0.1.x: Excel and CSV convert the view, PDF never streams, print never splits. Use it when the view computes running balances, totals or extra rows. |
| `setExcelMode('query'\|'view')` | `excel_mode` | Stream the query (fast, typed, real dates) or convert the Blade table (custom layout). |
| `setPrintSplitAfter(int)` | `print.split_after` (0 for view-based reports) | Rows above which print opens in parts (0 means never). |
| `setGroupBy(string $columnName)` | none | Group rows with headers and subtotals. |
| `setStreamThreshold(int)` | `stream_threshold` | Rows above which print/PDF stream in chunks (0 means always). |
| `setHeaderView()` / `setPdfHeaderView()` / `setPdfFooterView()` | config | Replace the built-in branded header, or the PDF page header and footer. |

### Hooks You May Override

| Method | Use it for |
|---|---|
| `authorize(): bool` | Access control. Returning `false` gives 403 for viewing and every export. |
| `additionalQuery(Builder $builder): Builder` | `withCount`, extra `where`, `groupBy`. Runs after selects. |
| `search(Builder $builder, string $search): Builder` | Custom search logic. Runs only when a term is present. |
| `map(Collection $rows): Collection` | Computed values per page and per export chunk. |
| `summaries(Builder $builder): array` | Extra figures from the filtered query, available as `$summaries` in the view. |
| `additionalData(): array` | Extra view variables, available as `$additional_datas`. |

## Columns

`Column::make(string $title, string $name)`. The title comes first.

- `'field'` is a base-table column. `'relation.field'` (or `'a.b.field'`) follows `BelongsTo`, `HasOne` or `MorphOne` relation methods using LEFT JOINs.
- `->searchable()` adds the column to the search box (a `custom()` column is searched by its name: `field` on the base table or `relation.field` through `whereHas`). `->sortable()` allows sorting it; other sort fields are rejected server side.

### Types (optional)

A type formats both display and export. Without a type the value prints as-is, escaped and left-aligned. Only give a column a type when it really holds that kind of data.

| Type | Display | Excel | CSV |
|---|---|---|---|
| none | value as-is | numbers stay numbers; codes with leading zeros stay text | as-is |
| `number($decimals = 2)` | `1,234.57`, right-aligned | real number with a `#,##0.00` format | rounded number |
| `money('BDT', 2, 'before'\|'after')` | `BDT 1,234.50` | real number | number |
| `date('d M Y')` / `datetime('d M Y H:i')` | formatted | **real Excel date** in the same format, centred | formatted text |
| `boolean('Yes', 'No')` | label | label | label |
| `badge(['paid' => ['Paid', 'badge bg-success']])` | Bootstrap badge | label | label |
| `link(fn ($value, $row) => url, '_blank')` | link | URL | URL |
| `image($width, fn ($value) => src)` | `<img>` | path | path |

- Do **not** use `number()` on identifiers: IDs, phone numbers, NIDs, postcodes, invoice codes. It adds thousands separators (`6,411`) and can drop leading zeros. Use `->align('right')` instead.
- For number, money, date, datetime and boolean columns, `format()` changes only the display. Exports keep the typed value. Use `exportFormat()` to change the export value. Adding `format()` or `exportFormat()` to a date column turns off the real Excel date.

### Formatting and Style

- `->format(fn ($value, $row, Column $column) => ...)` sets the display value (escaped). `->html()` prints it raw; returning an `HtmlString` does the same.
- `->exportFormat(fn ($value, $row) => ...)` sets the CSV/Excel value. HTML is stripped from exports.
- `->align('left'|'center'|'right')`, `->width('10%')`, `->style('color: red;')` or `->style(fn ($row) => ...)`. These merge over `default_style.td` for view, print and PDF. Excel takes only the alignment.
- `->sum()`, `->avg()`, `->count()`, `->min()`, `->max()` compute over **all filtered rows**, not just the page. They show as a total row in every output and as group subtotals.

### Visibility

| Method | Effect |
|---|---|
| `->hideIn('pdf\|xlsx\|csv\|print\|view\|inline')` | Hidden in those outputs only. |
| `->hide()` | Never shown and never selected. Load data you only need in `map()` with `setAdditionalSelects()`. |
| `->hiddenByDefault()` | Starts unticked in the viewer's Columns menu. |
| `->hideable(false)` | Always shown; cannot be unticked. |
| `->alwaysSelect()` | Stays in the SQL query when hidden, because something else reads it. |
| `->custom()` | Not selected from the database. Render it with `format()` reading `$row`. |

Users tick columns on and off in the viewer. The choice travels in the token, so print, PDF, Excel, CSV, queued exports and saved views all respect it. Hidden columns are dropped from the SQL `SELECT`, unless one is searched, actively sorted, used by `setGroupBy()`, or marked `alwaysSelect()`. The model's primary key is always selected for non-grouped queries, so `$row->id` works in `format()`.

## Filters

`Filter::make(string $title, string $key)` binds to `filters.{key}` in the viewer's filter dialog. Values are sanitised by type, and invalid values are ignored.

```php
Filter::make('Name contains', 'name')->text()->column('users.name'),                       // LIKE %value%
Filter::make('Status', 'status')->select(['active' => 'Active'])->column('users.status'),    // =
Filter::make('Roles', 'roles')->multiSelect(['admin' => 'Admin'])->column('users.role'),     // IN
Filter::make('Day', 'day')->date()->column('users.created_at'),                              // whereDate =
Filter::make('Joined', 'joined')->dateRange()->column('users.created_at'),                   // ['from' => 'Y-m-d', 'to' => 'Y-m-d']
Filter::make('Age', 'age')->number()->column('users.age'),
Filter::make('Balance', 'balance')->numberRange()->column('users.balance'),                  // ['from' => .., 'to' => ..]
Filter::make('Active', 'active')->boolean('Yes', 'No')->column('users.active'),              // '1' or '0'
Filter::make('Status', 'status')->select([...])->filter(fn (Builder $query, string $value) => $query->where('users.status', $value)),

// Any Livewire component bound with wire:model, e.g. a rishadblack/wire-tomselect SearchComponent:
Filter::make('Country', 'country_id')->component('selects.country-select')->column('users.country_id')
    ->displayUsing(fn (int|string $id) => Country::find($id)?->name),
// Dependent dropdown: re-mounted with ['country_id' => value] and cleared when the parent changes.
// The child component declares #[Reactive] public $country_id.
Filter::make('District', 'district_id')->component('selects.district-select')->dependsOn('country_id')->column('users.district_id'),

Filter::make('Period', 'period')->bladeComponent('forms.period-picker', ['presets' => true]),
```

- `->column('table.col')` applies the default constraint for the type. `->filter(fn (Builder $query, $value) => ...)` replaces it and receives the sanitised value.
- `->displayUsing(fn ($value) => 'Label')` turns stored ids into names for the filter chips and export headers. Use it on every `component()` or id-based filter.
- `->default($value)`, `->placeholder('…')`, `->customClass('col-md-6')`, `->options([...])`.
- **When a filter applies:** by default the report changes only when the user clicks **Apply filters** (plain `wire:model`). The dialog edits a draft (`filters`), and Apply copies it to `applied_filters`, which the report, URL, chips, exports and saved views use. Cancel discards unapplied edits. Per filter: `->live(300)` (applies while typing, debounced in ms), `->onChange()` (applies as soon as the value changes), `->onBlur()` (applies when the field is left) or `->deferred()`. The app-wide default is `filter_update` (`defer`, `live`, `change`, `blur`), with `filter_debounce` (500 ms). The package writes the right `wire:model` modifier for Livewire 3 or 4. Blade filter components receive it as a `wire:model…` attribute; read it with `$attributes->wire('model')` or `$attributes->whereStartsWith('wire:model')`. Changing a filter returns to page 1.
- Inside the report, `$this->getFilter('key')` returns the current value, or `false` when it is not set.
- Applied filters show as removable chips in the viewer and appear in every export header.

## The Report View

Available variables:

- `$datas`: a paginator when viewing, a collection when exporting.
- `$columns`: the columns visible in this output; `$all_columns`: every column.
- `$aggregates`, `$summaries`, `$additional_datas`, `$group_by`, `$branding`.
- `$export`: `view`, `inline`, `print`, `pdf`, `xlsx` or `csv`.
- `$report`, `$report_title`, `$header_title`.

The plain grid is the fastest option, and `make:report` generates it:

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
        <x-i-reports::aggregates label="Total" />
    </x-i-reports::table>
</x-i-reports::layout>
```

- `x-i-reports::layout` builds the document. It adds the branded header for print and PDF, the PDF page header and footer, print CSS, the split-print toolbar and `window.print()`. For inline, Excel and CSV it outputs only the slot.
- `<x-i-reports::rows :rows="$datas" />` renders every row and visible column. It is several times faster than a `tr`/`td` loop. Use a loop (`<x-i-reports::tr>` + `<x-i-reports::td :column="$column" :row="$row" />`) only when cells need custom markup.
- `th`/`td` accept `:column`, or `name="field"`. `td` also accepts `:row`, a slot, or `value` (escaped unless `:html="true"`). Hidden columns render nothing. A column already rendered by hand in the same `tr` is skipped by a later loop.
- `<x-i-reports::grouped-tbody :rows="$datas" />` renders group headers and subtotals when `setGroupBy()` is set.
- `<x-i-reports::chunk />` between complete blocks (never inside a `<table>`) splits PDF HTML so each mPDF `WriteHTML` call stays small. `<x-i-reports::page-break />` forces a new PDF/print page.
- Never call `$datas->links()`; the viewer paginates. Keep markup simple (no nested tables) for `excel_mode` `view`. mPDF supports only a subset of CSS: use inline table styles, and no flexbox, grid or sticky positioning.

## The Viewer

```blade
<livewire:i-reports.report-viewer report="users" />
<livewire:i-reports.report-viewer report="users" mode="inline" filter_extended_view="reports.users-extra-filters" />
```

- **Header:** title, record count, Saved views (when `presets.enabled` and signed in), and the Export menu.
- **Toolbar:** search, Filters (a dialog; active filters show as chips with ×), Columns (a show/hide picker), sort field and direction, rows per page, Reset.
- **Body and footer:** the iframe (or inline) table, then First / Prev / page numbers / Next / Last / jump to page.
- **Export menu:**
  - **Print and PDF** open the page setup dialog (paper, orientation, font size, scale) when `page_setup.enabled`.
  - **Excel and CSV** export straight away.
  - Large exports go to the background exports panel when `queue.enabled`.
- **Browser events** for toasts and other UI:
  - `exportEvent` (`url`): an export opened in a new tab.
  - `exportQueued` (`format`): an export was queued.
  - `i-reports:export-ready` (`id`, `format`, `url`) and `i-reports:export-failed`.
  - `i-reports:filters-applied`.
- **Element ids** for browser tests: `#i-reports-search`, `#i-reports-search-button`, `#i-reports-export-button`, `[data-export="pdf"]`, `#i-reports-filter-dialog`, `#i-reports-page-setup`, `[data-orientation="landscape"]`, `#i-reports-setup-paper`, `#i-reports-setup-font`, `#i-reports-setup-scale`, `#i-reports-setup-submit`, `[data-column="email"]`.

To link straight to a report without the viewer, build the token yourself, as the signed-in user:

```php
use Rishadblack\IReports\Helpers\RequestHelper;

$url = route('i-reports.view', ['token' => (new RequestHelper([
    'report' => 'users',
    'export' => 'pdf',                                  // view, print, pdf, xlsx, csv
    'filters' => ['district_id' => 5],
    'hidden_columns' => ['email'],
    'page_setup' => ['orientation' => 'landscape', 'font_size' => 8, 'scale' => 90],
]))->generateToken()]);
```

## Reports Whose Blade View Computes Values

Ledgers, cash books, trial balances and similar reports often compute values in the view: debit and credit from one amount column, running balances, nested account rows, or totals in `<tfoot>`. Column-based exports can't see those values. For such reports:

- Call `$this->setExportSource('view')` in `configure()`, or set `'export_source' => 'view'` in config for the whole app. Every output then renders the view with **all** rows, just like the screen.
- Custom header views (`setHeaderView()`) appear in print, PDF and Excel. `<x-i-reports::table type="header">` is hidden on screen and shown in exports.
- Hard-coded `colspan`s assume every column is visible. Set `'columns_hideable' => false` (or `->hideable(false)` per column), or compute colspans from `count($columns)`.
- Keep the column-based default for plain grids; it streams huge exports and writes typed Excel cells.

## Print and PDF Page Setup

- Defaults come from `pdf_paper_size`, `pdf_orientation` and `page_setup.font_size` / `page_setup.scale`, or from the report's `setPaperSize()`, `setOrientation()`, `setFontSize()` and `setScale()`.
- Users change them in the dialog. Only values in `page_setup.papers`, `font_sizes` and `scales` (plus the report's own defaults) are accepted; anything else falls back to the default.
- **Font size** sets the table text (`default_style` `th`, `td`, group and total rows). Header and footer text keep their size.
- **Scale** resizes everything. Print uses CSS `zoom`; PDF multiplies CSS sizes (font sizes, padding, heights) because mPDF has no zoom.
- Set `page_setup.enabled` to `false` to export Print and PDF directly with the defaults. Excel and CSV are never affected.

## Large Prints Open in Parts

- Above `print.split_after` rows (500), print shows `print.rows_per_part` rows (1,000) per page. A toolbar (screen only, never printed) offers « ‹ Previous 1,000 · a part selector · Next 1,000 › » and **Print this part**. The URL gets `&part=N`, and the token is reused until it expires.
- Only the first open auto-prints. The header's Records box shows the range ("1,001–2,000 of 11,001"). The grand total appears only on the last part.
- Set `split_after` to `0` to go back to one long page, streamed in chunks above `stream_threshold`. PDF, Excel and CSV always contain every row.

## Branding: Headers and Footers on Every Export

`config('i-reports.branding')`: `name`, `tagline`, `address`, `contact`, `logo` (absolute path to PNG/JPG), `footer_note` (e.g. `'Confidential'`), `accent_color`, `show_filters`, `show_generated_by`, `date_format`.

| Output | Header | Footer |
|---|---|---|
| Print / PDF | Page 1: logo, name, tagline, address, contact, then **REPORT** and the title, an accent rule, and an info band (Generated, Prepared by, Records, Applied filters). Pages 2+: a slim running header (name · title · date). | name · title, `footer_note` (or "Generated … by …"), and Page X of Y. |
| Excel | Rows 1–4: name with the tagline, address and contact; title; the generated / prepared by / records line; applied filters. Logo top-left. Then a frozen heading row, autofilter, zebra rows, no gridlines, a totals row, real dates and number formats. | Excel print header from page 2, footer with Page &P of &N. |
| CSV | The same four lines as plain text (`csv.title_rows`; `false` for machine imports). | none |

If `branding.name` is not set, it falls back to `setHeaderTitle()`, then `app.name`. `header_view` / `setHeaderView()` replaces the built-in print/PDF header.

## Background Exports, Polling and Broadcasting

1. Set `queue.enabled = true`, run `php artisan migrate` (creates the `i_reports_exports` table) and keep a queue worker running.
2. Exports at or above `queue.thresholds[format]` rows run as `ExportReportJob`. Defaults: `pdf` 1000, `xlsx` 50000, `csv` 200000; `threshold` is the fallback. Print is never queued (it splits instead).
3. The viewer lists them (queued, processing, ready, failed), and the user downloads through `i-reports.exports.download`, which checks the owner.
4. Status updates:
   - **Poll mode (default):** the viewer checks every `queue.poll_seconds` seconds while an export is pending.
   - **Broadcast mode:** set `queue.realtime = 'broadcast'` (env `I_REPORTS_REALTIME`). Status changes are pushed as `ReportExportUpdated` on the private channel `i-reports.exports.{userId}`, event `.report-export.updated`, through Reverb, Pusher or Ably.
   - **Broadcast setup:** needs `php artisan install:broadcasting` and Laravel Echo on the page.
   - **Fallback:** guests, or pages without Echo, poll automatically, and a safety poll runs every `fallback_poll_seconds`.
5. Clean up with `php artisan i-reports:prune-exports --days=7` (schedule it). `ReportExportCompleted` fires when a file is stored.
6. From the CLI or scheduler: `php artisan i-reports:export users --format=xlsx --filter=status=active --user=1 --disk=local [--queue]`.

## Saved Views (Presets)

Set `presets.enabled = true` and run `php artisan migrate`. Signed-in users can then save and re-apply search, filters, sort, page size and hidden columns per report.

## Configuration Reference (`config/i-reports.php`)

| Area | Keys |
|---|---|
| Resolution | `report_namespace`, `report_suffix`, `reports` |
| Route and security | `route_prefix` (`ireport`), `route_middleware` (`['web']`), `route_throttle` (off by default; e.g. `'60,1'`), `token_ttl`, `token_bind_user`, `use_cache_token` |
| Viewer | `filter_update` (`defer` default/`live`/`change`/`blur`), `filter_debounce` (500), `viewer_mode` (`iframe`/`inline`), `default_pagination`, `default_pagination_list`, `max_per_page`, `show_search`, `show_reset_button`, `show_filter_button`, `show_export_button`, `show_pagination`, `export_options` |
| Look | `branding.*`, `default_style.th\|td\|tr\|zebra\|group\|aggregate`, `header_view` |
| Exports | `excel_mode`, `export_chunk_size`, `export_memory_limit`, `export_time_limit`, `csv.delimiter\|bom\|title_rows` |
| Export source | `export_source` (`columns`/`view`), `columns_hideable`, `eager_load_relations` (default true: relation columns also eager load their relation, so views can use `$row->relation`) |
| Print and PDF | `stream_threshold`, `print.split_after`, `print.rows_per_part`, `pdf_paper_size`, `pdf_orientation`, `page_setup.enabled\|font_size\|scale\|papers\|font_sizes\|scales`, `pdf_chunk_size`, `pdf_chunk_page_break`, `pdf_chunk_separator`, `pdf_header_view`, `pdf_footer_view`, `mpdf` (margins and other mPDF options) |
| Queue | `queue.enabled\|thresholds\|threshold\|connection\|queue\|disk\|path\|table\|keep_days\|poll_seconds\|realtime\|broadcast_channel\|fallback_poll_seconds` |
| Presets | `presets.enabled\|table` |

Publish tags: `i-reports.config`, `i-reports.views`, `i-reports.migrations`. The package loads its migrations only for enabled features.

## Recipes

- **Money column with a total:** `Column::make('Amount', 'amount')->money('BDT')->sum()`.
- **Status as a coloured badge:** `->badge(['paid' => ['Paid', 'badge bg-success'], 'due' => ['Due', 'badge bg-warning']])`.
- **Computed column:** `Column::make('Full name')->custom()->format(fn ($v, $row) => $row->first_name.' '.$row->last_name)`. Mark `first_name` and `last_name` with `->alwaysSelect()` if they are hideable, or add them with `setAdditionalSelects()`.
- **Edit link column:** `Column::make('Actions')->custom()->html()->format(fn ($v, $row) => new HtmlString('<a href="'.e(route('users.edit', $row)).'">Edit</a>'))->hideIn('pdf|xlsx|csv|print')`.
- **Count of a relation:** override `additionalQuery()` with `$builder->withCount('orders')`, then `Column::make('Orders', 'orders_count')->custom()->format(fn ($v, $row) => $row->orders_count)`.
- **Group by district with subtotals:** `setGroupBy('district_name')` (a column name), and `<x-i-reports::grouped-tbody :rows="$datas" />` in the view.
- **Wide report:** `setOrientation('landscape')` and `setFontSize(8)`. Users can still change both in the dialog.
- **Only some users may run it:** `authorize()` returns `auth()->user()?->can('view-reports') ?? false`.

## Testing with Pest

```php
use Livewire\Livewire;
use Rishadblack\IReports\Exports\ReportExporter;
use Rishadblack\IReports\Helpers\RequestHelper;

function reportUrl(array $params = []): string
{
    return route('i-reports.view', ['token' => (new RequestHelper($params + ['report' => 'users', 'per_page' => 50]))->generateToken()]);
}

it('filters users by district', function () {
    $this->actingAs(User::factory()->create());

    $this->get(reportUrl(['filters' => ['district_id' => 2]]))->assertOk()->assertSee('Karim')->assertDontSee('Rahim');
});

it('exports csv with headings', function () {
    $this->actingAs(User::factory()->create());

    expect($this->get(reportUrl(['export' => 'csv']))->assertOk()->streamedContent())->toContain('Name,Email');
});

it('sorts, filters and hides columns in the viewer', function () {
    Livewire::test('i-reports.report-viewer', ['report' => 'users'])
        ->set('filters.district_id', '2')
        ->call('filterSubmit')
        ->assertSet('total', 1)
        ->call('sortBy', 'name')
        ->assertSet('sort_field', 'name')
        ->call('toggleColumn', 'email')
        ->assertSet('hidden_columns', ['email']);
});

it('exports a landscape pdf', function () {
    Livewire::test('i-reports.report-viewer', ['report' => 'users'])
        ->call('exportAs', 'pdf', ['orientation' => 'landscape', 'font_size' => 8])
        ->assertDispatched('exportEvent');
});
```

- Create the token as the same user who makes the request (tokens are user-bound). A PDF response body starts with `%PDF`. Streamed print and CSV responses need `->streamedContent()`.
- **Excel:** store it with `app(ReportExporter::class)->store($report, 'xlsx', 'disk')` and load it with `PhpOffice\PhpSpreadsheet\IOFactory`. Title cells are rich text, so cast them: `(string) $sheet->getCell('A4')->getValue()`. Headings are on row 5 and data starts on row 6.
- **Split print:** set `config(['i-reports.print.split_after' => 2, 'i-reports.print.rows_per_part' => 2])`, then request `reportUrl(['export' => 'print']).'&part=2'`.
- **Queue:** call `Queue::fake()`, then `->call('queueExport', 'csv')`, then `Queue::assertPushed(ExportReportJob::class)`.

## Common Pitfalls

| Symptom | Cause and fix |
|---|---|
| 403 "A report token is required" / "Invalid or expired report token" | No token, older than `token_ttl`, or created by another user. |
| 404 "Report not found" | `report_namespace` / `report_suffix` don't match the class location, or the name is wrong (`sales.daily` maps to `Sales\DailyReport`). |
| "Column … is ambiguous" | An unqualified column in a filter, sort, search or `additionalQuery()`. Prefix it with the table name. |
| Relation column is empty | The segment is not a `BelongsTo`/`HasOne`/`MorphOne` method on the model. |
| A `format()` value is empty after the user hides a column | Hidden columns leave the SQL query. Add `->alwaysSelect()` to the column it reads. |
| HTML shows as text | Add `->html()`, or return an `HtmlString`. |
| IDs show as `6,411` | `number()` on an identifier. Use `->align('right')` instead. |
| Date is text in Excel | The column has `format()`/`exportFormat()`, or it is not `date()`/`datetime()`. |
| Sorting does nothing | The column is not `->sortable()`. |
| Filter chip shows an id | Add `->displayUsing(fn ($id) => Model::find($id)?->name)`. |
| Browser hangs printing thousands of rows | `print.split_after` is `0`. Keep it at 500 (view-based reports never split; their running totals need all rows). |
| Excel/CSV of a ledger misses Debit/Credit/Balance or shows 0 | The view computes them. Use `setExportSource('view')`. |
| Running balance restarts partway through a print | The print was split into parts. Use `setExportSource('view')` or `setPrintSplitAfter(0)`. |
| MySQL error 1055 on a grouped report export | Upgrade the package: chunked exports now order grouped queries by their group columns. |
| Huge PDF times out | Enable `queue.enabled`; PDFs over 1,000 rows then run in the background (mPDF takes about 3 s per 1,000 rows). |
| Background export stays "queued" | No queue worker is running, or `php artisan migrate` was not run after enabling the queue. |
| Live status never arrives | Echo is not on the page or broadcasting is not installed. The viewer still falls back to polling. |
| Private data reachable | `route_middleware` lacks `auth`, `authorize()` returns `true`, or `builder()` is not scoped. |
