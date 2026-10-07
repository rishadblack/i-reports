<?php

namespace Rishadblack\IReports\Traits;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Support\ReportContext;
use Rishadblack\IReports\Views\Filter;

/**
 * State and helpers shared by the report viewer. Use it to build a custom viewer component.
 */
trait WithReportViewer
{
    /** @var array<string, mixed> */
    #[Url(except: [])]
    public array $filters = [];

    #[Url(except: '')]
    public string $search = '';

    public string $export = '';

    #[Url(except: null)]
    public ?int $per_page = null;

    /** @var array<int, int> */
    #[Locked]
    public array $per_page_list = [];

    #[Url(except: 1)]
    public int $page = 1;

    public int $last_page = 1;

    public int $total = 0;

    #[Url(except: null)]
    public ?string $sort_field = null;

    #[Url(except: 'asc')]
    public string $sort_direction = 'asc';

    /** @var array<int, string> Column names switched off in the column picker. */
    #[Url(as: 'hidden', except: [])]
    public array $hidden_columns = [];

    protected ?BaseReportController $reportInstance = null;

    protected bool $totalsSynced = false;

    #[Computed]
    public function requestHelper(): RequestHelper
    {
        return new RequestHelper([
            'filters' => $this->filters,
            'search' => $this->search,
            'export' => $this->export ?: 'view',
            'per_page' => $this->per_page,
            'page' => $this->page,
            'total' => $this->totalsSynced ? $this->total : null,
            'report' => $this->report,
            'sort_field' => $this->sort_field,
            'sort_direction' => $this->sort_direction,
            'hidden_columns' => $this->hidden_columns,
        ]);
    }

    #[Computed]
    public function reportUrl(): string
    {
        $this->syncTotals();

        return route('i-reports.view', [
            'token' => $this->requestHelper()->generateToken(),
        ]);
    }

    #[Computed]
    public function showingFrom(): int
    {
        return $this->total > 0 ? (($this->page - 1) * max(1, (int) $this->per_page) + 1) : 0;
    }

    #[Computed]
    public function showingTo(): int
    {
        return min($this->page * max(1, (int) $this->per_page), $this->total);
    }

    /**
     * @return array<int, Filter>
     */
    #[Computed]
    public function reportFilters(): array
    {
        return $this->getReportInstance()->getFilters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function sortableColumns(): array
    {
        return $this->getReportInstance()->getColumns()
            ->filter(fn ($column) => $column->isSortable())
            ->map(fn ($column) => ['name' => $column->getName(), 'title' => $column->getTitle()])
            ->values()
            ->all();
    }

    /**
     * Run the COUNT once per request and clamp the page into range.
     */
    protected function syncTotals(): void
    {
        if ($this->totalsSynced) {
            return;
        }

        $this->total = $this->getReportInstance()->total();
        $this->last_page = max(1, (int) ceil($this->total / max(1, (int) $this->per_page)));
        $this->page = max(1, min($this->page, $this->last_page));
        $this->totalsSynced = true;
    }

    protected function updatePaginationInfo(): void
    {
        $this->totalsSynced = false;
        $this->syncTotals();
    }

    protected function getReportInstance(): BaseReportController
    {
        $this->requestHelper()->storeGlobally();

        if ($this->reportInstance !== null) {
            return $this->reportInstance;
        }

        $reportClass = $this->findReportClass($this->report);

        return $this->reportInstance = app($reportClass);
    }

    protected function context(): ReportContext
    {
        return app(ReportContext::class);
    }
}
