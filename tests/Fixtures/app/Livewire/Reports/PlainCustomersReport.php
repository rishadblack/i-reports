<?php

namespace App\Livewire\Reports;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Views\Column;

/**
 * A report with no Blade view of its own: it renders through the package's
 * default view (i-reports::default-report).
 */
class PlainCustomersReport extends BaseReportController
{
    public function builder(): Builder
    {
        return Customer::query();
    }

    public function configure(): void
    {
        $this->setReportTitle('Plain Customers');
        $this->setPagination(10);
        $this->setDefaultSort('customers.name');
    }

    /**
     * @return array<int, Column>
     */
    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->searchable()->sortable(),
            Column::make('Amount', 'amount')->number()->sum(),
        ];
    }
}
