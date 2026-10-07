<?php

namespace App\Livewire\Reports;

class GroupedCustomersReport extends CustomersReport
{
    public function configure(): void
    {
        parent::configure();

        $this->setReportTitle('Grouped Customers');
        $this->setPagination(10);
        $this->setDefaultSort('customers.city');
        $this->setGroupBy('city');
        $this->setExcelMode('view');
    }
}
