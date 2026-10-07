<?php

namespace Rishadblack\IReports\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Rishadblack\IReports\Models\QueuedExport;

/**
 * Broadcast (Reverb, Pusher, Ably, …) whenever a background export changes status, so the
 * viewer updates instantly instead of polling. Sent on the owner's private channel:
 * "{queue.broadcast_channel}.{userId}" as ".report-export.updated". Guests are never
 * broadcast to (they cannot join private channels); their viewer keeps polling.
 */
class ReportExportUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public int $id;

    public string $report;

    public string $format;

    public string $status;

    public string $owner;

    public function __construct(QueuedExport $export)
    {
        $this->id = $export->id;
        $this->report = $export->report;
        $this->format = $export->format;
        $this->status = $export->status;
        $this->owner = $export->owner;
    }

    /**
     * The private channel name for a user id.
     */
    public static function channelFor(int|string $userId): string
    {
        return rtrim((string) config('i-reports.queue.broadcast_channel', 'i-reports.exports'), '.').'.'.$userId;
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channelFor($this->userId() ?? '0'))];
    }

    public function broadcastAs(): string
    {
        return 'report-export.updated';
    }

    /**
     * Only ids and statuses; the file path stays on the server.
     *
     * @return array{id: int, report: string, format: string, status: string}
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->id, 'report' => $this->report, 'format' => $this->format, 'status' => $this->status];
    }

    public function broadcastWhen(): bool
    {
        return config('i-reports.queue.realtime') === 'broadcast' && $this->userId() !== null;
    }

    protected function userId(): ?string
    {
        return str_starts_with($this->owner, 'user:') ? substr($this->owner, 5) : null;
    }
}
