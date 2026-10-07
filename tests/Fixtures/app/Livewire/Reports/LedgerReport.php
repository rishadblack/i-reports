<?php

namespace App\Livewire\Reports;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Views\Column;

/**
 * A 0.1.x style report: the Blade view computes a running balance and a closing total.
 */
class LedgerReport extends BaseReportController
{
    public function builder(): Builder
    {
        return Customer::query()->orderBy('customers.name');
    }

    public function configure(): void
    {
        $this->setReportTitle('Ledger');
        $this->setExportSource('view');
        $this->setHeaderView('partials.ledger-header');
    }

    /**
     * @return array<int, Column>
     */
    public function columns(): array
    {
        return [
            Column::make('Name', 'name'),
            Column::make('Amount', 'amount'),
            Column::make('Balance', 'balance')->custom(),
        ];
    }
}
