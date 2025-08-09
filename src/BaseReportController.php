<?php
namespace Rishadblack\IReports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
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

    public function additionalQuery(Builder $builder): Builder
    {
        return $builder;
    }

    public function summaries(Builder $builder): array
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

    public function map(Collection $collection): Collection
    {
        return $collection;
    }

    public function renderReport(string $view, $data = []): View
    {
        return view($view, $data);
    }
}
