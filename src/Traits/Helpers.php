<?php
namespace Rishadblack\IReports\Traits;

use Illuminate\Support\Str;
use ReflectionClass;

trait Helpers
{
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

    public function setHeaderView(int $header_view)
    {
        $this->header_view = $header_view;
        return $this;
    }

    public function getHeaderView(): int
    {
        return $this->header_view ?? config('i-reports.default_pagination');
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

        // Remove trailing 'Report' suffix if exists
        // $className = preg_replace('/Report$/', '', $className);

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

}
