---
name: i-reports-development
description: "Use this skill when building reports with rishadblack/i-reports. Trigger when creating or editing classes that extend Rishadblack\\IReports\\BaseReportController, defining report columns (Column::make) or filters (Filter::make), writing report Blade views with x-i-reports::* components, embedding <livewire:i-reports.report-viewer>, exporting reports to print, PDF, Excel or CSV, configuring config/i-reports.php, using Livewire dropdowns (e.g. wire-tomselect) as report filters, or testing reports with Pest."
license: MIT
metadata:
  author: rishadblack
---

# I-Reports Development

`rishadblack/i-reports` turns an Eloquent query into a report. You write one class (query, columns, filters) and one Blade view. The package provides:

- a Livewire viewer with search, filters, pagination and an export menu;
- a token-protected route that renders the view inside an iframe;
- print, PDF (mpdf), Excel and CSV (maatwebsite/excel) exports, all rendered from the same view.

## How It Fits Together

1. The page renders `<livewire:i-reports.report-viewer report="users" />`.
2. The viewer encrypts its state (report name, search, filters, page, per page, export type) into a token that expires after 10 minutes. It then loads `route('i-reports.view', ['token' => ...])` in an iframe.
3. The route resolves `users` to a report class. The class builds the query: joins for relation columns, then filters, search, sort, selects, `additionalQuery()`, and finally pagination or `get()`.
4. The class renders its Blade view. For `pdf`, `xlsx` and `csv` it returns a download instead. For `print` it renders every row and calls `window.print()`.

## Setup

- PHP 8.3+, Laravel 11–13, Livewire 3 or 4. The viewer markup uses Bootstrap 5, including a modal for filters, so Bootstrap's JavaScript must be loaded.
- Publish the config with `php artisan vendor:publish --tag=i-reports.config`. Set a namespace and suffix so reports sit in their own folder:

```php
// config/i-reports.php
'report_namespace' => 'Reports', // App\Livewire\Reports\...
'report_suffix' => 'Report',     // "users" resolves to UsersReport
'route_middleware' => ['web', 'auth'], // the default is no middleware at all
```

Other keys: `route_prefix` (default `ireport`, so the URL is `/ireport/view`), `default_pagination`, `default_pagination_list`, `pdf_paper_size`, `pdf_orientation`, `mpdf` (passed straight to the `Mpdf` constructor), `export_options` (the export menu), `default_style.th|td|tr` (inline CSS for cells), `header_view` (a view included at the top of every report), and `use_cache_token` (store tokens in Redis or Memcached instead of encrypting them).

## Name, Class and View Resolution

| Report name | Class | View |
|---|---|---|
| `users` | `App\Livewire\Reports\UsersReport` | `livewire.reports.users-report` |
| `sales.daily` | `App\Livewire\Reports\Sales\DailyReport` | `livewire.reports.sales.daily-report` |
| `billing::invoices` | `Modules\Billing\{modules-livewire.namespace}\Reports\InvoicesReport` | `billing::livewire.reports.invoices-report` |

- The class is `{livewire.class_namespace}\{report_namespace}\{Studly segments}{report_suffix}`. The view name is the class path from `Livewire` onward, converted to kebab-case. Put the view at `resources/views/livewire/reports/users-report.blade.php`.
- An unknown name throws an exception (HTTP 500). Report names may contain only letters, digits, `.` and `-`.

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

    /**
     * @return array<int, Column>
     */
    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->searchable(),
            Column::make('Email', 'email')->searchable(),
            Column::make('District', 'district.name'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(): array
    {
        return [
            Filter::make('Status', 'status')
                ->select(['active' => 'Active', 'blocked' => 'Blocked'])
                ->filter(fn (Builder $query, string $status) => $query->where('users.status', $status)),
        ];
    }
}
```

`configure()` runs in the constructor. Use it only for settings:

| Setter | Default | Purpose |
|---|---|---|
| `setReportTitle(string)` | class name as words | Page title and PDF footer text. |
| `setHeaderTitle(string)` | `config('app.name')` | Organisation name in the PDF footer. |
| `setDefaultSort(string $field, string $dir = 'asc')` | none | Sort used when the request has no sort. Use a qualified column (`users.name`). |
| `setPagination(int)` / `setPaginationList(array)` | config | Default and selectable page sizes. |
| `setPaperSize(string)` / `setOrientation('landscape'\|'portrait')` | config | PDF page setup. |
| `setFileName(string)` / `setFileTitle(string)` | `users-report-ymd-His` | Download file name and PDF title. |
| `setSearchField(array\|string)` | `[]` | Extra searchable fields besides the `->searchable()` columns. `relation.field` is searched with `whereHas`. |
| `setAdditionalSelects(array\|string)` | `[]` | Extra `addSelect` expressions, e.g. aggregates read in `map()`. |

Optional hooks you can override:

| Method | When it runs |
|---|---|
| `additionalQuery(Builder $builder): Builder` | After selects, before pagination. Use it for `withCount`, `groupBy`, etc. |
| `search(Builder $builder, string $search): Builder` | After the default `LIKE` search on searchable fields, only when a term is present. |
| `map(Collection $collection): Collection` | On the fetched rows (`Illuminate\Database\Eloquent\Collection`), for computed values. |
| `summaries(Builder $builder): array` | Receives the filtered and searched query. The result is `$summaries` in the view (totals). |
| `additionalData(): array` | Extra variables, available as `$additional_datas`. |

## Columns

`Column::make(string $title, string $name)`. The title comes first.

- `'field'` is a column on the base table.
- `'relation.field'` (or `'a.b.field'`) follows `BelongsTo`, `HasOne` or `MorphOne` relations with LEFT JOINs aliased by the relation name. The value is selected as `relation.field`. The names must be relation methods on the model.
- `->searchable()` includes the column in the search box.
- `->hide()` skips the column in selects and rendering.
- `->custom()` skips it in selects only. Render it yourself, e.g. with a `format()` that reads `$row`.
- `->format(fn ($value, $row, Column $column) => ...)` transforms the value. **The result is printed unescaped**, so wrap user data in `e()`.
- `->style('color: red;')` or `->style(fn ($row) => $row->overdue ? 'color: red;' : '')` adds inline CSS, merged over `default_style.td`.
- `->sortable()` only stores a flag. The viewer has no column-sorting UI.

Because relation columns add joins, **always qualify base-table columns** in filters, sorts and `additionalQuery()` (`users.country_id`, not `country_id`). Otherwise MySQL throws "ambiguous column" errors.

## Filters

`Filter::make(string $title, string $key)`. The title comes first. Each filter is bound to `filters.{key}` in the viewer's filter modal. The `->filter()` callback runs only when the value is filled, and the value arrives as a string or array.

```php
Filter::make('Name contains', 'name')->text()->placeholder('Type a name')
    ->filter(fn (Builder $query, string $name) => $query->where('users.name', 'like', "%{$name}%")),

Filter::make('Status', 'status')->select(['active' => 'Active'])
    ->filter(fn (Builder $query, string $status) => $query->where('users.status', $status)),

// Any Livewire component that is bound with wire:model and accepts name / label / placeholder,
// e.g. a rishadblack/wire-tomselect SearchComponent:
Filter::make('District', 'district_id')->component('selects.district-select')
    ->filter(fn (Builder $query, string $districtId) => $query->where('users.district_id', $districtId)),

// Anonymous or class Blade component; receives wire:model, name, label, placeholder, options, params
Filter::make('Joined', 'joined')->bladeComponent('forms.date-range', ['options' => []]),
```

- Use `->customClass('col-md-6')` to set the wrapper's width class.
- Inside the report, `$this->getFilter('key')` returns the current value, or `false` if it is not set.
- `component()` parameters are fixed when the viewer mounts, so filter dropdowns cannot depend on each other. Make each one work on its own, for example with `->when($this->parent_id, ...)` inside `builder()`.

## The Report View

Variables: `$datas` (a `LengthAwarePaginator` when viewing, a full `Collection` when exporting), `$columns`, `$summaries`, `$additional_datas`, `$export` (`view`, `print`, `pdf`, `xlsx` or `csv`), and `$options['header_view']`. The `x-i-reports::*` components also receive `$report_title` and `$header_title`.

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
        <x-i-reports::tfoot>
            <x-i-reports::tr>
                <x-i-reports::td style="font-weight: bold;">Total: {{ $summaries['total'] ?? '' }}</x-i-reports::td>
            </x-i-reports::tr>
        </x-i-reports::tfoot>
    </x-i-reports::table>
</x-i-reports::layout>
```

- `x-i-reports::layout` outputs the HTML document for view, print and PDF, and only the slot for Excel and CSV. It adds a PDF footer with page numbers and calls `window.print()` when printing.
- `th` and `td` accept `:column`, or `name="field"` to look the column up. `td` also accepts `:row`, a slot, or `value`. Hidden columns render nothing. Within one `tr`, a column already rendered is skipped. That lets you render a few columns by hand first and then loop over all of them.
- Do not call `$datas->links()`. The viewer handles pagination outside the iframe.
- Excel and CSV are built from the same HTML table, so keep the markup simple: no nested tables, and images are ignored.

## Embedding the Viewer

```blade
<livewire:i-reports.report-viewer report="users" />
```

- The viewer shows a search box, Reset, a filter modal, the export menu, the per-page select, the iframe, and First / Prev / Next / Last / jump-to-page controls.
- Exports open in a new tab through a fresh token.
- To link straight to a report without the viewer, build the token yourself:

```php
use Rishadblack\IReports\Helpers\RequestHelper;

$url = route('i-reports.view', [
    'token' => (new RequestHelper(['report' => 'users', 'export' => 'pdf', 'filters' => ['district_id' => 5]]))->generateToken(),
]);
```

## PDF Notes

- mpdf renders the same view. The defaults include `autoScriptToLang` and `autoLangToFont`, so Bangla and other complex scripts work. Its temp files go to `storage/app/i-reports/mpdf`.
- mpdf supports only a subset of CSS. Prefer inline table styles, and avoid flexbox, grid and `position: sticky` in content meant for PDF.
- Add mpdf options (margins, `default_font`, `fontDir`, ...) under `config('i-reports.mpdf')`.

## Testing with Pest

Test the report through its route with a generated token, and the viewer with `Livewire::test()`:

```php
use App\Models\User;
use Livewire\Livewire;
use Rishadblack\IReports\Helpers\RequestHelper;

function reportUrl(array $params = []): string
{
    return route('i-reports.view', [
        'token' => (new RequestHelper(['report' => 'users', 'per_page' => 50] + $params))->generateToken(),
    ]);
}

it('filters users by district', function () {
    User::factory()->create(['name' => 'Rahim', 'district_id' => 1]);
    User::factory()->create(['name' => 'Karim', 'district_id' => 2]);

    $this->get(reportUrl(['filters' => ['district_id' => 2]]))
        ->assertOk()
        ->assertSee('Karim')
        ->assertDontSee('Rahim');
});

it('exports to excel', function () {
    $response = $this->get(reportUrl(['export' => 'xlsx']))->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});

it('counts the filtered rows in the viewer', function () {
    Livewire::test('i-reports.report-viewer', ['report' => 'users'])
        ->set('filters.district_id', '2')
        ->call('filterSubmit')
        ->assertSet('total', 1);
});
```

- PDF responses can be checked with `->headers->get('content-type') === 'application/pdf'` and content that starts with `%PDF`.
- Report state is kept in static helpers (`ReportHelper`) for the current request. Every test request sets it again, so tests stay independent.

## Common Pitfalls

- **`Report class not found: App\Livewire\...`:** `report_namespace`/`report_suffix` don't match where the class lives, or the report name has the wrong case or separators. Use `sales.daily` for `Sales\DailyReport`.
- **`View [livewire.reports....] not found`:** the view path must mirror the class path in kebab-case.
- **"Column ... is ambiguous":** a filter, sort or `additionalQuery()` uses an unqualified column while relation columns add joins.
- **A relation column is empty:** the name segment is not a `BelongsTo`/`HasOne`/`MorphOne` method on the model.
- **The filter modal does not open:** Bootstrap's JavaScript is not loaded on the page.
- **Private data is reachable through the iframe URL:** `route_middleware` is empty, or `builder()` is not scoped. Tokens stop users from forging parameters, but they are not an access check.
