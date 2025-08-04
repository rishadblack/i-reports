<?php
namespace Rishadblack\IReports\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Rishadblack\IReports\Services\ReportTokenManager;
use Rishadblack\IReports\Traits\HasReportClass;

class ReportViewer extends Component
{
    use HasReportClass, WithPagination;

    public $report;
    public $filters = [];
    public $export  = '';
    public $per_page;
    public $per_page_list = [];
    public $page          = 1; // current page
    public $last_page     = 1;
    public $current_page  = 1;
    public $total         = 0;
    public $search        = '';
    public $requestDatas  = [];

    protected $queryString = ['filters', 'search', 'per_page', 'page'];

    public string $orientation = 'fullpage'; // default

    protected function rules()
    {
        return [
            'search' => 'nullable|string|max:255',
            'export' => 'nullable|in:print,xlsx,csv,pdf',
            'page' => 'nullable|integer|min:1|max:' . $this->last_page,
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function setOrientation(string $orientation)
    {
        if (in_array($orientation, ['portrait', 'landscape', 'fullpage'])) {
            $this->orientation = $orientation;
        }
    }

    public function goToPage()
    {
        $this->page = max(1, min($this->page, $this->last_page));
    }

    public function nextPage()
    {
        if ($this->page < $this->last_page) {
            $this->page++;
        }
    }

    public function prevPage()
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function firstPage()
    {
        $this->page = 1;
    }

    public function lastPage()
    {
        $this->page = $this->last_page;
    }

    public function searchReport()
    {
        $this->firstPage();
    }

    public function reserReport()
    {
        $this->resetExcept(['report', 'per_page', 'per_page_list']);
        $this->updatePaginationInfo();

    }

    #[Computed]
    public function reportUrl(): string
    {
        $requestDatas = array_merge($this->filters, [
            'search' => $this->search,
            'export' => 'view',
            'report' => $this->report,
        ]);

        $this->updatePaginationInfo();

        $token = ReportTokenManager::store($requestDatas);

        return route('i-reports.view', ['per_page' => $this->per_page, 'page' => $this->page, 'token' => $token]);
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

    public function updatedExport(string $format)
    {
        if (! in_array($format, ['print', 'pdf', 'xlsx', 'csv'])) {
            return;
        }

        $requestDatas = array_merge($this->filters, [
            'search' => $this->search,
            'export' => $format,
            'report' => $this->report,
        ]);

        $token = ReportTokenManager::store($requestDatas);

        $url = route('i-reports.view', ['token' => $token]);

        $this->dispatch('exportEvent', ['url' => $url]);

        $this->reset('export');
    }

    public function mount()
    {
        $reportInstance = $this->findReportClass($this->report);

        if (! $reportInstance) {
            abort(404);
        }

        $reportInstance = app($reportInstance);

        $this->per_page = $reportInstance->getPagination();
        $this->per_page_list = $reportInstance->getPaginationList();
    }

    protected function updatePaginationInfo()
    {
        $reportInstance = $this->findReportClass($this->report);

        if (! $reportInstance) {
            abort(404);
        }

        $reportInstance = app($reportInstance);

        $requestDatas = array_merge($this->filters, [
            'search' => $this->search,
        ]);

        if (method_exists($reportInstance, 'getPaginationInfo')) {
            $paginationInfo = $reportInstance->getPaginationInfo(
                $requestDatas,
                $this->per_page,
                $this->page
            );

            $this->last_page = $paginationInfo['last_page'] ?? 1;
            $this->current_page = $paginationInfo['current_page'] ?? 1;
            $this->total = $paginationInfo['total'] ?? 0;

        }
    }

    public function render()
    {
        return view('i-reports::livewire.report-viewer', [
            'reportUrl' => $this->reportUrl,
        ]);
    }
}
