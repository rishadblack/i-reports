<?php
namespace Rishadblack\IReports\Helpers;

class ReportHelper
{
    protected static $requestData = null;

    public static function setRequestData(array $value): void
    {
        self::$requestData = $value;
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
}
