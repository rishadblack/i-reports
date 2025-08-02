<?php
namespace Rishadblack\IReports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use ReflectionClass;
use Rishadblack\IReports\Traits\Helpers;
use Rishadblack\IReports\Traits\SearchableQuery;
use Rishadblack\IReports\Traits\WithExcel;
use Rishadblack\IReports\Traits\WithMpdfPdf;

abstract class BaseReportController extends Controller
{
    use WithExcel, WithMpdfPdf, Helpers, SearchableQuery;

    public function __construct()
    {
        $this->configure(); // 👈 this ensures it's always called
    }

    public function configure(): void
    {
        // Child classes can override to set config, e.g. filename
    }

    /**
     * Child classes must return a query builder for the report data.
     */
    abstract public function builder(Request $request): Builder;

    /**
     * Child classes can return an array of Filter objects.
     */
    public function filters(): array
    {
        return [];
    }

    public function searchFields(): array
    {
        return ['name'];
    }

    /**
     * Apply filters from request input using the filters() method.
     */
    protected function applyFilters(Request $request, Builder $query): Builder
    {
        foreach ($this->filters() as $filter) {
            $field = $filter->getField();

            if ($request->filled($field)) {
                $value = $request->input($field);
                $filter->apply($query, $value);
            }
        }

        return $query;
    }

    protected function applySearch(Request $request, Builder $query): Builder
    {
        $search = $request->input('search');

        if ($search && $fields = $this->searchFields()) {
            return $this->applySearchable($query, $fields, $search);
        }

        return $query;
    }

    /**
     * Default pagination method.
     */
    protected function paginate(Builder $query, int $perPage = null)
    {
        $perPage = $perPage ?? request()->input('per_page', $this->getPagination());
        return $query->paginate($perPage)->appends(request()->except('page'));
    }

    /**
     * Render the report blade view.
     */
    protected function renderReport(string $view, $data = [])
    {
        return view($view, $data);
    }

    /**
     * Export to PDF using DomPDF.
     */
    protected function exportPdf(string $view, array $data = [])
    {
        return $this->pdfExportByMpdf($view, $data);
    }

    public function map(Collection $collection): Collection
    {
        return $collection;
    }

    /**
     * Main entry point for route.
     */
    public function view(Request $request)
    {
        $export = $request->input('export');
        $perPage = $request->input('per_page') ?? $this->getPagination();

        $query = $this->builder($request);

        $query = $this->applyFilters($request, $query);
        $query = $this->applySearch($request, $query);

        // If export is requested, handle it
        if (! in_array($export, ['print', 'xlsx', 'csv', 'pdf'])) {
            $data = $this->paginate($query, $perPage);

            $data->setCollection(
                $this->map($data->getCollection())
            );

        } else {
            $data = $this->map($query->get());
        }

        if (in_array($export, ['xlsx', 'csv'])) {
            return $this->exportExcelFromView($this->getViewName(), ['data' => $data, 'export' => $export], $export);
        } elseif ($export === 'pdf') {
            return $this->exportPdf($this->getViewName(), ['data' => $data, 'export' => $export]);
        }

        return $this->renderReport($this->getViewName(), ['data' => $data, 'export' => $export]);
    }

    /**
     * Default implementation automatically determines view path from class name.
     */
    protected function getViewName(): string
    {
        // Get short class name like 'UsersReport'
        $className = (new ReflectionClass($this))->getShortName();
        // Remove trailing 'Controller' suffix if exists
        $baseName = preg_replace('/Controller$/', '', $className);

        // Convert to snake_case, e.g. 'reports.users'
        $viewName = Str::snake($baseName, '-');

        return "reports.{$viewName}";
    }

}