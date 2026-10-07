<?php

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    seedCustomers();
});

/*
|--------------------------------------------------------------------------
| Access control
|--------------------------------------------------------------------------
*/

it('requires a token', function () {
    $this->get(route('i-reports.view'))->assertForbidden();
    $this->get(route('i-reports.view', ['report' => 'customers', 'export' => 'csv']))->assertForbidden();
});

it('rejects invalid tokens', function (string $token) {
    $this->get(route('i-reports.view', ['token' => $token]))->assertForbidden();
})->with(['e:not-a-token', 'c:missing', 'junk', 'e:'.base64_encode('still junk')]);

it('rejects an expired token', function () {
    $url = reportUrl();

    $this->travel(11)->minutes();

    $this->get($url)->assertForbidden();
});

it('binds a token to the user who created it', function () {
    $owner = makeUser('Owner');
    $other = makeUser('Other');

    $this->actingAs($owner);
    $url = reportUrl();

    $this->actingAs($other)->get($url)->assertForbidden();
    $this->actingAs($owner)->get($url)->assertOk();
});

it('rejects a user token used by a guest', function () {
    $this->actingAs(makeUser());
    $url = reportUrl();

    auth()->logout();

    $this->get($url)->assertForbidden();
});

it('can disable user binding', function () {
    config()->set('i-reports.token_bind_user', false);

    $this->actingAs(makeUser('Owner'));
    $url = reportUrl();

    $this->actingAs(makeUser('Other'))->get($url)->assertOk();
});

it('returns 404 for an unknown report', function () {
    $this->get(reportUrl([], 'nope'))->assertNotFound();
});

it('returns 403 when the report does not authorize the user', function () {
    $this->get(reportUrl([], 'secret'))->assertForbidden();
});

it('applies the throttle middleware to the report route', function () {
    $middleware = Route::getRoutes()->getByName('i-reports.view')->gatherMiddleware();

    expect($middleware)->toContain('web')->toContain('throttle:60,1');
});

/*
|--------------------------------------------------------------------------
| Listing, pagination, sorting
|--------------------------------------------------------------------------
*/

it('renders the first page sorted by the default sort', function () {
    $this->get(reportUrl())
        ->assertOk()
        ->assertSee('Customer List')
        ->assertSeeInOrder(['Name', 'City', 'Country', 'Alice', 'Bob'])
        ->assertDontSee('Charlie');
});

it('renders the requested page', function () {
    $this->get(reportUrl(['page' => 2]))
        ->assertOk()
        ->assertSee('Charlie')
        ->assertDontSee('Alice');
});

it('skips the count query when the viewer already counted', function () {
    DB::enableQueryLog();

    $this->get(reportUrl(['total' => 3]))->assertOk()->assertSee('Alice');

    $countQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains(strtolower($query['query']), 'count('));

    expect($countQueries)->toHaveCount(0);
});

it('runs a single count query without a known total', function () {
    DB::enableQueryLog();

    $this->get(reportUrl())->assertOk();

    $countQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains(strtolower($query['query']), 'count('));

    expect($countQueries)->toHaveCount(1);
});

it('caps per page at the configured maximum', function () {
    config()->set('i-reports.max_per_page', 1);

    $this->get(reportUrl(['per_page' => 500]))
        ->assertOk()
        ->assertSee('Alice')
        ->assertDontSee('Bob');
});

it('sorts by a sortable column in both directions', function () {
    $this->get(reportUrl(['sort_field' => 'amount', 'sort_direction' => 'desc']))
        ->assertOk()
        ->assertSeeInOrder(['Alice', 'Charlie'])
        ->assertDontSee('Bob');

    $this->get(reportUrl(['sort_field' => 'amount', 'sort_direction' => 'asc']))
        ->assertOk()
        ->assertSeeInOrder(['Bob', 'Charlie'])
        ->assertDontSee('Alice');
});

it('sorts by a relation column', function () {
    $this->get(reportUrl(['sort_field' => 'country.name', 'sort_direction' => 'desc', 'per_page' => 1]))
        ->assertOk()
        ->assertSee('India')
        ->assertDontSee('Bangladesh');
});

it('ignores sort fields that are hidden, custom or not sortable', function (string $field) {
    $this->get(reportUrl(['sort_field' => $field, 'sort_direction' => 'desc']))
        ->assertOk()
        ->assertSeeInOrder(['Alice', 'Bob'])
        ->assertDontSee('Charlie');
})->with(['secret', 'active', 'actions', 'customers.password', 'nope']);

/*
|--------------------------------------------------------------------------
| Search and filters
|--------------------------------------------------------------------------
*/

it('searches the searchable base columns', function () {
    $this->get(reportUrl(['search' => 'bo']))
        ->assertOk()
        ->assertSee('Bob')
        ->assertDontSee('Alice');
});

it('searches the searchable relation columns', function () {
    $this->get(reportUrl(['search' => 'ind']))
        ->assertOk()
        ->assertSee('Bob')
        ->assertDontSee('Alice')
        ->assertDontSee('Charlie');
});

it('applies every filter type', function (array $filters, array $see, array $dontSee) {
    $response = $this->get(reportUrl(['filters' => $filters, 'per_page' => 10]))->assertOk();

    foreach ($see as $name) {
        $response->assertSee($name);
    }

    foreach ($dontSee as $name) {
        $response->assertDontSee($name);
    }
})->with([
    'select with callback' => [['city' => 'Khulna'], ['Alice'], ['Bob', 'Charlie']],
    'multi select' => [['cities' => ['Khulna']], ['Alice'], ['Bob', 'Charlie']],
    'multi select, several' => [['cities' => ['Khulna', 'Dhaka']], ['Alice', 'Bob', 'Charlie'], []],
    'date range from' => [['joined' => ['from' => '2024-01-01']], ['Alice', 'Charlie'], ['Bob']],
    'date range to' => [['joined' => ['to' => '2023-12-31']], ['Bob'], ['Alice', 'Charlie']],
    'date range both' => [['joined' => ['from' => '2024-01-01', 'to' => '2024-01-31']], ['Charlie'], ['Alice', 'Bob']],
    'number range from' => [['amount' => ['from' => '90']], ['Alice', 'Charlie'], ['Bob']],
    'number range to' => [['amount' => ['to' => '80']], ['Bob'], ['Alice', 'Charlie']],
    'boolean false' => [['active' => '0'], ['Alice'], ['Bob', 'Charlie']],
    'boolean true' => [['active' => '1'], ['Bob', 'Charlie'], ['Alice']],
    'text contains' => [['name' => 'li'], ['Alice', 'Charlie'], ['Bob']],
    'select on column' => [['country_id' => '2'], ['Bob'], ['Alice', 'Charlie']],
    'combined' => [['cities' => ['Dhaka'], 'active' => '1', 'amount' => ['from' => '80']], ['Charlie'], ['Alice', 'Bob']],
]);

it('ignores filter values that fail sanitisation instead of failing', function (array $filters) {
    $this->get(reportUrl(['filters' => $filters, 'per_page' => 10]))
        ->assertOk()
        ->assertSee('Alice')
        ->assertSee('Bob')
        ->assertSee('Charlie');
})->with([
    'string for a range' => [['joined' => 'not-an-array']],
    'invalid date' => [['joined' => ['from' => 'yesterday-ish']]],
    'invalid number' => [['amount' => ['from' => 'abc']]],
    'invalid boolean' => [['active' => 'maybe']],
    'blank values' => [['city' => '', 'cities' => [''], 'name' => '   ']],
    'unknown key' => [['does_not_exist' => 'x']],
]);

it('takes the first value when an array reaches a single-value filter', function () {
    $this->get(reportUrl(['filters' => ['city' => ['Khulna', 'Dhaka']], 'per_page' => 10]))
        ->assertOk()
        ->assertSee('Alice')
        ->assertDontSee('Bob');
});

/*
|--------------------------------------------------------------------------
| Rendering
|--------------------------------------------------------------------------
*/

it('escapes cell values by default', function () {
    Customer::create(['name' => '<b onmouseover=alert(1)>Mallory</b>', 'city' => 'Dhaka', 'amount' => 1]);

    $this->get(reportUrl(['search' => 'Mallory']))
        ->assertOk()
        ->assertSee('&lt;b onmouseover=alert(1)&gt;Mallory&lt;/b&gt;', false)
        ->assertDontSee('<b onmouseover=alert(1)>', false);
});

it('prints html columns without escaping', function () {
    $this->get(reportUrl())
        ->assertOk()
        ->assertSee('<a href="/customers/', false)
        ->assertSee('Edit');
});

it('never renders hidden columns', function () {
    $this->get(reportUrl())
        ->assertOk()
        ->assertDontSee('alice-secret')
        ->assertDontSee('Secret');
});

it('formats typed columns for display', function () {
    $this->get(reportUrl())
        ->assertOk()
        ->assertSee('BDT 250.50')
        ->assertSee('Inactive')
        ->assertSee('15/02/2024');
});

it('shows column aggregates in the footer', function () {
    $this->get(reportUrl())
        ->assertOk()
        ->assertSee('Total')
        ->assertSee('BDT 425.50');
});

it('aggregates over the filtered rows only', function () {
    $this->get(reportUrl(['filters' => ['city' => 'Dhaka']]))
        ->assertOk()
        ->assertSee('BDT 175.00')
        ->assertDontSee('BDT 425.50');
});

it('renders group headers and subtotals', function () {
    $this->get(reportUrl(['per_page' => 10], 'grouped-customers'))
        ->assertOk()
        ->assertSeeInOrder(['Dhaka', 'Subtotal', 'BDT 175.00', 'Khulna', 'Alice', 'Subtotal', 'BDT 250.50', 'Grand total', 'BDT 425.50'])
        ->assertSee('Bob')
        ->assertSee('Charlie');
});

it('includes the configured header view', function () {
    config()->set('i-reports.header_view', 'partials.report-header');

    $this->get(reportUrl())->assertOk()->assertSee('Company Header');
});

it('resolves module reports', function () {
    $this->get(reportUrl([], 'billing::invoices'))->assertOk()->assertSee('Invoices');
});

it('renders mapped rows, summaries, additional data, named cells and the search hook', function () {
    $this->get(reportUrl(['per_page' => 10], 'mapped-customers'))
        ->assertOk()
        ->assertSee('ALICE!')
        ->assertSee('extra-note')
        ->assertSee('Rows: 3')
        ->assertSee('color: red', false);

    $this->get(reportUrl(['search' => 'Khulna', 'per_page' => 10], 'mapped-customers'))
        ->assertOk()
        ->assertSee('ALICE!')
        ->assertDontSee('BOB!');

    $this->get(reportUrl(['search' => 'India', 'per_page' => 10], 'mapped-customers'))
        ->assertOk()
        ->assertSee('BOB!')
        ->assertDontSee('ALICE!');
});

it('makes sortable headers clickable in the iframe', function () {
    $this->get(reportUrl(['sort_field' => 'name']))
        ->assertOk()
        ->assertSee('i-reports:sort', false)
        ->assertSee('&#9650;', false);
});
