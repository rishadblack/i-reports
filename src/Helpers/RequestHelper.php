<?php
namespace Rishadblack\IReports\Helpers;

use Rishadblack\IReports\Services\ReportTokenManager;

class RequestHelper
{
    protected array $filters = [];
    protected string $search = '';
    protected string $export = '';
    protected int $perPage   = 25;
    protected int $page      = 1;
    protected string $report = '';

    public function __construct(array $params = [])
    {
        $this->filters = $params['filters'] ?? [];
        $this->search = $params['search'] ?? '';
        $this->export = $params['export'] ?? '';
        $this->perPage = $params['per_page'] ?? 25;
        $this->page = $params['page'] ?? 1;
        $this->report = $params['report'] ?? '';
    }

    // Setters for fluent usage if needed
    public function setFilters(array $filters): self
    {$this->filters = $filters;return $this;}
    public function setSearch(string $search): self
    {$this->search = $search;return $this;}
    public function setExport(string $export): self
    {$this->export = $export;return $this;}
    public function setPerPage(int $perPage): self
    {$this->perPage = $perPage;return $this;}
    public function setPage(int $page): self
    {$this->page = $page;return $this;}
    public function setReport(string $report): self
    {$this->report = $report;return $this;}

    // Getters
    public function getFilters(): array
    {return $this->filters;}
    public function getSearch(): string
    {return $this->search;}
    public function getExport(): string
    {return $this->export;}
    public function getPerPage(): int
    {return $this->perPage;}
    public function getPage(): int
    {return $this->page;}
    public function getReport(): string
    {return $this->report;}

    // Compose all request data for token or query building
    public function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'search' => $this->search,
            'export' => $this->export ?: 'view',
            'per_page' => $this->perPage,
            'page' => $this->page,
            'report' => $this->report,
        ];
    }

    // Generate a report token for URL or export
    public function generateToken(int $ttlMinutes = 10): string
    {
        return ReportTokenManager::store($this->toArray(), $ttlMinutes);
    }

    // Save current data to ReportHelper for global static access
    public function storeGlobally(): void
    {
        ReportHelper::setRequestData($this->toArray());
    }
}
