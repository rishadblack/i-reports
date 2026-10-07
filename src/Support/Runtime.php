<?php

namespace Rishadblack\IReports\Support;

/**
 * Process-level preparation for report requests and exports.
 */
class Runtime
{
    /**
     * Turn off Laravel Debugbar for this request. Its view and model collectors keep a copy of
     * every rendered cell, which exhausts memory on large reports, and it would inject into exports.
     */
    public static function disableDebugbar(): void
    {
        if (app()->bound('debugbar') && is_object($debugbar = app('debugbar')) && method_exists($debugbar, 'disable')) {
            $debugbar->disable();
        }
    }

    /**
     * Raise the memory and time limits for a full export (print, PDF, Excel, CSV).
     */
    public static function prepareForExport(): void
    {
        self::disableDebugbar();

        $memoryLimit = config('i-reports.export_memory_limit');

        if (is_string($memoryLimit) && $memoryLimit !== '' && self::toBytes($memoryLimit) > self::toBytes((string) ini_get('memory_limit'))) {
            @ini_set('memory_limit', $memoryLimit);
        }

        $timeLimit = (int) config('i-reports.export_time_limit', 0);

        if ($timeLimit > 0 && function_exists('set_time_limit')) {
            @set_time_limit($timeLimit);
        }
    }

    public static function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }
}
