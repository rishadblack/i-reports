<?php
namespace Rishadblack\IReports\Helpers;

class ReportExportHelper
{
    protected static $export = null;

    public static function setExport($value)
    {
        self::$export = $value;
    }

    public static function getExport()
    {
        return self::$export;
    }
}
