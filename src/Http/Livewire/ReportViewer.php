<?php
namespace Rishadblack\IReports\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Rishadblack\IReports\Traits\HasReportClass;
use Rishadblack\IReports\Traits\WithReportViewer;

class ReportViewer extends Component
{
    use HasReportClass, WithPagination, WithReportViewer;

    public $report;
    public $filter_list = [];
    public $filter_extended_view;

    protected $queryString = ['filters', 'search', 'per_page', 'page'];

    protected function rules()
    {
        return [
            'search' => 'nullable|string|max:255',
            'export' => 'nullable|in:print,xlsx,csv,pdf',
            'page' => 'nullable|integer|min:1|max:' . $this->last_page,
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function mount()
    {
        $reportInstance = $this->getReportInstance();

        $this->per_page = $this->per_page ?? $reportInstance->getPagination();
        $this->per_page_list = $reportInstance->getPaginationList();
        $this->filter_list = collect($reportInstance->filters())
            ->map(fn($filter) => $filter->toArray())
            ->all();
    }

    public function searchReport()
    {
        $this->firstPage();
    }

    public function resetReport()
    {
        $this->resetExcept(['report', 'per_page', 'per_page_list', 'filter_list']);
        $this->updatePaginationInfo();
    }

    public function filterReset()
    {
        $this->reset(['filters']);
    }

    public function filterSubmit()
    {
        $this->updatePaginationInfo();
    }

    public function updatedExport(string $format)
    {
        if (! in_array($format, ['print', 'pdf', 'xlsx', 'csv'])) {
            return;
        }

        $token = $this->requestHelper()->setExport($format)->generateToken();

        $this->dispatch('exportEvent', ['url' => route('i-reports.view', ['token' => $token])]);

        $this->reset('export');
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

    public function render()
    {
        return view('i-reports::livewire.report-viewer', [
            'reportUrl' => $this->reportUrl,
        ]);
    }
}
