<?php
namespace Rishadblack\IReports\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Rishadblack\IReports\Helpers\ReportHelper;
use Rishadblack\IReports\Views\Column;

trait WithQueryBuilder
{
    protected Builder $builder;

    protected ?string $primaryKey;

    protected array $additionalSelects = [];

    public function setPrimaryKey(string $primaryKey)
    {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    public function setBuilder(Builder $builder): void
    {
        $this->builder = $builder;
    }

    public function setAdditionalSelects(string | array $selects): self
    {
        if (! is_array($selects)) {
            $selects = [$selects];
        }

        $this->additionalSelects = $selects;

        return $this;
    }

    public function getAdditionalSelects(): array
    {
        return $this->additionalSelects;
    }

    public function getBuilder(): Builder
    {
        if (! isset($this->builder)) {
            $this->setBuilder($this->builder());
        }

        return $this->builder;
    }

    public function baseBuilder(): Builder
    {
        $this->setBuilder($this->builder());
        $this->setBuilder($this->joinRelations());
        $this->setBuilder($this->applyFilters());
        $this->setBuilder($this->applySearch());
        $this->setBuilder($this->applySort());

        return $this->getBuilder();
    }

    public function paginate(Builder $query): LengthAwarePaginator
    {
        $perPage = ReportHelper::getPerPage($this->getPagination());

        return $query->paginate($perPage)->appends(request()->except('page'));
    }

    protected function applyFilters(): Builder
    {
        $filters = ReportHelper::getFilters();

        foreach ($this->filters() as $filter) {
            $field = $filter->key();

            if (array_key_exists($field, $filters) && filled($filters[$field])) {
                $filter->apply($this->getBuilder(), $filters[$field]);
            }
        }

        return $this->getBuilder();
    }

    protected function applySearch(): Builder
    {
        $search = ReportHelper::getSearch();

        // Get searchable column names from columns()
        $searchableColumns = collect($this->columns())
            ->filter(fn($column) => $column->isSearchable())
            ->map(fn($column) => $column->getColumnSelectName())
            ->all();

        // Merge with $this->getSearchField() (which may return extra fields)
        $fields = array_values(array_unique(array_merge($this->getSearchField(), $searchableColumns)));

        if ($search && ! empty($search)) {
            if (count($fields) > 0) {
                $this->applySearchable($this->getBuilder(), $fields, $search);
            }
            return $this->search($this->getBuilder(), $search);
            // return $this->getBuilder(), $search;
        }

        return $this->getBuilder();
    }

    protected function applySort(): Builder
    {
        $sortField = ReportHelper::getSortField();
        $sortDirection = ReportHelper::getSortDirection();

        $allowedDirections = ['asc', 'desc'];
        if (! in_array($sortDirection, $allowedDirections)) {
            $sortDirection = 'asc';
        }

        if ($sortField) {
            $this->getBuilder()->orderBy($sortField, $sortDirection);
        }

        return $this->getBuilder();
    }

    protected function selectFields(): Builder
    {
        // Load any additional selects that were not already columns
        foreach ($this->getAdditionalSelects() as $select) {
            $this->setBuilder($this->getBuilder()->addSelect($select));
        }

        foreach ($this->getSelectedColumnsForQuery() as $column) {
            $this->setBuilder($this->getBuilder()->addSelect($column->getColumn() . ' as ' . $column->getColumnSelectName()));
        }

        return $this->getBuilder();
    }

    protected function joinRelations(): Builder
    {
        foreach ($this->getSelectedColumnsForQuery() as $column) {
            if ($column->hasRelations()) {
                $this->setBuilder($this->joinRelation($column));
            }
        }
        return $this->getBuilder();
    }

    protected function joinRelation(Column $column): Builder
    {
        $this->setBuilder($this->getBuilder()->with($column->getRelationString()));

        $table = false;
        $tableAlias = false;
        $foreign = false;
        $other = false;
        $lastAlias = false;
        $lastQuery = clone $this->getBuilder();

        foreach ($column->getRelations() as $i => $relationPart) {
            $model = $lastQuery->getRelation($relationPart);
            $tableAlias = $this->getTableAlias($tableAlias, $relationPart);

            switch (true) {
                case $model instanceof MorphOne:
                case $model instanceof HasOne:
                    $table = "{$model->getRelated()->getTable()} AS $tableAlias";
                    $foreign = "$tableAlias.{$model->getForeignKeyName()}";
                    $other = $i === 0
                    ? $model->getQualifiedParentKeyName()
                    : $lastAlias . '.' . $model->getLocalKeyName();

                    break;

                case $model instanceof BelongsTo:
                    $table = "{$model->getRelated()->getTable()} AS $tableAlias";
                    $foreign = $i === 0
                    ? $model->getQualifiedForeignKeyName()
                    : $lastAlias . '.' . $model->getForeignKeyName();

                    $other = "$tableAlias.{$model->getOwnerKeyName()}";

                    break;
            }

            if ($table) {
                $this->setBuilder($this->performJoin($table, $foreign, $other));
            }

            $lastAlias = $tableAlias;
            $lastQuery = $model->getQuery();
        }

        return $this->getBuilder();
    }

    protected function performJoin(string $table, string $foreign, string $other, string $type = 'left'): Builder
    {
        $joins = [];

        foreach ($this->getBuilder()->getQuery()->joins ?? [] as $join) {
            $joins[] = $join->table;
        }

        if (! in_array($table, $joins, true)) {
            $this->setBuilder($this->getBuilder()->join($table, $foreign, '=', $other, $type));
        }

        return $this->getBuilder();
    }

    protected function applySearchable(Builder $query, array $attributes, string $searchTerm): Builder
    {
        $query->where(function (Builder $query) use ($attributes, $searchTerm) {
            $model = $query->getModel();
            $table = $model->getTable();

            foreach (Arr::wrap($attributes) as $attribute) {
                $query->orWhere(function (Builder $subQuery) use ($attribute, $searchTerm, $table) {

                    // Split into relation path + field
                    $segments = explode('.', $attribute);
                    $field = array_pop($segments);           // last part is always the field
                    $relationPath = implode('.', $segments); // may be empty for direct field

                    if ($relationPath) {
                        // For relations (can be multi-level)
                        $subQuery->orWhereHas($relationPath, function (Builder $relationQuery) use ($field, $searchTerm) {
                            $relationTable = $relationQuery->getModel()->getTable();

                            if (str_contains($field, '->')) {
                                [$column, $jsonKey] = explode('->', $field, 2);
                                $jsonPath = "$.$jsonKey";

                                $relationQuery->whereRaw(
                                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT(`{$relationTable}`.`{$column}`, ?))) LIKE LOWER(?)",
                                    [$jsonPath, "%{$searchTerm}%"]
                                );
                            } else {
                                $relationQuery->whereRaw(
                                    "LOWER(`{$relationTable}`.`{$field}`) LIKE LOWER(?)",
                                    ["%{$searchTerm}%"]
                                );
                            }
                        });
                    } else {
                        // For direct fields on the main model
                        if (str_contains($field, '->')) {
                            [$column, $jsonKey] = explode('->', $field, 2);
                            $jsonPath = "$.$jsonKey";

                            $subQuery->whereRaw(
                                "LOWER(JSON_UNQUOTE(JSON_EXTRACT(`{$table}`.`{$column}`, ?))) LIKE LOWER(?)",
                                [$jsonPath, "%{$searchTerm}%"]
                            );
                        } else {
                            $subQuery->whereRaw(
                                "LOWER(`{$table}`.`{$field}`) LIKE LOWER(?)",
                                ["%{$searchTerm}%"]
                            );
                        }
                    }
                });
            }
        });

        return $query;
    }

    protected function getTableForColumn(Column $column): ?string
    {
        $table = null;
        $lastQuery = clone $this->getBuilder();

        foreach ($column->getRelations() as $relationPart) {

            $model = $lastQuery->getRelation($relationPart);
            if ($model instanceof HasOne || $model instanceof BelongsTo || $model instanceof MorphOne) {

                $table = $this->getTableAlias($table, $relationPart);
            }

            $lastQuery = $model->getQuery();
        }
        return $table;
    }

    protected function getTableAlias(?string $currentTableAlias, string $relationPart): string
    {
        if (! $currentTableAlias) {
            return $relationPart;
        }

        return $currentTableAlias . '_' . $relationPart;
    }
}
