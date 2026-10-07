<?php

use App\Livewire\Reports\CustomersReport;
use Composer\InstalledVersions;
use Livewire\Livewire;
use Rishadblack\IReports\Facades\IReports;
use Rishadblack\IReports\Http\Livewire\ReportViewer;
use Rishadblack\IReports\Views\Filter;

beforeEach(function () {
    seedCustomers();
});

/**
 * The directive the package writes for a mode on the installed Livewire version
 * (Livewire 4: .live.change / .live.blur; Livewire 3: .lazy / .blur).
 */
function expectedModel(string $mode): string
{
    $livewire4 = (int) InstalledVersions::getVersion('livewire/livewire') >= 4;

    return match ($mode) {
        'change' => $livewire4 ? 'wire:model.live.change' : 'wire:model.lazy',
        'blur' => $livewire4 ? 'wire:model.live.blur' : 'wire:model.blur',
    };
}

it('builds the wire:model directive for each update mode', function (Filter $filter, string $directive) {
    $directive = in_array($directive, ['change', 'blur'], true) ? expectedModel($directive) : $directive;

    expect($filter->wireModel())->toBe($directive)
        ->and($filter->toArray()['wire_model'])->toBe($directive);
})->with([
    'default waits for apply' => [Filter::make('A', 'a')->text(), 'wire:model'],
    'on change' => [Filter::make('A', 'a')->select()->onChange(), 'change'],
    'on blur' => [Filter::make('A', 'a')->text()->onBlur(), 'blur'],
    'live with debounce' => [Filter::make('A', 'a')->text()->live(300), 'wire:model.live.debounce.300ms'],
    'live with the default debounce' => [Filter::make('A', 'a')->text()->live(), 'wire:model.live.debounce.500ms'],
    'live without debounce' => [Filter::make('A', 'a')->text()->live(0), 'wire:model.live'],
    'deferred again' => [Filter::make('A', 'a')->text()->live()->deferred(), 'wire:model'],
]);

it('takes the default mode and debounce from config', function () {
    config()->set('i-reports.filter_update', 'live');
    config()->set('i-reports.filter_debounce', 800);

    expect(Filter::make('A', 'a')->text()->wireModel())->toBe('wire:model.live.debounce.800ms')
        ->and(Filter::make('A', 'a')->text()->onChange()->wireModel())->toBe(expectedModel('change'))
        ->and(Filter::make('A', 'a')->text()->deferred()->wireModel())->toBe('wire:model');

    config()->set('i-reports.filter_update', 'nonsense');

    expect(Filter::make('A', 'a')->text()->wireModel())->toBe('wire:model')
        ->and(fn () => Filter::make('A', 'a')->updateOn('instant'))->toThrow(InvalidArgumentException::class);
});

it('renders every filter field with its update mode', function () {
    $report = new class extends CustomersReport
    {
        public function filters(): array
        {
            return [
                Filter::make('City', 'city')->select(['Dhaka' => 'Dhaka'])->column('customers.city')->onChange(),
                Filter::make('Name', 'name')->text()->column('customers.name')->live(300),
                Filter::make('Joined', 'joined')->dateRange()->column('customers.joined_at')->onBlur(),
                Filter::make('Pick', 'pick')->bladeComponent('forms.pick')->live(),
                Filter::make('Plain', 'plain')->bladeComponent('forms.pick'),
            ];
        }
    };
    IReports::register('modes', $report::class);

    Livewire::test(ReportViewer::class, ['report' => 'modes'])
        ->assertSeeHtml(expectedModel('change').'="filters.city"')
        ->assertSeeHtml('wire:model.live.debounce.300ms="filters.name"')
        ->assertSeeHtml(expectedModel('blur').'="filters.joined.from"')
        ->assertSeeHtml(expectedModel('blur').'="filters.joined.to"')
        ->assertSeeHtml('data-pick="filters.pick" wire:model.live.debounce.500ms="filters.pick"')
        ->assertSeeHtml('data-pick="filters.plain" wire:model="filters.plain"');
});

it('changes the report only on apply for default filters', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('page', 2)
        ->set('filters.city', 'Dhaka')
        ->assertSet('applied_filters', ['region' => 'north'])
        ->assertSet('page', 2)
        ->assertSet('total', 3)
        ->assertSeeHtml('data-pending-filters')
        ->call('filterSubmit')
        ->assertSet('applied_filters', ['region' => 'north', 'city' => 'Dhaka'])
        ->assertSet('page', 1)
        ->assertSet('total', 2)
        ->assertDontSeeHtml('data-pending-filters');
});

it('changes the report at once for live, change and blur filters', function (string $mode) {
    $report = new class extends CustomersReport
    {
        public static string $mode = 'live';

        public function filters(): array
        {
            return [
                Filter::make('City', 'city')->select(['Dhaka' => 'Dhaka', 'Khulna' => 'Khulna'])->column('customers.city')->updateOn(self::$mode),
                Filter::make('Name', 'name')->text()->column('customers.name'),
            ];
        }
    };
    $report::$mode = $mode;
    IReports::register('instant', $report::class);

    Livewire::test(ReportViewer::class, ['report' => 'instant'])
        ->set('page', 2)
        ->set('filters.name', 'Ali')
        ->assertSet('applied_filters', [])
        ->set('filters.city', 'Dhaka')
        ->assertSet('applied_filters', ['city' => 'Dhaka'])
        ->assertSet('page', 1)
        ->assertSet('total', 2);
})->with(['live', 'change', 'blur']);

it('discards unapplied changes when the dialog is cancelled', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('filters.city', 'Dhaka')
        ->call('discardFilters')
        ->assertSet('filters', ['region' => 'north'])
        ->assertSet('total', 3);
});

it('clears dependent dialog values when a parent changes, without applying them', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('filters.country_id', '1')
        ->assertSet('filters.region', null)
        ->assertSet('applied_filters', ['region' => 'north']);
});
