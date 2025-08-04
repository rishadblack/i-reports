<?php
namespace Rishadblack\IReports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Rishadblack\IReports\Helpers\ReportHelper;
use Rishadblack\IReports\Traits\Helpers;
use Rishadblack\IReports\Traits\WithExcel;
use Rishadblack\IReports\Traits\WithMpdfPdf;
use Rishadblack\IReports\Traits\WithQueryBuilder;

abstract class BaseReportController extends Controller
{
    use WithQueryBuilder, WithExcel, WithMpdfPdf, Helpers;

    public function __construct()
    {
        $this->configure();
    }

    abstract public function builder(): Builder;
    abstract public function configure(): void;
    abstract public function columns(): array;

    public function additionalData(): array
    {
        return [];
    }

    public function filters(): array
    {
        return [];
    }

    public function search(Builder $builder, string $search): Builder
    {
        return $builder;
    }

    protected function renderReport(string $view, $data = []): View
    {
        return view($view, $data);
    }

    protected function exportPdf(string $view, array $data = [])
    {
        if (config('i-reports.pdf_export_by')) {
            return $this->pdfExportByMpdf($view, $data);
        }
    }

    public function map(Collection $collection): Collection
    {
        return $collection;
    }

    public function view()
    {
        $export = ReportHelper::getExport();

        $query = $this->baseBuilder();

        if (! in_array($export, ['print', 'xlsx', 'csv', 'pdf'])) {
            $data = $this->paginate($query);

            $data->setCollection(
                $this->map($data->getCollection())
            );

        } else {
            $data = $this->map($query->get());
        }

        $data = [
            'datas' => $data,
            'columns' => $this->columns(),
            'additional_datas' => $this->additionalData(),
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
