<?php
namespace Rishadblack\IReports\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
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
        $this->requestDatas = array_merge($this->filters, [
            'per_page' => $this->per_page,
            'page' => $this->page,
            'search' => $this->search,
            'export' => 'view',
        ]);

        $this->updatePaginationInfo();

        $query = http_build_query($this->requestDatas);

        return route('ireport.view', ['report' => $this->report]) . '?' . $query;
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

        $query = http_build_query(array_merge($this->filters, [
            'export' => $format,
            'search' => $this->search,
        ]));

        $url = route('ireport.view', ['report' => $this->report]) . '?' . $query;

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

        $this->requestDatas = array_merge($this->filters, [
            'per_page' => $this->per_page,
            'page' => $this->page,
            'search' => $this->search,
        ]);

        if (method_exists($reportInstance, 'getPaginationInfo')) {
            $paginationInfo = $reportInstance->getPaginationInfo(
                $this->requestDatas,
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
            'reportUrl' => $this->reportUrl, // ✅ passes computed property
        ]);
    }
}
