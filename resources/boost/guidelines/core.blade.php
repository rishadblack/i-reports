## I-Reports

- `rishadblack/i-reports` builds paginated, filterable reports with print, PDF (mpdf), Excel and CSV export from one Blade view. Activate the `i-reports-development` skill whenever you create, edit, or test a report.
- A report is a class that extends `Rishadblack\IReports\BaseReportController` and implements `builder(): Illuminate\Database\Eloquent\Builder`, `configure(): void` and `columns(): array`. Do not write controllers, routes, or export classes for reports; the package's `i-reports.view` route and `<livewire:i-reports.report-viewer report="..." />` handle viewing and exporting.
- Report names resolve to classes as `{livewire.class_namespace}\{i-reports.report_namespace}\{StudlyName}{i-reports.report_suffix}` (this app: `users` → `App\Livewire\Reports\UsersReport`). The view is derived from the class: `App\Livewire\Reports\UsersReport` → `resources/views/livewire/reports/users-report.blade.php`.
- Report views use the `x-i-reports::*` components (`layout`, `table`, `thead`, `tbody`, `tr`, `th`, `td`) and loop over `$columns` and `$datas`. Do not call `$datas->links()`; the viewer paginates.
- Define columns with `Rishadblack\IReports\Views\Column::make('Title', 'field')`, using `relation.field` for `BelongsTo`/`HasOne` relations, and filters with `Rishadblack\IReports\Views\Filter::make('Title', 'key')->filter(fn (Builder $query, $value) => ...)`. Qualify filter columns with the table name, because relation columns add joins.
- `Column::format()` output is printed unescaped. Wrap user data in `e()`.
- `builder()` is the authorization boundary. The report route has no middleware by default, so set `i-reports.route_middleware` (for example `['web', 'auth']`) for private data.
