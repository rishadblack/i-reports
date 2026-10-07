<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Http\Livewire\ReportViewer;
use Rishadblack\IReports\Jobs\ExportReportJob;
use Rishadblack\IReports\Models\QueuedExport;

beforeEach(function () {
    seedCustomers();
    Storage::fake('exports');
});

it('queues large exports per format threshold and tracks them', function () {
    Queue::fake();
    config()->set('i-reports.queue.thresholds', ['pdf' => 2, 'xlsx' => 100, 'csv' => 100]);

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('filters.city', 'Dhaka')
        ->call('exportAs', 'csv')
        ->assertDispatched('exportEvent')
        ->call('exportAs', 'pdf')
        ->assertDispatched('exportQueued')
        ->assertSee('being prepared in the background')
        ->assertSee('Queued')
        ->assertSeeHtml('wire:poll.3s="refreshExports"');

    $record = QueuedExport::sole();

    expect($record->format)->toBe('pdf')
        ->and($record->status)->toBe(QueuedExport::QUEUED)
        ->and($record->report)->toBe('customers')
        ->and($record->request['filters']['city'])->toBe('Dhaka')
        ->and($record->owner)->toStartWith('session:');

    Queue::assertPushed(ExportReportJob::class, fn (ExportReportJob $job) => $job->exportId === $record->id && $job->format === 'pdf');
});

it('marks the export ready, then lets only its owner download it', function () {
    $owner = User::create(['name' => 'Owner']);
    $this->actingAs($owner);

    $record = QueuedExport::create(['owner' => QueuedExport::currentOwner(), 'report' => 'customers', 'format' => 'csv', 'status' => 'queued', 'disk' => 'exports', 'request' => []]);

    ExportReportJob::dispatchSync((new RequestHelper(['report' => 'customers']))->toArray(), 'csv', $owner->id, null, 'done', $record->id);

    $record->refresh();

    expect($record->status)->toBe(QueuedExport::READY)
        ->and($record->path)->toStartWith('done/')
        ->and($record->size)->toBeGreaterThan(0)
        ->and($record->started_at)->not->toBeNull()
        ->and($record->finished_at)->not->toBeNull();

    $this->get(route('i-reports.exports.download', $record->id))->assertOk()->assertDownload($record->file_name);

    $this->actingAs(User::create(['name' => 'Other']))
        ->get(route('i-reports.exports.download', $record->id))
        ->assertNotFound();
});

it('marks the export failed when the report refuses or the job throws', function () {
    $refused = QueuedExport::create(['owner' => 'session:x', 'report' => 'secret', 'format' => 'pdf', 'status' => 'queued', 'disk' => 'exports', 'request' => []]);
    ExportReportJob::dispatchSync((new RequestHelper(['report' => 'secret']))->toArray(), 'pdf', null, null, null, $refused->id);

    expect($refused->refresh()->status)->toBe(QueuedExport::FAILED)
        ->and($refused->error)->toContain('Not authorized');

    $broken = QueuedExport::create(['owner' => 'session:x', 'report' => 'customers', 'format' => 'pdf', 'status' => 'queued', 'disk' => 'exports', 'request' => []]);
    (new ExportReportJob([], 'pdf', null, null, null, $broken->id))->failed(new RuntimeException('Disk full'));

    expect($broken->refresh()->status)->toBe(QueuedExport::FAILED)->and($broken->error)->toBe('Disk full');
});

it('shows ready exports with a download button and dismisses them with their file', function () {
    $record = QueuedExport::create(['owner' => QueuedExport::currentOwner(), 'report' => 'customers', 'format' => 'xlsx', 'status' => 'queued', 'disk' => 'exports', 'request' => []]);
    Storage::disk('exports')->put('i-reports/exports/file.xlsx', 'xlsx-bytes');
    $record->markReady('i-reports/exports/file.xlsx');

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSee('Ready')
        ->assertSee('Download')
        ->assertSeeHtml('data-download="'.$record->id.'"')
        ->assertDontSeeHtml('wire:poll')
        ->call('dismissExport', $record->id)
        ->assertDontSee('Download');

    expect(QueuedExport::count())->toBe(0)
        ->and(Storage::disk('exports')->exists('i-reports/exports/file.xlsx'))->toBeFalse();
});

it('only lists and dismisses the current owner\'s exports for this report', function () {
    QueuedExport::create(['owner' => 'user:999', 'report' => 'customers', 'format' => 'pdf', 'status' => 'ready', 'disk' => 'exports', 'request' => [], 'path' => 'x.pdf']);
    $other = QueuedExport::create(['owner' => QueuedExport::currentOwner(), 'report' => 'grouped-customers', 'format' => 'pdf', 'status' => 'ready', 'disk' => 'exports', 'request' => []]);

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSet('recentExports', [])
        ->call('dismissExport', 1)
        ->call('dismissExport', $other->id);

    expect(QueuedExport::count())->toBe(1);
});

it('stops polling once exports finish', function () {
    $record = QueuedExport::create(['owner' => QueuedExport::currentOwner(), 'report' => 'customers', 'format' => 'csv', 'status' => 'processing', 'disk' => 'exports', 'request' => []]);

    $component = Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSeeHtml('wire:poll')
        ->assertSee('Preparing');

    Storage::disk('exports')->put('a.csv', 'x');
    $record->markReady('a.csv');

    $component->call('refreshExports')->assertDontSeeHtml('wire:poll')->assertSee('Ready');
});

it('prunes old exports with their files', function () {
    Storage::disk('exports')->put('old.csv', 'x');
    $old = QueuedExport::create(['owner' => 'session:x', 'report' => 'customers', 'format' => 'csv', 'status' => 'ready', 'disk' => 'exports', 'request' => [], 'path' => 'old.csv']);
    $old->forceFill(['created_at' => now()->subDays(10)])->save();
    QueuedExport::create(['owner' => 'session:x', 'report' => 'customers', 'format' => 'csv', 'status' => 'ready', 'disk' => 'exports', 'request' => []]);

    $this->artisan('i-reports:prune-exports', ['--days' => 7])->expectsOutputToContain('Deleted 1 export')->assertSuccessful();

    expect(QueuedExport::count())->toBe(1)
        ->and(Storage::disk('exports')->exists('old.csv'))->toBeFalse();
});

it('does not track exports when the queue is disabled', function () {
    config()->set('i-reports.queue.enabled', false);
    Queue::fake();

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSet('exports_enabled', false)
        ->call('queueExport', 'csv')
        ->assertSet('recentExports', []);

    expect(QueuedExport::count())->toBe(0);
    Queue::assertPushed(ExportReportJob::class, fn (ExportReportJob $job) => $job->exportId === null);
});
