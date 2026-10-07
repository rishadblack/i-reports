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
        $this->setDefaultSort('customers.name');
    }

    /**
     * @return array<int, Column>
     */
    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->searchable()->sortable(),
            Column::make('City', 'city')->sortable(),
            Column::make('Country', 'country.name')->searchable()->sortable(),
            Column::make('Amount', 'amount')->money('BDT')->sum()->sortable(),
            Column::make('Active', 'active')->boolean('Active', 'Inactive'),
            Column::make('Joined', 'joined_at')->date('d/m/Y')->hideIn('csv'),
            Column::make('Secret', 'secret')->hide(),
            Column::make('Actions', 'actions')->custom()->html()->format(fn ($value, $row) => '<a href="/customers/'.$row->id.'">Edit</a>'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(): array
    {
        return [
            Filter::make('City', 'city')
                ->select(['Dhaka' => 'Dhaka', 'Khulna' => 'Khulna'])
                ->filter(fn (Builder $query, string $city) => $query->where('customers.city', $city)),
            Filter::make('Cities', 'cities')->multiSelect(['Dhaka' => 'Dhaka', 'Khulna' => 'Khulna'])->column('customers.city'),
            Filter::make('Joined', 'joined')->dateRange()->column('customers.joined_at'),
            Filter::make('Amount', 'amount')->numberRange()->column('customers.amount'),
            Filter::make('Active', 'active')->boolean()->column('customers.active'),
            Filter::make('Name contains', 'name')->text()->column('customers.name')->placeholder('Part of the name'),
            Filter::make('Country', 'country_id')->select([1 => 'Bangladesh', 2 => 'India'])->column('customers.country_id'),
            Filter::make('Region', 'region')->select(['north' => 'North'])->dependsOn('country_id')->default('north'),
        ];
    }
}
