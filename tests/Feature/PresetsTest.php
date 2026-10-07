<?php

use Livewire\Livewire;
use Rishadblack\IReports\Http\Livewire\ReportViewer;
use Rishadblack\IReports\Models\ReportPreset;

beforeEach(function () {
    seedCustomers();
});

it('disables presets for guests and when turned off', function () {
    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSet('presets_enabled', false)
        ->assertDontSee('Saved views');

    config()->set('i-reports.presets.enabled', false);

    $this->actingAs(makeUser());

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSet('presets_enabled', false)
        ->call('savePreset')
        ->call('applyPreset', 1)
        ->call('deletePreset', 1);

    expect(ReportPreset::count())->toBe(0);
});

it('saves, lists, applies and deletes presets for the signed-in user', function () {
    $user = makeUser();
    $this->actingAs($user);

    $component = Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSet('presets_enabled', true)
        ->assertSee('Saved views')
        ->set('filters.city', 'Khulna')
        ->call('filterSubmit')
        ->set('search', 'ali')
        ->call('sortBy', 'amount')
        ->set('preset_name', ' Khulna only ')
        ->call('savePreset')
        ->assertHasNoErrors()
        ->assertSet('preset_name', '')
        ->assertSee('Khulna only');

    $preset = ReportPreset::first();

    expect($preset->user_id)->toBe((string) $user->id)
        ->and($preset->report)->toBe('customers')
        ->and($preset->name)->toBe('Khulna only')
        ->and($preset->state['filters'])->toBe(['region' => 'north', 'city' => 'Khulna'])
        ->and($preset->state['search'])->toBe('ali')
        ->and($preset->state['sort_field'])->toBe('amount');

    $component
        ->call('resetReport')
        ->assertSet('filters.city', null)
        ->call('applyPreset', $preset->id)
        ->assertSet('filters.city', 'Khulna')
        ->assertSet('search', 'ali')
        ->assertSet('sort_field', 'amount')
        ->assertSet('page', 1)
        ->assertSet('total', 1)
        ->call('deletePreset', $preset->id)
        ->assertDontSee('Khulna only');

    expect(ReportPreset::count())->toBe(0);
});

it('validates the preset name', function () {
    $this->actingAs(makeUser());

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('preset_name', '')
        ->call('savePreset')
        ->assertHasErrors(['preset_name' => 'required']);

    expect(ReportPreset::count())->toBe(0);
});

it('keeps presets private to their owner and report', function () {
    $owner = makeUser('Owner');
    $other = makeUser('Other');

    ReportPreset::create(['user_id' => $owner->id, 'report' => 'customers', 'name' => 'Mine', 'state' => ['filters' => ['city' => 'Khulna']]]);
    ReportPreset::create(['user_id' => $owner->id, 'report' => 'grouped-customers', 'name' => 'Other report', 'state' => []]);
    $mine = ReportPreset::where('name', 'Mine')->first();

    $this->actingAs($other);

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertDontSee('Mine')
        ->call('applyPreset', $mine->id)
        ->assertSet('filters.city', null)
        ->call('deletePreset', $mine->id);

    expect(ReportPreset::count())->toBe(2);

    $this->actingAs($owner);

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->assertSee('Mine')
        ->assertDontSee('Other report');
});

it('overwrites a preset saved with the same name', function () {
    $this->actingAs(makeUser());

    Livewire::test(ReportViewer::class, ['report' => 'customers'])
        ->set('filters.city', 'Khulna')
        ->call('filterSubmit')
        ->set('preset_name', 'Mine')
        ->call('savePreset')
        ->set('filters.city', 'Dhaka')
        ->call('filterSubmit')
        ->set('preset_name', 'Mine')
        ->call('savePreset');

    expect(ReportPreset::count())->toBe(1)
        ->and(ReportPreset::first()->state['filters']['city'])->toBe('Dhaka');
});
