<?php
namespace Rishadblack\IReports\Helpers;

use Rishadblack\IReports\Views\Column;

class ReportHelper
{
    protected static $requestData = null;
    protected static $columns     = [];

    public static function setRequestData(array $value): void
    {
        self::$requestData = $value;
    }

    public static function setColumns(array $columns): void
    {
        self::$columns = $columns;
    }

    public static function getColumns(): array
    {
        return self::$columns;
    }

    public static function getColumnByName(string $name): ?Column
    {
        foreach (self::getColumns() as $column) {
            if ($column->getName() === $name) {
                return $column;
            }
        }
        return null;
    }

    public static function getRequestData(): ?array
    {
        return self::$requestData;
    }

    public static function getExport(): ?string
    {
        return self::$requestData['export'] ?? null;
    }

    public static function getPerPage(int $default = 25): int
    {
        return self::$requestData['per_page'] ?? $default;
    }

    public static function getPage(int $default = 1): int
    {
        return self::$requestData['page'] ?? $default;
    }

    public static function getReport(): ?string
    {
        return self::$requestData['report'] ?? null;
    }

    public static function getFilters(): array
    {
        return self::$requestData ?? [];
    }

    public static function getSearch(): string
    {
        $search = self::$requestData['search'] ?? '';
        return is_string($search) ? trim($search) : '';
    }

    public static function getSortField(): ?string
    {
        return self::$requestData['sort_field'] ?? null;
    }

    public static function getSortDirection(): string
    {
        $direction = strtolower(self::$requestData['sort_direction'] ?? 'asc');
        return in_array($direction, ['asc', 'desc']) ? $direction : 'asc';
    }
}
