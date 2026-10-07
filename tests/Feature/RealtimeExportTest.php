<?php

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Rishadblack\IReports\Events\ReportExportUpdated;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Http\Livewire\ReportViewer;
use Rishadblack\IReports\Jobs\ExportReportJob;
use Rishadblack\IReports\Models\QueuedExport;

beforeEach(function () {
    seedCustomers();
    Storage::fake('exports');
});

function queuedExportFor(string $owner, string $status = 'queued', string $report = 'customers'): QueuedExport
{
    return QueuedExport::create(['owner' => $owner, 'report' => $report, 'format' => 'pdf', 'status' => $status, 'disk' => 'exports', 'request' => []]);
}

it('broadcasts on the owner\'s private channel without the file path', function () {
    config()->set('i-reports.queue.realtime', 'broadcast');
    $export = queuedExportFor('user:42');
    $export->forceFill(['path' => 'secret/path.pdf'])->save();

    $event = new ReportExportUpdated($export);

    expect($event->broadcastOn())->toHaveCount(1)
        ->and($event->broadcastOn()[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($event->broadcastOn()[0]->name)->toBe('private-i-reports.exports.42')
        ->and($event->broadcastAs())->toBe('report-export.updated')
        ->and($event->broadcastWith())->toBe(['id' => $export->id, 'report' => 'customers', 'format' => 'pdf', 'status' => 'queued'])
        ->and($event->broadcastWhen())->toBeTrue();

    config()->set('i-reports.queue.broadcast_channel', 'acme.reports');
    expect(ReportExportUpdated::channelFor(7))->toBe('acme.reports.7');
});

it('never broadcasts in poll mode or for guests', function () {
    config()->set('i-reports.queue.realtime', 'poll');
    expect((new ReportExportUpdated(queuedExportFor('user:1')))->broadcastWhen())->toBeFalse();

    config()->set('i-reports.queue.realtime', 'broadcast');
    expect((new ReportExportUpdated(queuedExportFor('session:abc')))->broadcastWhen())->toBeFalse();
});

it('fires an update for every status change in broadcast mode', function () {
    config()->set('i-reports.queue.realtime', 'broadcast');
    Event::fake([ReportExportUpdated::class]);

    $export = queuedExportFor('user:5');
    $export->markProcessing();
    Storage::disk('exports')->put('a.pdf', '%PDF');
    $export->markReady('a.pdf');
    $export->markFailed('later failure');

    Event::assertDispatched(ReportExportUpdated::class, 4);
    Event::assertDispatched(ReportExportUpdated::class, fn (ReportExportUpdated $event) => $event->status === 'ready');
});

it('fires nothing in poll mode or for guest owners', function () {
    Event::fake([ReportExportUpdated::class]);

    config()->set('i-reports.queue.realtime', 'poll');
    queuedExportFor('user:5')->markProcessing();

    config()->set('i-reports.queue.realtime', 'broadcast');
    queuedExportFor('session:xyz')->markProcessing();

    Event::assertNotDispatched(ReportExportUpdated::class);
});

it('keeps the export working when the socket server fails', function () {
    config()->set('i-reports.queue.realtime', 'broadcast');
    Event::listen(ReportExportUpdated::class, fn () => throw new RuntimeException('Reverb is down'));

    $owner = makeUser();
    $this->actingAs($owner);
    $export = queuedExportFor('user:'.$owner->id);

    ExportReportJob::dispatchSync((new RequestHelper(['report' => 'customers']))->toArray(), 'csv', $owner->id, null, null, $export->id);

    expect($export->refresh()->status)->toBe(QueuedExport::READY);
});

it('authorizes the private channel only for its own user', function () {
    $channels = app(BroadcastManager::class)->driver()->getChannels();
    $callback = $channels['i-reports.exports.{userId}'] ?? null;

    $alice = makeUser('Alice');

    expect($callback)->not->toBeNull()
        ->and($callback($alice, (string) $alice->id))->toBeTrue()
        ->and($callback($alice, (string) ($alice->id + 1)))->toBeFalse();
});

it('listens on the private channel only for signed-in users in broadcast mode', function () {
    $listenersOf = fn ($component) => (fn () => $this->getListeners())->call($component->instance());

    expect($listenersOf(Livewire::test(ReportViewer::class, ['report' => 'customers'])))->toBe([]);

    config()->set('i-reports.queue.realtime', 'broadcast');
    expect($listenersOf(Livewire::test(ReportViewer::class, ['report' => 'customers'])))->toBe([]);

    $user = makeUser();
    $this->actingAs($user);
    $component = Livewire::test(ReportViewer::class, ['report' => 'customers'])->assertSet('realtime', 'broadcast');

    expect($listenersOf($component))->toBe(['echo-private:i-reports.exports.'.$user->id.',.report-export.updated' => 'onExportUpdated']);
});

it('refreshes and announces a ready export when the socket message arrives', function () {
    config()->set('i-reports.queue.realtime', 'broadcast');
    Queue::fake();
    $user = makeUser();
    $this->actingAs($user);

    $component = Livewire::test(ReportViewer::class, ['report' => 'customers'])->call('queueExport', 'pdf');
    $export = QueuedExport::sole();

    $component->assertSet('pending_export_ids', [$export->id])->assertSee('Queued');

    Storage::disk('exports')->put('ready.pdf', '%PDF');
    $export->markReady('ready.pdf');

    $component->call('onExportUpdated', ['id' => $export->id, 'report' => 'customers', 'format' => 'pdf', 'status' => 'ready'])
        ->assertSee('Your PDF is ready')
        ->assertSee('Download')
        ->assertSet('pending_export_ids', [])
        ->assertDispatched('i-reports:export-ready', id: $export->id, format: 'pdf');
});

it('ignores socket messages for other reports', function () {
    config()->set('i-reports.queue.realtime', 'broadcast');
    $this->actingAs(makeUser());

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->call('onExportUpdated', ['id' => 1, 'report' => 'grouped-customers', 'status' => 'ready'])
        ->assertNotDispatched('i-reports:export-ready');
});

it('announces ready and failed exports found by polling', function () {
    $owner = QueuedExport::currentOwner();
    $ready = queuedExportFor($owner, 'processing');
    $failing = queuedExportFor($owner, 'queued');

    $component = Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSet('pending_export_ids', [$failing->id, $ready->id]);

    Storage::disk('exports')->put('r.pdf', '%PDF');
    $ready->markReady('r.pdf');
    $failing->markFailed('Out of memory');

    $component->call('refreshExports')
        ->assertDispatched('i-reports:export-ready', id: $ready->id)
        ->assertDispatched('i-reports:export-failed', id: $failing->id)
        ->assertSet('pending_export_ids', []);
});

it('renders an automatic polling fallback in broadcast mode and wire:poll in poll mode', function () {
    $owner = QueuedExport::currentOwner();
    queuedExportFor($owner, 'processing');

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSeeHtml('wire:poll.3s="refreshExports"')
        ->assertDontSeeHtml('data-i-reports-fallback-poll');

    config()->set('i-reports.queue.realtime', 'broadcast');

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertDontSeeHtml('wire:poll')
        ->assertSeeHtml('data-i-reports-fallback-poll')
        ->assertSeeHtml("typeof window.Echo !== 'undefined'")
        ->assertSeeHtml('const live = false');
});
