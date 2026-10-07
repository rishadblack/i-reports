<?php

namespace App\Livewire\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Rishadblack\IReports\Views\Column;

/**
 * Exercises map(), summaries(), additionalData(), additionalQuery() and the search() hook.
 */
class MappedCustomersReport extends CustomersReport
{
    public function configure(): void
    {
        parent::configure();

        $this->setReportTitle('Mapped Customers');
        $this->setSearchField(['country.name']);
        $this->setAdditionalSelects('customers.id as customer_id');
    }

    /**
     * @return array<int, Column>
     */
    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->sortable(),
            Column::make('Shout', 'shout')->custom()->format(fn ($value, $row) => $row->shout),
        ];
    }

    public function map(Collection $collection): Collection
    {
        return $collection->each(fn ($row) => $row->shout = strtoupper($row->name).'!');
    }

    public function summaries(Builder $builder): array
    {
        return ['rows' => $builder->count()];
    }

    public function additionalData(): array
    {
        return ['note' => 'extra-note'];
    }

    public function additionalQuery(Builder $builder): Builder
    {
        return $builder->where('customers.name', '!=', 'Nobody');
    }

    public function search(Builder $builder, string $search): Builder
    {
        return $builder->orWhere('customers.city', $search);
    }
}
