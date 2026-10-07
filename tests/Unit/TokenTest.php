<?php

use Illuminate\Support\Facades\Cache;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Services\ReportTokenManager;

it('round-trips an encrypted token and strips the internal keys', function () {
    $token = ReportTokenManager::store(['report' => 'customers', 'filters' => ['a' => 1]]);

    expect($token)->toStartWith('e:')
        ->and(ReportTokenManager::resolve($token))->toBe(['report' => 'customers', 'filters' => ['a' => 1]]);
});

it('round-trips a cache token and can forget it', function () {
    config()->set('i-reports.use_cache_token', true);

    $token = ReportTokenManager::store(['report' => 'customers']);

    expect($token)->toStartWith('c:')
        ->and(strlen($token))->toBe(42)
        ->and(ReportTokenManager::resolve($token))->toBe(['report' => 'customers']);

    ReportTokenManager::forget($token);

    expect(ReportTokenManager::resolve($token))->toBeNull();
});

it('expires tokens after the configured ttl', function () {
    config()->set('i-reports.token_ttl', 2);

    $token = ReportTokenManager::store(['report' => 'customers']);
    $custom = ReportTokenManager::store(['report' => 'customers'], 30);

    $this->travel(3)->minutes();

    expect(ReportTokenManager::resolve($token))->toBeNull()
        ->and(ReportTokenManager::resolve($custom))->not->toBeNull();
});

it('rejects tampered and malformed tokens', function (string $token) {
    expect(ReportTokenManager::resolve($token))->toBeNull();
})->with([
    '',
    'x:abc',
    'e:',
    'e:%%%',
    'e:'.base64_encode('not encrypted'),
    'c:unknown',
]);

it('binds tokens to the creating user', function () {
    $owner = makeUser('Owner');
    $this->actingAs($owner);

    $token = ReportTokenManager::store(['report' => 'customers']);

    expect(ReportTokenManager::resolve($token))->toBe(['report' => 'customers']);

    $this->actingAs(makeUser('Other'));

    expect(ReportTokenManager::resolve($token))->toBeNull();

    config()->set('i-reports.token_bind_user', false);

    expect(ReportTokenManager::resolve($token))->toBe(['report' => 'customers']);
});

it('builds tokens from the request helper with every field', function () {
    $helper = (new RequestHelper(['report' => 'customers', 'per_page' => '0', 'page' => '-2', 'sort_direction' => 'DOWN', 'filters' => 'junk']))
        ->setSearch('x')
        ->setSort('name', 'desc')
        ->setExport('pdf')
        ->setTotal(9);

    expect($helper->toArray())->toBe([
        'filters' => [],
        'search' => 'x',
        'export' => 'pdf',
        'per_page' => 1,
        'page' => 1,
        'total' => 9,
        'report' => 'customers',
        'sort_field' => 'name',
        'sort_direction' => 'desc',
        'hidden_columns' => [],
        'page_setup' => [],
    ])->and(ReportTokenManager::resolve($helper->generateToken()))->toBe($helper->toArray());

    expect((new RequestHelper)->toArray()['export'])->toBe('view');
});

it('does not use the cache for encrypted tokens', function () {
    Cache::spy();

    ReportTokenManager::store(['report' => 'customers']);

    Cache::shouldNotHaveReceived('put');
});
