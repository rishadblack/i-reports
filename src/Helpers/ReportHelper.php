<?php

namespace Rishadblack\IReports\Helpers;

use Rishadblack\IReports\Support\ReportContext;
use Rishadblack\IReports\Views\Column;

/**
 * Static facade over the request-scoped ReportContext. Kept for backwards compatibility.
 */
class ReportHelper
{
    public static function context(): ReportContext
    {
        return app(ReportContext::class);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function setRequestData(array $value): void
    {
        self::context()->setRequestData($value);
    }

    /**
     * @param  array<int, Column>  $columns
     */
    public static function setColumns(array $columns): void
    {
        self::context()->setColumns($columns);
    }

    /**
     * @return array<int, Column>
     */
    public static function getColumns(): array
    {
        return self::context()->getColumns();
    }

    public static function getColumnByName(string $name): ?Column
    {
        return self::context()->getColumnByName($name);
    }

    public static function setReportTitle(string $reportTitle): void
    {
        self::context()->setReportTitle($reportTitle);
    }

    public static function getReportTitle(): ?string
    {
        return self::context()->getReportTitle();
    }

    public static function setHeaderTitle(string $headerTitle): void
    {
        self::context()->setHeaderTitle($headerTitle);
    }

    public static function getHeaderTitle(): ?string
    {
        return self::context()->getHeaderTitle();
    }

    /**
     * @return array<string, mixed>
     */
    public static function getRequestData(): array
    {
        return self::context()->getRequestData();
    }

    public static function getExport(): string
    {
        return self::context()->getExport();
    }

    public static function getPerPage(int $default = 25): int
    {
        return self::context()->getPerPage($default);
    }

    public static function getPage(int $default = 1): int
    {
        return self::context()->getPage($default);
    }

    public static function getReport(): ?string
    {
        return self::context()->getReport();
    }

    /**
     * @return array<string, mixed>
     */
    public static function getFilters(): array
    {
        return self::context()->getFilters();
    }

    public static function getSearch(): string
    {
        return self::context()->getSearch();
    }

    public static function getSortField(): ?string
    {
        return self::context()->getSortField();
    }

    public static function getSortDirection(): string
    {
        return self::context()->getSortDirection();
    }
}
