<?php

namespace App\Livewire\Reports;

class SecretReport extends CustomersReport
{
    public function configure(): void
    {
        parent::configure();

        $this->setReportTitle('Secret');
    }

    public function authorize(): bool
    {
        return false;
    }
}
