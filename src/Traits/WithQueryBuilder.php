<?php

namespace Rishadblack\IReports\Traits;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\LazyCollection;
use Rishadblack\IReports\Views\Column;

trait WithQueryBuilder
{
    protected ?Builder $builder = null;

    /** @var array<int, string|Expression> */
    protected array $additionalSelects = [];

    /** @var array<string, string>|null */
    protected ?array $relationTables = null;

    public function setBuilder(Builder $builder): void
    {
        $this->builder = $builder;
    }

    /**
     * @param  string|Expression|array<int, string|Expression>  $selects
     */
    public function setAdditionalSelects(string|Expression|array $selects): static
    {
        $this->additionalSelects = is_array($selects) ? array_values($selects) : [$selects];

        return $this;
    }

    /**
     * @return array<int, string|Expression>
     */
    public function getAdditionalSelects(): array
    {
        return $this->additionalSelects;
    }

    public function getBuilder(): Builder
    {
        if ($this->builder === null) {
            $this->setBuilder($this->builder());
        }

        return $this->builder;
    }

    /**
     * A new, unmodified query from builder().
     */
    public function freshBuilder(): Builder
    {
        return $this->builder();
    }

    /**
     * The filtered, searched and sorted query with relation joins but without selects.
     */
    public function baseBuilder(?Builder $builder = null): Builder
    {
        $this->setBuilder($builder ?? $this->builder());
        $this->joinRelations();
        $this->applyFilters();
        $this->applySearch();
        $this->applySort();

        return $this->getBuilder();
    }

    /**
     * The query used to fetch rows: base query plus selects and additionalQuery().
     */
    public function exportBuilder(): Builder
    {
        $this->baseBuilder();
        $this->selectFields();
        $this->setBuilder($this->additionalQuery($this->getBuilder()));
        $this->selectPrimaryKey();

        return $this->getBuilder();
    }

    /**
     * The export rows, read from the database in chunks.
     *
     * @return LazyCollection<int, Model>
     */
    public function exportRows(int $chunkSize): LazyCollection
    {
        return $this->withStableOrder($this->exportBuilder())->lazy($chunkSize);
    }

    /**
     * Give an unordered query a deterministic order for chunked reads (lazy(), forPage()).
     * Laravel's lazy() would otherwise order by the primary key, which MySQL strict mode
     * rejects for GROUP BY and DISTINCT queries; those are ordered by their own columns.
     */
    public function withStableOrder(Builder $builder): Builder
    {
        $query = $builder->getQuery();

        if (! empty($query->orders) || ! empty($query->unionOrders)) {
            return $builder;
        }

        if (! empty($query->groups)) {
            foreach ($query->groups as $group) {
                $builder->orderBy($group);
            }

            return $builder;
        }

        if ($query->distinct) {
            foreach ((array) $query->columns as $column) {
                if (is_string($column) && ! str_contains($column, '*')) {
                    return $builder->orderBy(preg_replace('/\s+as\s+.*$/i', '', $column) ?? $column);
                }
            }

            return $builder;
        }

        return $builder->orderBy($builder->getModel()->qualifyColumn($builder->getModel()->getKeyName()));
    }

    /**
     * Always load the model key so format(), map() and links can use $row->id, unless the
     * query is grouped (adding a column would break GROUP BY) or already selects it.
     */
    protected function selectPrimaryKey(): void
    {
        $builder = $this->getBuilder();
        $query = $builder->getQuery();
        $keyName = $builder->getModel()->getKeyName();

        if (! empty($query->groups) || $query->distinct || ! empty($query->unions) || ! is_string($keyName) || $keyName === '') {
            return;
        }

        foreach ((array) $query->columns as $column) {
            // A raw select (selectRaw, DB::raw) may aggregate; adding the key would break it.
            if (! is_string($column)) {
                return;
            }

            if ($column === '*' || str_ends_with($column, '.*') || $column === $keyName || str_ends_with($column, ' as '.$keyName) || $column === $builder->getModel()->qualifyColumn($keyName)) {
                return;
            }
        }

        $builder->addSelect($builder->getModel()->qualifyColumn($keyName));
    }

    /**
     * Count of rows matching the current filters and search.
     */
    public function total(): int
    {
        return $this->baseBuilder()->toBase()->getCountForPagination();
    }

    /**
     * Paginate. When the viewer already counted the rows, the total travels in the token and
     * no second COUNT query runs.
     */
    public function paginate(Builder $query): LengthAwarePaginator
    {
        $perPage = min($this->context()->getPerPage($this->getPagination()), (int) config('i-reports.max_per_page', 1000));
        $page = $this->context()->getPage();
        $total = $this->context()->getTotal();

        if ($total === null) {
            return $query->paginate($perPage, ['*'], 'page', $page);
        }

        $items = $total > 0 ? $query->forPage($page, $perPage)->get() : $query->getModel()->newCollection();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    /**
     * Aggregates (sum, avg, ...) for the columns that asked for one, over the whole filtered set.
     *
     * @return array<string, mixed>
     */
    public function aggregates(): array
    {
        $columns = $this->getColumns()->filter(fn (Column $column) => $column->hasAggregate() && ! $column->isCustom());

        if ($columns->isEmpty()) {
            return [];
        }

        $query = $this->baseBuilder(clone $this->freshBuilder())->reorder();
        $query->getQuery()->columns = null;
        $grammar = $query->getQuery()->getGrammar();

        foreach ($columns as $index => $column) {
            $function = strtoupper($column->getAggregate());
            $query->addSelect(new Expression("{$function}(".$grammar->wrap($column->getColumn()).') as '.$grammar->wrap("aggregate_{$index}")));
        }

        $row = $query->toBase()->first();
        $results = [];

        foreach ($columns as $index => $column) {
            $results[$column->getName()] = $row->{"aggregate_{$index}"} ?? null;
        }

        return $results;
    }

    protected function applyFilters(): Builder
    {
        $values = $this->context()->getFilters();

        foreach ($this->getFilters() as $filter) {
            $key = $filter->key();

            if (array_key_exists($key, $values)) {
                $filter->apply($this->getBuilder(), $values[$key]);
            }
        }

        return $this->getBuilder();
    }

    protected function applySearch(): Builder
    {
        $search = $this->context()->getSearch();

        if ($search === '') {
            return $this->getBuilder();
        }

        $searchableColumns = $this->getColumns()
            ->filter(fn (Column $column) => $column->isSearchable() && ! $column->isCustom())
            ->map(fn (Column $column) => $column->getColumn())
            ->all();

        $extraFields = $this->getSearchField();

        if (count($searchableColumns) > 0 || count($extraFields) > 0) {
            $this->getBuilder()->where(function (Builder $query) use ($searchableColumns, $extraFields, $search) {
                foreach ($searchableColumns as $qualifiedColumn) {
                    $query->orWhere($qualifiedColumn, $this->likeOperator($query), "%{$search}%");
                }

                foreach ($extraFields as $field) {
                    $this->applySearchField($query, $field, $search);
                }
            });
        }

        return $this->setBuilderAndGet($this->search($this->getBuilder(), $search));
    }

    /**
     * Search an extra field: "field", "col->json.key" or "relation.nested.field".
     */
    protected function applySearchField(Builder $query, string $field, string $search): void
    {
        $segments = explode('.', $field);
        $column = array_pop($segments);
        $relationPath = implode('.', $segments);

        if ($relationPath === '') {
            $query->orWhere($query->getModel()->getTable().'.'.$column, $this->likeOperator($query), "%{$search}%");

            return;
        }

        $query->orWhereHas($relationPath, function (Builder $relationQuery) use ($column, $search) {
            $relationQuery->where($relationQuery->getModel()->getTable().'.'.$column, $this->likeOperator($relationQuery), "%{$search}%");
        });
    }

    protected function likeOperator(Builder $query): string
    {
        $connection = $query->getConnection();

        return $connection instanceof Connection && $connection->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    protected function applySort(): Builder
    {
        $requested = $this->context()->getSortField();
        $direction = $this->context()->getSortDirection();
        $sortColumn = null;

        if ($requested !== null) {
            $column = $this->getColumnByName($requested);

            if ($column !== null && $column->isSortable() && ! $column->isCustom()) {
                $sortColumn = $column->getColumn();
            }
        }

        if ($sortColumn === null) {
            [$sortColumn, $defaultDirection] = $this->getDefaultSortField();
            $direction = strtolower($defaultDirection ?? 'asc');
            $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
        }

        if ($sortColumn) {
            $this->getBuilder()->orderBy($sortColumn, $direction);
        }

        return $this->getBuilder();
    }

    /**
     * The column the current request sorts by, when it is allowed.
     *
     * @return array{0: string, 1: string}|null
     */
    public function getActiveSort(): ?array
    {
        $requested = $this->context()->getSortField();

        if ($requested === null) {
            return null;
        }

        $column = $this->getColumnByName($requested);

        if ($column === null || ! $column->isSortable()) {
            return null;
        }

        return [$column->getName(), $this->context()->getSortDirection()];
    }

    protected function selectFields(): Builder
    {
        $builder = $this->getBuilder();

        foreach ($this->getAdditionalSelects() as $select) {
            $builder->addSelect($select);
        }

        foreach ($this->getSelectedColumnsForQuery() as $column) {
            $builder->addSelect($column->getColumn().' as '.$column->getColumnSelectName());
        }

        return $builder;
    }

    protected function joinRelations(): Builder
    {
        foreach ($this->getSelectedColumnsForQuery() as $column) {
            if ($column->hasRelations()) {
                $this->joinRelation($column);
            }
        }

        return $this->getBuilder();
    }

    protected function joinRelation(Column $column): Builder
    {
        $tableAlias = null;
        $lastAlias = null;
        $lastModel = $this->getBuilder()->getModel();

        foreach ($column->getRelations() as $i => $relationPart) {
            $relation = $lastModel->{$relationPart}();
            $tableAlias = $this->getTableAlias($tableAlias, $relationPart);
            $table = null;
            $foreign = null;
            $other = null;

            if ($relation instanceof HasOne || $relation instanceof MorphOne) {
                $table = "{$relation->getRelated()->getTable()} AS {$tableAlias}";
                $foreign = "{$tableAlias}.{$relation->getForeignKeyName()}";
                $other = $i === 0 ? $relation->getQualifiedParentKeyName() : "{$lastAlias}.{$relation->getLocalKeyName()}";
            } elseif ($relation instanceof BelongsTo) {
                $table = "{$relation->getRelated()->getTable()} AS {$tableAlias}";
                $foreign = $i === 0 ? $relation->getQualifiedForeignKeyName() : "{$lastAlias}.{$relation->getForeignKeyName()}";
                $other = "{$tableAlias}.{$relation->getOwnerKeyName()}";
            }

            if ($table !== null) {
                $this->performJoin($table, $foreign, $other);

                if ($relation instanceof MorphOne) {
                    $this->getBuilder()->where("{$tableAlias}.{$relation->getMorphType()}", $relation->getMorphClass());
                }
            }

            $lastAlias = $tableAlias;
            $lastModel = $relation->getRelated();
        }

        return $this->getBuilder();
    }

    protected function performJoin(string $table, string $foreign, string $other, string $type = 'left'): Builder
    {
        $joined = array_map(fn ($join) => $join->table, $this->getBuilder()->getQuery()->joins ?? []);

        if (! in_array($table, $joined, true)) {
            $this->getBuilder()->join($table, $foreign, '=', $other, $type);
        }

        return $this->getBuilder();
    }

    /**
     * Resolve the join alias for a relation column without touching the query.
     */
    protected function getTableForColumn(Column $column): ?string
    {
        $alias = null;
        $model = $this->getBuilder()->getModel();

        foreach ($column->getRelations() as $relationPart) {
            $relation = $model->{$relationPart}();

            if ($relation instanceof HasOne || $relation instanceof BelongsTo || $relation instanceof MorphOne) {
                $alias = $this->getTableAlias($alias, $relationPart);
            }

            $model = $relation->getRelated();
        }

        return $alias;
    }

    protected function getTableAlias(?string $currentTableAlias, string $relationPart): string
    {
        return $currentTableAlias ? $currentTableAlias.'_'.$relationPart : $relationPart;
    }

    protected function setBuilderAndGet(Builder $builder): Builder
    {
        $this->setBuilder($builder);

        return $builder;
    }
}
