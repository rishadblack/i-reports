<?php

namespace Rishadblack\IReports\Traits;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use Rishadblack\IReports\Exports\ChunkedReportWriter;
use Rishadblack\IReports\Exports\ReportExporter;
use Rishadblack\IReports\Support\ReportContext;
use Rishadblack\IReports\Views\Column;
use Rishadblack\IReports\Views\Filter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait Helpers
{
    protected ?string $header_title = null;

    protected ?string $report_title = null;

    protected ?string $file_name = null;

    protected ?string $file_title = null;

    protected ?string $header_view = null;

    protected ?string $pdf_header_view = null;

    protected ?string $pdf_footer_view = null;

    protected ?int $pagination = null;

    /** @var array<int, int> */
    protected array $pagination_list = [];

    protected ?string $paper_size = null;

    protected ?string $orientation = null;

    protected ?string $default_sort_field = null;

    protected ?string $default_sort_direction = null;

    /** @var array<int, string>|null */
    protected ?array $search_field = null;

    protected ?string $excel_mode = null;

    protected ?string $group_by = null;

    /** @var Collection<int, Column>|null */
    protected ?Collection $columnCache = null;

    /** @var array<int, Filter>|null */
    protected ?array $filterCache = null;

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */

    public function setHeaderView(string $header_view): static
    {
        $this->header_view = $header_view;

        return $this;
    }

    public function getHeaderView(): ?string
    {
        return $this->header_view ?? config('i-reports.header_view');
    }

    public function setPdfHeaderView(string $view): static
    {
        $this->pdf_header_view = $view;

        return $this;
    }

    public function getPdfHeaderView(): ?string
    {
        return $this->pdf_header_view ?? config('i-reports.pdf_header_view');
    }

    public function setPdfFooterView(string $view): static
    {
        $this->pdf_footer_view = $view;

        return $this;
    }

    public function getPdfFooterView(): ?string
    {
        return $this->pdf_footer_view ?? config('i-reports.pdf_footer_view');
    }

    public function setHeaderTitle(string $headerTitle): static
    {
        $this->header_title = $headerTitle;

        return $this;
    }

    public function getHeaderTitle(): string
    {
        return $this->header_title ?? (string) config('app.name');
    }

    public function setReportTitle(string $reportTitle): static
    {
        $this->report_title = $reportTitle;

        return $this;
    }

    public function getReportTitle(): string
    {
        $className = (new ReflectionClass($this))->getShortName();

        return $this->report_title ?? Str::title(Str::replace('_', ' ', Str::snake($className)));
    }

    public function setFileName(string $fileName): static
    {
        $this->file_name = $fileName;

        return $this;
    }

    public function getFileName(): string
    {
        $className = Str::kebab((new ReflectionClass($this))->getShortName());

        return $this->file_name ?? $className.'-'.now()->format('ymd-His');
    }

    public function setFileTitle(string $fileTitle): static
    {
        $this->file_title = $fileTitle;

        return $this;
    }

    public function getFileTitle(): string
    {
        return $this->file_title ?? $this->getReportTitle();
    }

    public function setPagination(int $pagination): static
    {
        $this->pagination = $pagination;

        return $this;
    }

    public function getPagination(): int
    {
        return $this->pagination ?? (int) config('i-reports.default_pagination', 50);
    }

    /**
     * @param  array<int, int>  $paginationList
     */
    public function setPaginationList(array $paginationList): static
    {
        $this->pagination_list = array_values(array_map('intval', $paginationList));

        return $this;
    }

    /**
     * @return array<int, int>
     */
    public function getPaginationList(): array
    {
        $list = count($this->pagination_list) > 0 ? $this->pagination_list : (array) config('i-reports.default_pagination_list', [50]);

        if (! in_array($this->getPagination(), $list, true)) {
            $list[] = $this->getPagination();
            sort($list);
        }

        return array_values($list);
    }

    public function setPaperSize(string $paperSize): static
    {
        $this->paper_size = $paperSize;

        return $this;
    }

    public function getPaperSize(): string
    {
        return $this->paper_size ?? (string) config('i-reports.pdf_paper_size', 'A4');
    }

    public function setOrientation(string $orientation): static
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function getOrientation(): string
    {
        return $this->orientation ?? (string) config('i-reports.pdf_orientation', 'portrait');
    }

    public function setDefaultSort(string $field, string $direction = 'asc'): static
    {
        $this->default_sort_field = $field;
        $this->default_sort_direction = $direction;

        return $this;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    public function getDefaultSortField(): array
    {
        return [$this->default_sort_field, $this->default_sort_direction];
    }

    /**
     * @param  array<int, string>|string  $searchField
     */
    public function setSearchField(array|string $searchField): static
    {
        $this->search_field = is_string($searchField) ? [$searchField] : array_values($searchField);

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getSearchField(): array
    {
        return $this->search_field ?? [];
    }

    /**
     * "query" (chunked, fast) or "view" (renders the Blade table).
     */
    public function setExcelMode(string $mode): static
    {
        $this->excel_mode = $mode === 'view' ? 'view' : 'query';

        return $this;
    }

    public function getExcelMode(): string
    {
        return $this->excel_mode ?? (config('i-reports.excel_mode') === 'view' ? 'view' : 'query');
    }

    /**
     * Group rows by a column name; the grouped-tbody component renders headers and subtotals.
     */
    public function setGroupBy(string $columnName): static
    {
        $this->group_by = $columnName;

        return $this;
    }

    public function getGroupBy(): ?string
    {
        return $this->group_by;
    }

    /*
    |--------------------------------------------------------------------------
    | Columns and filters
    |--------------------------------------------------------------------------
    */

    /**
     * @return Collection<int, Column>
     */
    protected function setupColumns(): Collection
    {
        return collect($this->columns())
            ->filter(fn ($column) => $column instanceof Column)
            ->values()
            ->each(function (Column $column) {
                $column->setTable($column->isBaseColumn()
                    ? $this->getBuilder()->getModel()->getTable()
                    : $this->getTableForColumn($column));
            });
    }

    /**
     * Columns with their SQL tables resolved. Cached per report instance.
     *
     * @return Collection<int, Column>
     */
    public function getColumns(): Collection
    {
        return $this->columnCache ??= $this->setupColumns();
    }

    public function getColumnByName(string $name): ?Column
    {
        return $this->getColumns()->first(fn (Column $column) => $column->getName() === $name);
    }

    /**
     * @return Collection<int, Column>
     */
    public function getSelectedColumnsForQuery(): Collection
    {
        return $this->getColumns()
            ->reject(fn (Column $column) => $column->isHidden())
            ->reject(fn (Column $column) => $column->isCustom());
    }

    /**
     * Columns that appear in the given output.
     *
     * @return Collection<int, Column>
     */
    public function getVisibleColumns(?string $export = null): Collection
    {
        $export ??= $this->context()->getExport();

        return $this->getColumns()->filter(fn (Column $column) => $this->context()->isColumnVisible($column, $export))->values();
    }

    /**
     * @return array<int, Filter>
     */
    public function getFilters(): array
    {
        return $this->filterCache ??= array_values(array_filter($this->filters(), fn ($filter) => $filter instanceof Filter));
    }

    /**
     * Current value of a filter, or false when it is not set.
     */
    public function getFilter(string $filterName): mixed
    {
        $filters = $this->context()->getFilters();

        return array_key_exists($filterName, $filters) && filled($filters[$filterName]) ? $filters[$filterName] : false;
    }

    public function context(): ReportContext
    {
        return app(ReportContext::class);
    }

    /**
     * The active filters, search and sort as readable label/value pairs for headers and chips.
     *
     * @return array<int, array{key: string, label: string, value: string}>
     */
    public function appliedFilters(bool $includeSearchAndSort = true): array
    {
        $values = $this->context()->getFilters();
        $applied = [];

        foreach ($this->getFilters() as $filter) {
            if (! array_key_exists($filter->key(), $values)) {
                continue;
            }

            $description = $filter->describe($values[$filter->key()]);

            if ($description !== null) {
                $applied[] = ['key' => $filter->key(), 'label' => $filter->getTitle(), 'value' => $description];
            }
        }

        if (! $includeSearchAndSort) {
            return $applied;
        }

        if (($search = $this->context()->getSearch()) !== '') {
            $applied[] = ['key' => '__search', 'label' => 'Search', 'value' => $search];
        }

        if (($sort = $this->getActiveSort()) !== null) {
            $column = $this->getColumnByName($sort[0]);
            $applied[] = ['key' => '__sort', 'label' => 'Sorted by', 'value' => ($column?->getTitle() ?? $sort[0]).' ('.($sort[1] === 'desc' ? 'descending' : 'ascending').')'];
        }

        return $applied;
    }

    /**
     * Everything the branded header and footer of an export need.
     *
     * @return array{name: string, tagline: string|null, logo: string|null, logo_path: string|null, title: string, accent: string, filters: array<int, array{key: string, label: string, value: string}>, generated_at: string, generated_by: string|null}
     */
    public function branding(): array
    {
        $config = (array) config('i-reports.branding', []);
        $logoPath = is_string($config['logo'] ?? null) && is_file($config['logo']) ? $config['logo'] : null;
        $logo = null;

        if ($logoPath !== null) {
            $mime = str_ends_with(strtolower($logoPath), '.png') ? 'image/png' : 'image/jpeg';
            $logo = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        $user = auth()->user();
        $userName = $user ? ($user->name ?? $user->email ?? null) : null;

        return [
            'name' => (string) ($config['name'] ?? null ?: $this->getHeaderTitle()),
            'tagline' => $config['tagline'] ?? null,
            'logo' => $logo,
            'logo_path' => $logoPath,
            'title' => $this->getReportTitle(),
            'accent' => (string) ($config['accent_color'] ?? '#1f2937'),
            'filters' => ($config['show_filters'] ?? true) ? $this->appliedFilters() : [],
            'generated_at' => now()->format((string) ($config['date_format'] ?? 'd M Y, h:i A')),
            'generated_by' => ($config['show_generated_by'] ?? true) && is_scalar($userName) ? (string) $userName : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | View resolution
    |--------------------------------------------------------------------------
    */

    public function getViewName(): string
    {
        $fullClassName = get_class($this);
        $namespaceParts = explode('\\', $fullClassName);
        $moduleNamespace = config('modules.namespace');
        $moduleLivewireNamespace = config('modules-livewire.namespace', 'Livewire');
        $livewireNamespace = (string) config('livewire.class_namespace', 'App\\Livewire');

        if ($moduleNamespace && $namespaceParts[0] === $moduleNamespace && isset($namespaceParts[1])) {
            $moduleName = $namespaceParts[1];
            $livewireIndex = array_search($moduleLivewireNamespace, $namespaceParts, true);

            if ($livewireIndex === false) {
                throw new \RuntimeException("Livewire namespace '{$moduleLivewireNamespace}' not found in: {$fullClassName}");
            }

            $componentParts = collect(array_slice($namespaceParts, $livewireIndex))
                ->map(fn (string $part) => Str::kebab($part));

            return strtolower($moduleName).'::'.$componentParts->implode('.');
        }

        $livewireNamespaceParts = explode('\\', $livewireNamespace);
        $secondMatch = $livewireNamespaceParts[1] ?? null;
        $appLivewireIndex = $secondMatch ? array_search($secondMatch, $namespaceParts, true) : false;

        if ($appLivewireIndex !== false) {
            return collect(array_slice($namespaceParts, $appLivewireIndex))
                ->map(fn (string $part) => Str::kebab($part))
                ->implode('.');
        }

        throw new \RuntimeException("Unable to resolve Livewire view name for: {$fullClassName}");
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering and exporting
    |--------------------------------------------------------------------------
    */

    /**
     * Data passed to the report view.
     *
     * @return array<string, mixed>
     */
    public function viewData(bool $allRows = false): array
    {
        $export = $this->context()->getExport();
        $builder = $this->exportBuilder();

        if ($allRows || $this->context()->isFullExport()) {
            $datas = $this->map($builder->get());
        } else {
            $datas = $this->paginate($builder);
            $datas->setCollection($this->map($datas->getCollection()));
        }

        $aggregates = $this->aggregates();

        $this->context()
            ->put('report', $this)
            ->put('aggregates', $aggregates)
            ->put('group_by', $this->getGroupBy());

        return [
            'datas' => $datas,
            'columns' => $this->getVisibleColumns($export)->all(),
            'all_columns' => $this->getColumns()->all(),
            'additional_datas' => $this->additionalData(),
            'summaries' => $this->summaries($this->baseBuilder()),
            'aggregates' => $aggregates,
            'group_by' => $this->getGroupBy(),
            'export' => $export,
            'report' => $this,
            'branding' => $this->branding(),
            'report_title' => $this->getReportTitle(),
            'header_title' => $this->getHeaderTitle(),
            'options' => [
                'header_view' => $this->getHeaderView(),
                'pdf_header_view' => $this->getPdfHeaderView(),
                'pdf_footer_view' => $this->getPdfFooterView(),
            ],
        ];
    }

    /**
     * Render the report for the current context, or return a download for pdf, xlsx and csv.
     */
    public function view(): View|Response|StreamedResponse|BinaryFileResponse
    {
        $this->publishToContext();
        $export = $this->context()->getExport();

        if ($this->context()->isDownload()) {
            return app(ReportExporter::class)->download($this, $export);
        }

        if ($export === 'print' && $this->shouldStream()) {
            return app(ChunkedReportWriter::class)->printResponse($this);
        }

        return $this->renderReport($this->getViewName(), $this->viewData());
    }

    /**
     * Render the HTML body for the current context as a string.
     */
    public function toHtml(): string
    {
        $this->publishToContext();

        return $this->renderReport($this->getViewName(), $this->viewData())->render();
    }

    /**
     * Share titles and columns with the Blade components for this request.
     */
    public function publishToContext(): void
    {
        $this->context()
            ->setColumns($this->getColumns()->all())
            ->setReportTitle($this->getReportTitle())
            ->setHeaderTitle($this->getHeaderTitle())
            ->resetRendered();
    }

    /**
     * Group a page of rows by the configured column, with subtotals for aggregate columns.
     *
     * @param  iterable<int, mixed>  $rows
     * @return array<int, array{key: mixed, label: string, rows: Collection<int, mixed>, subtotals: array<string, mixed>}>
     */
    public function groupRows(iterable $rows): array
    {
        $groupColumn = $this->getGroupBy() ? $this->getColumnByName($this->getGroupBy()) : null;
        $aggregateColumns = $this->getColumns()->filter(fn (Column $column) => $column->hasAggregate());
        $rows = ($rows instanceof LengthAwarePaginator ? $rows->getCollection() : collect($rows))->values();

        if ($groupColumn === null) {
            return [[
                'key' => null,
                'label' => '',
                'rows' => $rows,
                'subtotals' => $this->subtotals($rows, $aggregateColumns),
            ]];
        }

        $groups = [];

        foreach ($rows->groupBy(fn ($row) => (string) $groupColumn->getValue($row), true) as $key => $groupRows) {
            $groups[] = [
                'key' => $key,
                'label' => $groupColumn->render($groupRows->first()),
                'rows' => $groupRows->values(),
                'subtotals' => $this->subtotals($groupRows, $aggregateColumns),
            ];
        }

        return $groups;
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @param  Collection<int, Column>  $aggregateColumns
     * @return array<string, mixed>
     */
    protected function subtotals(Collection $rows, Collection $aggregateColumns): array
    {
        $subtotals = [];

        foreach ($aggregateColumns as $column) {
            $values = $rows->map(fn ($row) => $column->getValue($row))->filter(fn ($value) => $value !== null && $value !== '');
            $numeric = $values->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value);

            $subtotals[$column->getName()] = match ($column->getAggregate()) {
                'sum' => $numeric->sum(),
                'avg' => $numeric->count() > 0 ? $numeric->avg() : null,
                'count' => $values->count(),
                'min' => $numeric->min(),
                'max' => $numeric->max(),
                default => null,
            };
        }

        return $subtotals;
    }

    /**
     * Rows for the current page, honouring map().
     *
     * @return EloquentCollection<int, Model>
     */
    public function rows(): EloquentCollection
    {
        return $this->map($this->exportBuilder()->get());
    }
}
