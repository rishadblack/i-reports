<?php

namespace Rishadblack\IReports;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;
use Rishadblack\IReports\Contracts\ReportRenderer;
use Rishadblack\IReports\Traits\Helpers;
use Rishadblack\IReports\Traits\WithExcel;
use Rishadblack\IReports\Traits\WithMpdfPdf;
use Rishadblack\IReports\Traits\WithQueryBuilder;
use Rishadblack\IReports\Views\Column;
use Rishadblack\IReports\Views\Filter;

abstract class BaseReportController extends Controller
{
    use Helpers, WithExcel, WithMpdfPdf, WithQueryBuilder;

    public function __construct()
    {
        $this->configure();
    }

    /**
     * The base query. This is the authorization boundary: scope it to the current user or tenant.
     */
    abstract public function builder(): Builder;

    /**
     * Report settings (title, sort, pagination, PDF options). Runs in the constructor.
     */
    abstract public function configure(): void;

    /**
     * @return array<int, Column>
     */
    abstract public function columns(): array;

    /**
     * Whether the current user may open this report. Returning false yields a 403.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Extra variables for the view, available as $additional_datas.
     *
     * @return array<string, mixed>
     */
    public function additionalData(): array
    {
        return [];
    }

    /**
     * Runs after selects and joins, before pagination. Use it for withCount(), groupBy(), etc.
     */
    public function additionalQuery(Builder $builder): Builder
    {
        return $builder;
    }

    /**
     * Totals computed from the filtered query, available as $summaries in the view.
     *
     * @return array<string, mixed>
     */
    public function summaries(Builder $builder): array
    {
        return [];
    }

    /**
     * Extra search behaviour, runs after the default search on searchable columns.
     */
    public function search(Builder $builder, string $search): Builder
    {
        return $builder;
    }

    /**
     * Transform the fetched rows. Applied to each page and to each export chunk.
     *
     * @param  Collection<int, Model>  $collection
     * @return Collection<int, Model>
     */
    public function map(Collection $collection): Collection
    {
        return $collection;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function renderReport(string $view, array $data = []): View
    {
        return app(ReportRenderer::class)->render($this, $view, $data);
    }
}
