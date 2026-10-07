<?php

namespace App\Livewire\Reports;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Rishadblack\IReports\BaseReportController;
use Rishadblack\IReports\Views\Column;
use Rishadblack\IReports\Views\Filter;

class CustomersReport extends BaseReportController
{
    public function builder(): Builder
    {
        return Customer::query();
    }

    public function configure(): void
    {
        $this->setReportTitle('Customer List');
        $this->setPagination(2);
        $this->setDefaultSort('name');
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->searchable(),
            Column::make('City', 'city'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::make('City', 'city')
                ->select(['Dhaka' => 'Dhaka', 'Khulna' => 'Khulna'])
                ->filter(fn (Builder $query, string $city) => $query->where('city', $city)),
        ];
    }
}
