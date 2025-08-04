<?php
namespace Rishadblack\IReports\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Rishadblack\IReports\Helpers\ReportHelper;

trait WithQueryBuilder
{
    public function baseBuilder(Request $request): Builder
    {
        $builder = $this->builder(); // Start with the base quesy.
        $builder = $this->applyFilters($builder, $request);
        $builder = $this->applySearch($builder, $request);
        $builder = $this->applySort($builder, $request);

        return $builder;
    }

    public function paginate(Builder $query): LengthAwarePaginator
    {
        $perPage = ReportHelper::getPerPage($this->getPagination());

        return $query->paginate($perPage)->appends(request()->except('page'));
    }

    protected function applyFilters(Builder $builder, Request $request): Builder
    {
        foreach ($this->filters() as $filter) {
            $field = $filter->getField();

            if ($request->filled($field)) {
                $value = $request->input($field);
                $filter->apply($builder, $value);
            }
        }

        return $builder;
    }

    protected function applySearch(Builder $builder, Request $request): Builder
    {
        $search = (string) $request->input('search');

        $fields = $this->getSearchField();

        if ($search && ! empty($search)) {
            if (count($fields) > 0) {
                $builder = $this->applySearchable($builder, $fields, $search);
            }
            return $this->search($builder, $search, $request);
        }

        return $builder;
    }

    protected function applySort(Builder $builder, Request $request): Builder
    {
        $sortField = $request->input('sort_field');
        $sortDirection = strtolower($request->input('sort_direction', 'asc'));

        $allowedDirections = ['asc', 'desc'];
        if (! in_array($sortDirection, $allowedDirections)) {
            $sortDirection = 'asc';
        }

        if ($sortField) {
            $builder->orderBy($sortField, $sortDirection);
        }

        return $builder;
    }

    protected function applySearchable(Builder $query, array $attributes, string $searchTerm): Builder
    {
        $query->where(function (Builder $query) use ($attributes, $searchTerm) {
            $model = $query->getModel();
            $table = $model->getTable();

            foreach (Arr::wrap($attributes) as $attribute) {
                $query->when(
                    str_contains($attribute, '.'),
                    function (Builder $query) use ($attribute, $searchTerm, $model) {
                        $segments = explode('.', $attribute);
                        $relation = implode('.', array_slice($segments, 0, -1));
                        $field = end($segments);

                        $relationModel = $model->$relation()->getRelated();
                        $relationTable = $relationModel->getTable();

                        $query->orWhereHas($relation, function (Builder $query) use ($field, $searchTerm, $relationTable) {
                            if (str_contains($field, '->')) {
                                [$column, $jsonKey] = explode('->', $field, 2);
                                $jsonPath = "$.$jsonKey";

                                $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(`{$relationTable}`.`{$column}`, ?))) LIKE LOWER(?)", [$jsonPath,
                                    "%{$searchTerm}%"]);
                            } else {
                                $query->whereRaw("LOWER(`{$relationTable}`.`{$field}`) LIKE LOWER(?)", ["%{$searchTerm}%"]);
                            }
                        });
                    },
                    function (Builder $query) use ($attribute, $searchTerm, $table) {
                        if (str_contains($attribute, '->')) {
                            [$column, $jsonKey] = explode('->', $attribute, 2);
                            $jsonPath = "$.$jsonKey";

                            $query->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(`{$table}`.`{$column}`, ?))) LIKE LOWER(?)", [$jsonPath,
                                "%{$searchTerm}%"]);
                        } else {
                            $query->orWhereRaw("LOWER(`{$table}`.`{$attribute}`) LIKE LOWER(?)", ["%{$searchTerm}%"]);
                        }
                    }
                );
            }
        });

        return $query;
    }
}
