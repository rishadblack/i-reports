<?php

namespace Rishadblack\IReports\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A saved set of filters, search and sort for one report and one user.
 *
 * @property int $id
 * @property int|string|null $user_id
 * @property string $report
 * @property string $name
 * @property array<string, mixed>|null $state
 */
class ReportPreset extends Model
{
    protected $guarded = [];

    protected $casts = [
        'state' => 'array',
    ];

    public function getTable(): string
    {
        return (string) config('i-reports.presets.table', 'i_reports_presets');
    }

    /**
     * @param  Builder<ReportPreset>  $query
     * @return Builder<ReportPreset>
     */
    public function scopeForUser(Builder $query, int|string|null $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
