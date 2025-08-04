<?php
namespace Rishadblack\IReports\Traits;

use Livewire\Attributes\Computed;
use Rishadblack\IReports\Helpers\RequestHelper;

trait WithReportViewer
{
    public $filters = [];
    public $export  = '';
    public $per_page;
    public $per_page_list = [];
    public $page          = 1;
    public $last_page     = 1;
    public $current_page  = 1;
    public $total         = 0;
    public $search        = '';

    public string $orientation = 'fullpage';

    #[Computed]
    public function requestHelper(): RequestHelper
    {
        return new RequestHelper([
            'filters' => $this->filters,
            'search' => $this->search,
            'export' => $this->export ?: 'view',
            'per_page' => $this->per_page,
            'page' => $this->page,
            'report' => $this->report,
        ]);
    }

    #[Computed]
    public function reportUrl(): string
    {
        $this->updatePaginationInfo();

        $token = $this->requestHelper()->generateToken();

        return route('i-reports.view', [
            'per_page' => $this->per_page,
            'page' => $this->page,
            'token' => $token,
        ]);
    }

    #[Computed]
    public function showingFrom(): int
    {
        return $this->total > 0 ? (($this->page - 1) * $this->per_page + 1) : 0;
    }

    #[Computed]
    public function showingTo(): int
    {
        return min($this->page * $this->per_page, $this->total);
    }

    #[Computed]
    public function reportFilters(): array
    {
        return $this->getReportInstance()->filters();
    }

    protected function updatePaginationInfo(): void
    {
        $reportInstance = $this->getReportInstance();
        $total = $reportInstance->baseBuilder()->count();
        $this->last_page = (int) ceil($total / $this->per_page);
        $this->current_page = $this->page;
        $this->total = $total ?? 0;
    }

    protected function getReportInstance()
    {
        $reportClass = $this->findReportClass($this->report);

        if (! $reportClass) {
            abort(404);
        }

        $this->requestHelper()->storeGlobally();

        return app($reportClass);
    }
}
