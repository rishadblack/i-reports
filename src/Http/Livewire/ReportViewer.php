<?php

namespace Rishadblack\IReports\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Rishadblack\IReports\Events\ReportExportUpdated;
use Rishadblack\IReports\Jobs\ExportReportJob;
use Rishadblack\IReports\Models\QueuedExport;
use Rishadblack\IReports\Models\ReportPreset;
use Rishadblack\IReports\Support\PageSetup;
use Rishadblack\IReports\Support\ReportContext;
use Rishadblack\IReports\Traits\HasReportClass;
use Rishadblack\IReports\Traits\WithReportViewer;

class ReportViewer extends Component
{
    use HasReportClass, WithReportViewer;

    #[Locked]
    public string $report = '';

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $filter_list = [];

    #[Locked]
    public ?string $filter_extended_view = null;

    #[Locked]
    public string $mode = 'iframe';

    #[Locked]
    public bool $presets_enabled = false;

    #[Locked]
    public bool $exports_enabled = false;

    /** How the viewer learns about background export progress: poll or broadcast. */
    #[Locked]
    public string $realtime = 'poll';

    /** @var array<int, int> Ids of exports still pending at the last refresh. */
    #[Locked]
    public array $pending_export_ids = [];

    public string $preset_name = '';

    public string $export_message = '';

    public bool $show_filters = false;

    public function mount(string $report, ?string $mode = null, ?string $filter_extended_view = null): void
    {
        $this->report = $report;
        $this->mode = in_array($mode, ['iframe', 'inline'], true) ? $mode : (config('i-reports.viewer_mode') === 'inline' ? 'inline' : 'iframe');
        $this->filter_extended_view = $filter_extended_view;
        $this->presets_enabled = (bool) config('i-reports.presets.enabled') && Auth::check();

        $reportInstance = $this->getReportInstance();

        abort_unless($reportInstance->authorize(), 403);

        $this->per_page_list = $reportInstance->getPaginationList();
        $this->per_page = in_array((int) $this->per_page, $this->per_page_list, true) ? (int) $this->per_page : $reportInstance->getPagination();
        $this->filter_list = collect($reportInstance->getFilters())->map(fn ($filter) => $filter->toArray())->values()->all();

        foreach ($this->filter_list as $filter) {
            if ($filter['default'] !== null && ! array_key_exists($filter['name'], $this->filters)) {
                $this->filters[$filter['name']] = $filter['default'];
            }
        }

        $this->search = mb_substr(trim($this->search), 0, 255);
        $this->sort_direction = in_array($this->sort_direction, ['asc', 'desc'], true) ? $this->sort_direction : 'asc';

        if (count($this->hidden_columns) === 0) {
            $this->hidden_columns = $this->defaultHiddenColumns();
        } else {
            $this->updatedHiddenColumns();
        }

        $this->exports_enabled = (bool) config('i-reports.queue.enabled');
        $this->realtime = config('i-reports.queue.realtime') === 'broadcast' ? 'broadcast' : 'poll';
        $this->pending_export_ids = $this->pendingExportIds();
    }

    /**
     * In broadcast mode, signed-in users listen on their private channel for status changes.
     *
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        if ($this->realtime !== 'broadcast' || ! $this->exports_enabled || ! Auth::check()) {
            return [];
        }

        return ['echo-private:'.ReportExportUpdated::channelFor((string) Auth::id()).',.report-export.updated' => 'onExportUpdated'];
    }

    /**
     * A status change pushed over the socket.
     *
     * @param  array<string, mixed>  $payload
     */
    public function onExportUpdated(array $payload = []): void
    {
        if (($payload['report'] ?? $this->report) !== $this->report) {
            return;
        }

        $this->refreshExports();
    }

    /**
     * Whether the browser should fall back to polling in broadcast mode (guests cannot join
     * private channels; the page decides at runtime whether window.Echo exists).
     */
    public function canListenForBroadcasts(): bool
    {
        return $this->realtime === 'broadcast' && Auth::check();
    }

    /*
    |--------------------------------------------------------------------------
    | Search, filters, reset
    |--------------------------------------------------------------------------
    */

    public function searchReport(): void
    {
        $this->search = mb_substr(trim($this->search), 0, 255);
        $this->firstPage();
    }

    public function resetReport(): void
    {
        $this->reset(['filters', 'search', 'page', 'sort_field', 'sort_direction', 'export', 'export_message']);
        $this->per_page = $this->getReportInstance()->getPagination();
        $this->hidden_columns = $this->defaultHiddenColumns();
        $this->applyFilterDefaults();
        $this->updatePaginationInfo();
    }

    /*
    |--------------------------------------------------------------------------
    | Column picker
    |--------------------------------------------------------------------------
    */

    /**
     * Columns the user may show or hide, with their current state.
     *
     * @return array<int, array{name: string, title: string, visible: bool}>
     */
    #[Computed]
    public function hideableColumns(): array
    {
        return $this->getReportInstance()->getColumns()
            ->filter(fn ($column) => $column->isHideable())
            ->map(fn ($column) => [
                'name' => $column->getName(),
                'title' => $column->getTitle(),
                'visible' => ! in_array($column->getName(), $this->hidden_columns, true),
            ])
            ->values()
            ->all();
    }

    /**
     * Show or hide one column. The last visible column cannot be hidden.
     */
    public function toggleColumn(string $name): void
    {
        $hideable = array_column($this->hideableColumns(), 'name');

        if (! in_array($name, $hideable, true)) {
            return;
        }

        if (in_array($name, $this->hidden_columns, true)) {
            $this->hidden_columns = array_values(array_diff($this->hidden_columns, [$name]));
        } else {
            $visibleCount = $this->getReportInstance()->getColumns()->reject(fn ($column) => $column->isHidden())->count() - count($this->hidden_columns);

            if ($visibleCount <= 1) {
                return;
            }

            $this->hidden_columns[] = $name;
        }

        unset($this->hideableColumns);
    }

    public function showAllColumns(): void
    {
        $this->hidden_columns = [];
        unset($this->hideableColumns);
    }

    /**
     * Keep only real, hideable column names when the browser sends hidden columns.
     */
    public function updatedHiddenColumns(): void
    {
        $hideable = array_column($this->hideableColumns(), 'name');
        $this->hidden_columns = array_values(array_intersect(array_unique(array_filter($this->hidden_columns, 'is_string')), $hideable));
        unset($this->hideableColumns);
    }

    /**
     * @return array<int, string>
     */
    protected function defaultHiddenColumns(): array
    {
        return $this->getReportInstance()->getColumns()
            ->filter(fn ($column) => $column->isHiddenByDefault())
            ->map(fn ($column) => $column->getName())
            ->values()
            ->all();
    }

    /**
     * Remove one active filter (a chip's close button), and any filters that depend on it.
     */
    public function removeFilter(string $key): void
    {
        if ($key === '__search') {
            $this->search = '';
        } elseif ($key === '__sort') {
            $this->sort_field = null;
            $this->sort_direction = 'asc';
        } elseif (array_key_exists($key, $this->filters)) {
            unset($this->filters[$key]);
            $this->updatedFilters(null, $key);
        }

        $this->firstPage();
    }

    #[Computed]
    public function reportTitle(): string
    {
        return $this->getReportInstance()->getReportTitle();
    }

    /**
     * Active filters, search and sort as readable chips.
     *
     * @return array<int, array{key: string, label: string, value: string}>
     */
    #[Computed]
    public function activeFilters(): array
    {
        return $this->getReportInstance()->appliedFilters();
    }

    /**
     * Number of active filters (search and sort excluded), for the Filter button badge.
     */
    #[Computed]
    public function activeFilterCount(): int
    {
        return count($this->getReportInstance()->appliedFilters(false));
    }

    /**
     * Page numbers to show around the current page; null marks a gap.
     *
     * @return array<int, int|null>
     */
    #[Computed]
    public function pageWindow(): array
    {
        $this->syncTotals();
        $last = $this->last_page;
        $pages = array_unique(array_filter([1, $last, ...range(max(1, $this->page - 2), min($last, $this->page + 2))]));
        sort($pages);

        $window = [];
        $previous = 0;

        foreach ($pages as $number) {
            if ($number - $previous > 1) {
                $window[] = null;
            }

            $window[] = $number;
            $previous = $number;
        }

        return $window;
    }

    public function goTo(int $page): void
    {
        $this->syncTotals();
        $this->page = max(1, min($page, $this->last_page));
    }

    public function filterReset(): void
    {
        $this->reset(['filters']);
        $this->applyFilterDefaults();
        $this->firstPage();
    }

    public function filterSubmit(): void
    {
        $this->show_filters = false;
        $this->firstPage();
        $this->dispatch('i-reports:filters-applied');
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr(trim($this->search), 0, 255);
    }

    /**
     * Clear dependent filters when their parent changes.
     */
    public function updatedFilters(mixed $value, ?string $key = null): void
    {
        if ($key === null) {
            return;
        }

        $parentKey = explode('.', $key)[0];

        foreach ($this->filter_list as $filter) {
            if (($filter['depends_on'] ?? null) === $parentKey) {
                $this->filters[$filter['name']] = null;
            }
        }
    }

    protected function applyFilterDefaults(): void
    {
        foreach ($this->filter_list as $filter) {
            if ($filter['default'] !== null) {
                $this->filters[$filter['name']] = $filter['default'];
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Sorting
    |--------------------------------------------------------------------------
    */

    public function sortBy(string $field): void
    {
        $allowed = collect($this->sortableColumns())->pluck('name')->all();

        if (! in_array($field, $allowed, true)) {
            return;
        }

        if ($this->sort_field === $field) {
            $this->sort_direction = $this->sort_direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort_field = $field;
            $this->sort_direction = 'asc';
        }

        $this->firstPage();
    }

    public function clearSort(): void
    {
        $this->sort_field = null;
        $this->sort_direction = 'asc';
        $this->firstPage();
    }

    public function updatedSortDirection(): void
    {
        $this->sort_direction = in_array($this->sort_direction, ['asc', 'desc'], true) ? $this->sort_direction : 'asc';
    }

    public function updatedSortField(): void
    {
        $allowed = collect($this->sortableColumns())->pluck('name')->all();

        if ($this->sort_field !== null && ! in_array($this->sort_field, $allowed, true)) {
            $this->sort_field = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Export
    |--------------------------------------------------------------------------
    */

    public function updatedExport(string $format): void
    {
        $this->exportAs($format);
    }

    /**
     * Export in the given format. For print and PDF, $pageSetup carries the export dialog's
     * paper, orientation, font size and scale; anything not allowed falls back to the defaults.
     *
     * @param  array<string, mixed>  $pageSetup
     */
    public function exportAs(string $format, array $pageSetup = []): void
    {
        $format = strtolower($format);

        if (! in_array($format, ['print', 'pdf', 'xlsx', 'csv'], true)) {
            $this->export = '';

            return;
        }

        $this->syncTotals();

        if ($this->shouldQueue($format)) {
            $this->queueExport($format, $pageSetup);
            $this->export = '';

            return;
        }

        $token = $this->requestHelper()->setExport($format)->setTotal(null)->setPageSetup($this->resolvePageSetup($format, $pageSetup))->generateToken();
        $url = route('i-reports.view', ['token' => $token]);

        $this->js('window.open('.json_encode($url).', "_blank")');
        $this->dispatch('exportEvent', url: $url);
        $this->export = '';
    }

    /**
     * Generate the export in the background. With the exports table enabled, a tracking record
     * lets the viewer show its progress and a download button when it is ready.
     */
    /**
     * @param  array<string, mixed>  $pageSetup
     */
    public function queueExport(string $format, array $pageSetup = []): void
    {
        $format = strtolower($format);

        if (! in_array($format, ['pdf', 'xlsx', 'csv'], true)) {
            return;
        }

        $request = $this->requestHelper()->setExport($format)->setTotal(null)->setPageSetup($this->resolvePageSetup($format, $pageSetup))->toArray();
        $record = null;

        if ($this->exports_enabled) {
            $record = QueuedExport::query()->create([
                'owner' => QueuedExport::currentOwner(),
                'report' => $this->report,
                'format' => $format,
                'status' => QueuedExport::QUEUED,
                'disk' => (string) config('i-reports.queue.disk', 'local'),
                'request' => $request,
            ]);
        }

        ExportReportJob::dispatch($request, $format, Auth::id(), null, null, $record?->id);

        $this->export_message = __('Your :format is being prepared in the background. It will appear below when it is ready.', ['format' => strtoupper($format)]);
        unset($this->recentExports, $this->hasPendingExports);
        $this->dispatch('exportQueued', format: $format);

        // With a sync queue the job has already finished; report the outcome straight away.
        $this->announceFinishedExports($record ? [$record->id] : []);
        $this->pending_export_ids = $this->pendingExportIds();
    }

    /**
     * The current user's latest background exports of this report.
     *
     * @return array<int, array{id: int, format: string, status: string, created: string, size: string|null, error: string|null, url: string|null}>
     */
    #[Computed]
    public function recentExports(): array
    {
        if (! $this->exports_enabled) {
            return [];
        }

        return QueuedExport::query()
            ->ownedBy(QueuedExport::currentOwner())
            ->where('report', $this->report)
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (QueuedExport $export) => [
                'id' => $export->id,
                'format' => $export->format,
                'status' => $export->status,
                'created' => $export->created_at?->diffForHumans() ?? '',
                'size' => $export->isReady() ? $export->humanSize() : null,
                'error' => $export->status === QueuedExport::FAILED ? $export->error : null,
                'url' => $export->isReady() ? route('i-reports.exports.download', ['export' => $export->id]) : null,
            ])
            ->all();
    }

    #[Computed]
    public function hasPendingExports(): bool
    {
        return collect($this->recentExports())->contains(fn (array $export) => in_array($export['status'], [QueuedExport::QUEUED, QueuedExport::PROCESSING], true));
    }

    /**
     * Polled while exports are pending; re-reads their status.
     */
    public function refreshExports(): void
    {
        unset($this->recentExports, $this->hasPendingExports);

        $this->announceFinishedExports($this->pending_export_ids);
        $this->pending_export_ids = $this->pendingExportIds();
    }

    /**
     * Tell the user about exports that were pending and have now finished, and fire a browser
     * event ("i-reports:export-ready" / "i-reports:export-failed") the app can hook a toast to.
     *
     * @param  array<int, int>  $previouslyPending
     */
    protected function announceFinishedExports(array $previouslyPending): void
    {
        foreach ($this->recentExports() as $export) {
            if (! in_array($export['id'], $previouslyPending, true)) {
                continue;
            }

            if ($export['status'] === QueuedExport::READY) {
                $this->export_message = __('Your :format is ready. Download it below.', ['format' => strtoupper($export['format'])]);
                $this->dispatch('i-reports:export-ready', id: $export['id'], format: $export['format'], url: $export['url']);
            } elseif ($export['status'] === QueuedExport::FAILED) {
                $this->export_message = __('Your :format export failed.', ['format' => strtoupper($export['format'])]);
                $this->dispatch('i-reports:export-failed', id: $export['id'], format: $export['format']);
            }
        }
    }

    /**
     * @return array<int, int>
     */
    protected function pendingExportIds(): array
    {
        return collect($this->recentExports())
            ->filter(fn (array $export) => in_array($export['status'], [QueuedExport::QUEUED, QueuedExport::PROCESSING], true))
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * Remove one of the user's exports (record and file).
     */
    public function dismissExport(int $id): void
    {
        if (! $this->exports_enabled) {
            return;
        }

        QueuedExport::query()->ownedBy(QueuedExport::currentOwner())->whereKey($id)->first()?->deleteWithFile();
        unset($this->recentExports, $this->hasPendingExports);
    }

    /**
     * Whether a format should run in the background for the current row count.
     */
    protected function shouldQueue(string $format): bool
    {
        if (! config('i-reports.queue.enabled') || $format === 'print') {
            return false;
        }

        $thresholds = (array) config('i-reports.queue.thresholds', []);
        $threshold = (int) ($thresholds[$format] ?? config('i-reports.queue.threshold', 0));

        return $threshold > 0 && $this->total >= $threshold;
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    public function updatedPerPage(mixed $value): void
    {
        $this->per_page = in_array((int) $value, $this->per_page_list, true) ? (int) $value : $this->getReportInstance()->getPagination();
        $this->firstPage();
    }

    public function updatedPage(mixed $value): void
    {
        $this->page = max(1, (int) $value);
    }

    public function goToPage(): void
    {
        $this->syncTotals();
        $this->page = max(1, min((int) $this->page, $this->last_page));
    }

    public function nextPage(): void
    {
        $this->syncTotals();

        if ($this->page < $this->last_page) {
            $this->page++;
        }
    }

    public function prevPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function firstPage(): void
    {
        $this->page = 1;
        $this->totalsSynced = false;
    }

    public function lastPage(): void
    {
        $this->syncTotals();
        $this->page = $this->last_page;
    }

    /*
    |--------------------------------------------------------------------------
    | Presets
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function presets(): array
    {
        if (! $this->presets_enabled) {
            return [];
        }

        return ReportPreset::query()
            ->forUser(Auth::id())
            ->where('report', $this->report)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ReportPreset $preset) => ['id' => $preset->id, 'name' => $preset->name])
            ->all();
    }

    public function savePreset(): void
    {
        if (! $this->presets_enabled) {
            return;
        }

        $this->validate(['preset_name' => 'required|string|max:100']);

        ReportPreset::query()->updateOrCreate(
            ['user_id' => Auth::id(), 'report' => $this->report, 'name' => trim($this->preset_name)],
            ['state' => [
                'filters' => $this->filters,
                'search' => $this->search,
                'sort_field' => $this->sort_field,
                'sort_direction' => $this->sort_direction,
                'per_page' => $this->per_page,
                'hidden_columns' => $this->hidden_columns,
            ]],
        );

        $this->preset_name = '';
        unset($this->presets);
    }

    public function applyPreset(int $presetId): void
    {
        if (! $this->presets_enabled) {
            return;
        }

        $preset = ReportPreset::query()->forUser(Auth::id())->where('report', $this->report)->find($presetId);

        if ($preset === null) {
            return;
        }

        $state = $preset->state ?? [];
        $this->filters = is_array($state['filters'] ?? null) ? $state['filters'] : [];
        $this->search = (string) ($state['search'] ?? '');
        $this->sort_field = $state['sort_field'] ?? null;
        $this->sort_direction = in_array($state['sort_direction'] ?? 'asc', ['asc', 'desc'], true) ? $state['sort_direction'] : 'asc';
        $this->updatedPerPage($state['per_page'] ?? $this->per_page);
        $this->updatedSortField();
        $this->hidden_columns = is_array($state['hidden_columns'] ?? null) ? $state['hidden_columns'] : $this->defaultHiddenColumns();
        $this->updatedHiddenColumns();
        $this->firstPage();
    }

    public function deletePreset(int $presetId): void
    {
        if (! $this->presets_enabled) {
            return;
        }

        ReportPreset::query()->forUser(Auth::id())->where('report', $this->report)->whereKey($presetId)->delete();
        unset($this->presets);
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    */

    /**
     * The report body for inline mode. One COUNT and one SELECT per interaction.
     */
    #[Computed]
    public function reportHtml(): string
    {
        $this->syncTotals();

        $report = $this->getReportInstance();
        $context = app(ReportContext::class);
        $context->setRequestData(array_merge($this->requestHelper()->toArray(), ['export' => 'inline']));

        return $report->toHtml();
    }

    /**
     * Paper, orientation, font size and scale choices for the print/PDF export dialog.
     *
     * @return array{defaults: array{paper: string, orientation: string, font_size: float|null, scale: int}, papers: array<int, string>, orientations: array<int, string>, font_sizes: array<int, float>, scales: array<int, int>}
     */
    #[Computed]
    public function pageSetupOptions(): array
    {
        return PageSetup::options($this->getReportInstance());
    }

    public function pageSetupEnabled(): bool
    {
        return (bool) config('i-reports.page_setup.enabled', true);
    }

    /**
     * The validated page setup for print and PDF; empty for other formats.
     *
     * @param  array<string, mixed>  $pageSetup
     * @return array<string, mixed>
     */
    protected function resolvePageSetup(string $format, array $pageSetup): array
    {
        if (! in_array($format, ['print', 'pdf'], true) || $pageSetup === [] || ! $this->pageSetupEnabled()) {
            return [];
        }

        return PageSetup::resolve($this->getReportInstance(), $pageSetup)->toArray();
    }

    public function render(): View
    {
        $this->syncTotals();

        return view('i-reports::livewire.report-viewer', [
            'reportUrl' => $this->mode === 'iframe' ? $this->reportUrl() : null,
            'reportHtml' => $this->mode === 'inline' ? $this->reportHtml() : null,
            'exportOptions' => (array) config('i-reports.export_options', []),
        ]);
    }
}
