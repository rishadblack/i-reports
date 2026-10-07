<?php

namespace Rishadblack\IReports\Support;

use Rishadblack\IReports\Views\Column;

/**
 * Request-scoped state shared between the report, the Blade components and the views.
 *
 * The container binds this class as scoped, so Octane and queue workers get a fresh
 * instance per request or job instead of leaking state through static properties.
 */
class ReportContext
{
    public const EXPORTS = ['view', 'inline', 'print', 'pdf', 'xlsx', 'csv'];

    public const DOWNLOADS = ['pdf', 'xlsx', 'csv'];

    /** @var array<string, mixed> */
    protected array $requestData = [];

    /** @var array<int, Column> */
    protected array $columns = [];

    protected ?string $reportTitle = null;

    protected ?string $headerTitle = null;

    /** @var array<string, array<int, string>> */
    protected array $rendered = ['th' => [], 'td' => []];

    /** @var array<string, mixed> */
    protected array $shared = [];

    /**
     * Share a value with the Blade components for this request (aggregates, report, ...).
     */
    public function put(string $key, mixed $value): static
    {
        $this->shared[$key] = $value;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->shared[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setRequestData(array $data): static
    {
        $this->requestData = $data;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequestData(): array
    {
        return $this->requestData;
    }

    /**
     * The raw page setup the user chose in the export dialog (validated by PageSetup::resolve()).
     *
     * @return array<string, mixed>
     */
    public function getPageSetup(): array
    {
        $setup = $this->requestData['page_setup'] ?? [];

        return is_array($setup) ? $setup : [];
    }

    /**
     * A config('i-reports.default_style') entry, with the chosen print/PDF font size applied.
     */
    public function defaultStyle(string $key): string
    {
        $style = (string) config("i-reports.default_style.{$key}", '');
        $setup = $this->get('page_setup');

        // Font size applies to cells only, not to row or stripe styles.
        return $setup instanceof PageSetup && in_array($key, ['th', 'td', 'group', 'aggregate'], true) ? $setup->tableStyle($style) : $style;
    }

    public function getExport(): string
    {
        $export = $this->requestData['export'] ?? 'view';

        return in_array($export, self::EXPORTS, true) ? $export : 'view';
    }

    public function isDownload(): bool
    {
        return in_array($this->getExport(), self::DOWNLOADS, true);
    }

    public function isFullExport(): bool
    {
        return in_array($this->getExport(), ['print', 'pdf', 'xlsx', 'csv'], true);
    }

    public function getPerPage(int $default = 25): int
    {
        $perPage = (int) ($this->requestData['per_page'] ?? 0);

        return $perPage > 0 ? $perPage : $default;
    }

    public function getPage(int $default = 1): int
    {
        $page = (int) ($this->requestData['page'] ?? 0);

        return $page > 0 ? $page : $default;
    }

    public function getTotal(): ?int
    {
        $total = $this->requestData['total'] ?? null;

        return is_numeric($total) ? (int) $total : null;
    }

    public function getReport(): ?string
    {
        $report = $this->requestData['report'] ?? null;

        return is_string($report) && $report !== '' ? $report : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        $filters = $this->requestData['filters'] ?? [];

        return is_array($filters) ? $filters : [];
    }

    public function getSearch(): string
    {
        $search = $this->requestData['search'] ?? '';

        return is_string($search) ? mb_substr(trim($search), 0, 255) : '';
    }

    /**
     * Column names the user switched off in the column picker.
     *
     * @return array<int, string>
     */
    public function getHiddenColumns(): array
    {
        $hidden = $this->requestData['hidden_columns'] ?? [];

        return is_array($hidden) ? array_values(array_filter($hidden, 'is_string')) : [];
    }

    /**
     * Whether a column appears in the given output (current export when null): not hidden by the
     * report, not excluded with hideIn(), and not switched off by the user.
     */
    public function isColumnVisible(Column $column, ?string $export = null): bool
    {
        if ($column->isHiddenIn($export ?? $this->getExport())) {
            return false;
        }

        return ! ($column->isHideable() && in_array($column->getName(), $this->getHiddenColumns(), true));
    }

    public function getSortField(): ?string
    {
        $field = $this->requestData['sort_field'] ?? null;

        return is_string($field) && $field !== '' ? $field : null;
    }

    public function getSortDirection(): string
    {
        $direction = strtolower((string) ($this->requestData['sort_direction'] ?? 'asc'));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
    }

    /**
     * @param  array<int, Column>  $columns
     */
    public function setColumns(array $columns): static
    {
        $this->columns = array_values(array_filter($columns, fn ($column) => $column instanceof Column));

        return $this;
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getColumnByName(string $name): ?Column
    {
        foreach ($this->columns as $column) {
            if ($column->getName() === $name) {
                return $column;
            }
        }

        return null;
    }

    public function setReportTitle(?string $title): static
    {
        $this->reportTitle = $title;

        return $this;
    }

    public function getReportTitle(): ?string
    {
        return $this->reportTitle;
    }

    public function setHeaderTitle(?string $title): static
    {
        $this->headerTitle = $title;

        return $this;
    }

    public function getHeaderTitle(): ?string
    {
        return $this->headerTitle;
    }

    public function markRendered(string $type, string $name): void
    {
        $this->rendered[$type][] = $name;
    }

    public function isRendered(string $type, string $name): bool
    {
        return in_array($name, $this->rendered[$type] ?? [], true);
    }

    public function resetRendered(?string $type = null): void
    {
        if ($type === null) {
            $this->rendered = ['th' => [], 'td' => []];

            return;
        }

        $this->rendered[$type] = [];
    }

    public function reset(): void
    {
        $this->requestData = [];
        $this->columns = [];
        $this->reportTitle = null;
        $this->headerTitle = null;
        $this->shared = [];
        $this->resetRendered();
    }
}
