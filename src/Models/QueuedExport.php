<?php

namespace Rishadblack\IReports\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Rishadblack\IReports\Events\ReportExportUpdated;
use Throwable;

/**
 * An export generated in the background: its status, owner and the stored file.
 *
 * @property int $id
 * @property string $owner
 * @property string $report
 * @property string $format
 * @property string $status
 * @property string $disk
 * @property string|null $path
 * @property string|null $file_name
 * @property int|null $size
 * @property array<string, mixed>|null $request
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 */
class QueuedExport extends Model
{
    public const QUEUED = 'queued';

    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'request' => 'array',
        'size' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('i-reports.queue.table', 'i_reports_exports');
    }

    /**
     * Who may see and download an export: the signed-in user, or the browser session for guests.
     */
    public static function currentOwner(): string
    {
        if (Auth::check()) {
            return 'user:'.Auth::id();
        }

        return 'session:'.(app()->bound('session') ? session()->getId() : 'cli');
    }

    /**
     * @param  Builder<QueuedExport>  $query
     * @return Builder<QueuedExport>
     */
    public function scopeOwnedBy(Builder $query, string $owner): Builder
    {
        return $query->where('owner', $owner);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::QUEUED, self::PROCESSING], true);
    }

    public function isReady(): bool
    {
        return $this->status === self::READY;
    }

    protected static function booted(): void
    {
        static::created(fn (QueuedExport $export) => $export->broadcastStatus());
    }

    /**
     * Tell the owner's browser about the new status (broadcast mode only). A broadcasting
     * failure (e.g. the socket server is down) is reported but never fails the export.
     */
    public function broadcastStatus(): void
    {
        if (config('i-reports.queue.realtime') !== 'broadcast' || ! str_starts_with($this->owner, 'user:')) {
            return;
        }

        try {
            event(new ReportExportUpdated($this));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function markProcessing(): void
    {
        $this->forceFill(['status' => self::PROCESSING, 'started_at' => now(), 'error' => null])->save();
        $this->broadcastStatus();
    }

    public function markReady(string $path): void
    {
        $this->forceFill([
            'status' => self::READY,
            'path' => $path,
            'file_name' => basename($path),
            'size' => Storage::disk($this->disk)->size($path),
            'finished_at' => now(),
        ])->save();
        $this->broadcastStatus();
    }

    public function markFailed(Throwable|string $error): void
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        $this->forceFill(['status' => self::FAILED, 'error' => mb_substr($message, 0, 1000), 'finished_at' => now()])->save();
        $this->broadcastStatus();
    }

    /**
     * Delete the stored file together with the record.
     */
    public function deleteWithFile(): void
    {
        if ($this->path !== null && Storage::disk($this->disk)->exists($this->path)) {
            Storage::disk($this->disk)->delete($this->path);
        }

        $this->delete();
    }

    public function humanSize(): string
    {
        $size = (int) $this->size;

        return match (true) {
            $size >= 1048576 => number_format($size / 1048576, 1).' MB',
            $size >= 1024 => number_format($size / 1024, 0).' KB',
            default => $size.' B',
        };
    }
}
