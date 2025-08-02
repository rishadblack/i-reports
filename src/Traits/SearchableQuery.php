<?php
namespace Rishadblack\IReports\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

trait SearchableQuery
{
    public function applySearchable(Builder $query, array $attributes, string $searchTerm): Builder
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