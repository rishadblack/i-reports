<?php
namespace Rishadblack\IReports\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionClass;
use Rishadblack\IReports\Helpers\ReportHelper;
use Rishadblack\IReports\Views\Column;

trait Helpers
{
    protected $header_title;
    protected $report_title;
    protected $file_name;
    protected $file_title;
    protected $header_view;
    protected $pagination;
    protected $pagination_list = [];
    protected $paper_size;
    protected $orientation;
    protected $default_sort_field;
    protected $default_sort_direction;
    protected $search_field;

    public function setHeaderView(string $header_view)
    {
        // $this->header_view = $header_view;
        Config::set('i-reports.header_view', $header_view);
        return $this;
    }

    public function getHeaderView(): string | null
    {
        // return $this->header_view;
        return Config::get('i-reports.header_view', null);
    }

    public function setHeaderTitle(string $headerTitle)
    {
        $this->header_title = $headerTitle;
        return $this;
    }

    public function getHeaderTitle(): string
    {
        return $this->header_title ?? config('app.name');
    }

    public function setReportTitle(string $reportTitle)
    {
        $this->report_title = $reportTitle;
        return $this;
    }

    protected function setupColumns(): Collection
    {
        return collect($this->columns())
            ->filter(fn($column) => $column instanceof Column)
            ->map(function (Column $column) {
                if ($column->isBaseColumn()) {
                    $column->setTable($this->getBuilder()->getModel()->getTable());
                } else {
                    $column->setTable($this->getTableForColumn($column));
                }
                return $column;
            });

    }

    public function getColumns(): Collection
    {
        return $this->setupColumns();
    }

    public function getReportTitle(): string
    {
        $className = (new ReflectionClass($this))->getShortName();

        return $this->report_title ?? Str::title(Str::replace('_', ' ', Str::snake($className)));
    }

    public function setFileName(string $fileName)
    {
        $this->file_name = $fileName;
        return $this;
    }

    public function getFileName(): string
    {
        // Get short class name like 'UsersReport'
        $className = (new ReflectionClass($this))->getShortName();

        // Convert to snake_case, e.g. 'Users' or 'Sales'
        $className = Str::kebab($className);

        // Append datetime suffix, e.g. _20250729_143012
        $datetime = now()->format('ymd-His');

        return $this->file_name ?? "{$className}-{$datetime}";
    }

    public function setFileTitle(string $fileTitle)
    {
        $this->file_title = $fileTitle;
        return $this;
    }

    public function getFileTitle(): string
    {
        return $this->file_title ?? Str::title(Str::snake($this->getFileName()));
    }

    public function setPagination(int $pagination)
    {
        $this->pagination = $pagination;
        return $this;
    }

    public function getPagination(): int
    {
        return $this->pagination ?? config('i-reports.default_pagination');
    }

    public function setPaginationList(array $paginationList)
    {
        $this->pagination_list = $paginationList;
        return $this;
    }

    public function getPaginationList(): array
    {
        return count($this->pagination_list) > 0 ? $this->pagination_list : config('i-reports.default_pagination_list');
    }

    public function setPaperSize(string $paperSize)
    {
        $this->paper_size = $paperSize;
        return $this;
    }

    public function getPaperSize(): string
    {
        return $this->paper_size ?? config('i-reports.pdf_paper_size');
    }

    public function setOrientation(string $orientation)
    {
        $this->orientation = $orientation;
        return $this;
    }

    public function getOrientation(): string
    {
        return $this->orientation ?? config('i-reports.pdf_orientation');
    }

    public function getFilter(string $filterName): string | bool
    {
        return isset($this->filters[$filterName]) ? $this->filters[$filterName] : false;
    }

    public function setDefaultSort(string $field, string $direction = 'asc')
    {
        $this->default_sort_field = $field;
        $this->default_sort_direction = $direction;
        return $this;
    }

    public function getDefaultSortField(): array
    {
        return [$this->default_sort_field, $this->default_sort_direction];
    }

    public function setSearchField(array | string $SearchField)
    {
        if (is_string($SearchField)) {
            $SearchField = [$SearchField];
        }

        $this->search_field = $SearchField;

        return $this;
    }

    public function getSearchField(): array
    {
        return $this->search_field ?? [];
    }

    public function getSelectedColumnsForQuery(): Collection
    {
        return $this->getColumns()
            ->reject(fn(Column $column) => $column->isHidden())
            ->reject(fn(Column $column) => $column->isCustom());
    }

    public function getViewName(): string
    {
        $fullClassName = get_class($this);
        $namespaceParts = explode('\\', $fullClassName);
        $moduleNamespace = config('modules.namespace');
        $moduleLivewireNamespace = config('modules-livewire.namespace');
        $reportNamespaceSegment = config('i-reports.report_namespace');
        $livewireNamespace = config('livewire.class_namespace');

        // Detect module
        if ($namespaceParts[0] === $moduleNamespace && isset($namespaceParts[1])) {
            $moduleName = $namespaceParts[1];

            // Find start index of Livewire\Reports\...
            $livewireIndex = array_search($moduleLivewireNamespace, $namespaceParts);
            if ($livewireIndex === false) {
                throw new \Exception("Livewire namespace '{$moduleLivewireNamespace}' not found in: {$fullClassName}");
            }

            $subPathParts = array_slice($namespaceParts, $livewireIndex); // e.g. ['Livewire', 'Reports', 'Test', 'ReceivableReport']

            // Remove the last class name and convert it to kebab-case
            $componentParts = collect($subPathParts)
                ->map(fn($part) => Str::kebab($part));

            return strtolower($moduleName) . '::' . $componentParts->implode('.');
        }

        // Fallback: App\Livewire\Reports\...
        $appLivewireIndex = array_search($livewireNamespace, $namespaceParts);
        if ($appLivewireIndex !== false) {
            $subPathParts = array_slice($namespaceParts, $appLivewireIndex); // ['Livewire', 'Reports', 'UsersReport']

            $componentParts = collect($subPathParts)
                ->map(fn($part) => Str::kebab($part));

            return $componentParts->implode('.');
        }

        throw new \Exception("Unable to resolve Livewire view name for: {$fullClassName}");
    }

    protected function exportPdf(string $view, array $data = [])
    {
        if (config('i-reports.pdf_export_by')) {
            return $this->pdfExportByMpdf($view, $data);
        }
    }

    protected function summeryData(): array
    {
        return $this->summaries($this->baseBuilder());
    }

    public function view()
    {
        $export = ReportHelper::getExport();
        $this->baseBuilder();
        $this->setBuilder($this->selectFields());
        $this->setBuilder($this->additionalQuery($this->getBuilder()));

        if (! in_array($export, ['print', 'xlsx', 'csv', 'pdf'])) {
            $data = $this->paginate($this->getBuilder());

            $data->setCollection(
                $this->map($data->getCollection())
            );

        } else {
            $data = $this->map($this->getBuilder()->get());
        }

        $data = [
            'datas' => $data,
            'columns' => $this->columns(),
            'additional_datas' => $this->additionalData(),
            'summaries' => $this->summeryData(),
            'export' => $export,
            'options' => [
                'header_view' => $this->getHeaderView(),
            ],
        ];

        if (in_array($export, ['xlsx', 'csv'])) {
            return $this->exportExcelFromView($this->getViewName(), $data, $export);
        } elseif ($export === 'pdf') {
            return $this->exportPdf($this->getViewName(), $data);
        }

        return $this->renderReport($this->getViewName(), $data);
    }

}
