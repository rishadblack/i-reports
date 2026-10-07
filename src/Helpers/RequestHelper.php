<?php

namespace Rishadblack\IReports\Helpers;

use Rishadblack\IReports\Services\ReportTokenManager;
use Rishadblack\IReports\Support\ReportContext;

/**
 * Value object describing one report request: which report, filters, search, sort, page and export.
 */
class RequestHelper
{
    /** @var array<string, mixed> */
    protected array $filters = [];

    protected string $search = '';

    protected string $export = '';

    protected int $perPage = 25;

    protected int $page = 1;

    protected ?int $total = null;

    protected string $report = '';

    protected ?string $sortField = null;

    protected string $sortDirection = 'asc';

    /** @var array<int, string> */
    protected array $hiddenColumns = [];

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(array $params = [])
    {
        $this->setHiddenColumns(is_array($params['hidden_columns'] ?? null) ? $params['hidden_columns'] : []);
        $this->filters = is_array($params['filters'] ?? null) ? $params['filters'] : [];
        $this->search = is_string($params['search'] ?? null) ? $params['search'] : '';
        $this->export = is_string($params['export'] ?? null) ? $params['export'] : '';
        $this->perPage = max(1, (int) ($params['per_page'] ?? 25));
        $this->page = max(1, (int) ($params['page'] ?? 1));
        $this->total = isset($params['total']) && is_numeric($params['total']) ? (int) $params['total'] : null;
        $this->report = is_string($params['report'] ?? null) ? $params['report'] : '';
        $this->sortField = is_string($params['sort_field'] ?? null) && $params['sort_field'] !== '' ? $params['sort_field'] : null;
        $this->setSortDirection((string) ($params['sort_direction'] ?? 'asc'));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function setFilters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    public function setSearch(string $search): self
    {
        $this->search = $search;

        return $this;
    }

    public function setExport(string $export): self
    {
        $this->export = $export;

        return $this;
    }

    public function setPerPage(int $perPage): self
    {
        $this->perPage = max(1, $perPage);

        return $this;
    }

    public function setPage(int $page): self
    {
        $this->page = max(1, $page);

        return $this;
    }

    public function setTotal(?int $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function setReport(string $report): self
    {
        $this->report = $report;

        return $this;
    }

    public function setSort(?string $field, string $direction = 'asc'): self
    {
        $this->sortField = $field ?: null;

        return $this->setSortDirection($direction);
    }

    /**
     * @param  array<int, mixed>  $columns
     */
    public function setHiddenColumns(array $columns): self
    {
        $this->hiddenColumns = array_values(array_unique(array_filter($columns, fn ($name) => is_string($name) && $name !== '')));

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getHiddenColumns(): array
    {
        return $this->hiddenColumns;
    }

    public function setSortDirection(string $direction): self
    {
        $direction = strtolower($direction);
        $this->sortDirection = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getSearch(): string
    {
        return $this->search;
    }

    public function getExport(): string
    {
        return $this->export;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function getReport(): string
    {
        return $this->report;
    }

    public function getSortField(): ?string
    {
        return $this->sortField;
    }

    public function getSortDirection(): string
    {
        return $this->sortDirection;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'search' => $this->search,
            'export' => $this->export ?: 'view',
            'per_page' => $this->perPage,
            'page' => $this->page,
            'total' => $this->total,
            'report' => $this->report,
            'sort_field' => $this->sortField,
            'sort_direction' => $this->sortDirection,
            'hidden_columns' => $this->hiddenColumns,
        ];
    }

    /**
     * Generate a signed, expiring token that carries this request.
     */
    public function generateToken(?int $ttlMinutes = null): string
    {
        return ReportTokenManager::store($this->toArray(), $ttlMinutes);
    }

    /**
     * Make this request the current one for the report context.
     */
    public function storeGlobally(): void
    {
        app(ReportContext::class)->setRequestData($this->toArray());
    }
}
