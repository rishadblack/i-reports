<?php

use App\Models\Customer;
use Rishadblack\IReports\Helpers\RequestHelper;

/**
 * @param  array<string, mixed>  $params
 */
function reportUrl(array $params = []): string
{
    $token = (new RequestHelper(['report' => 'customers', 'per_page' => 2] + $params))->generateToken();

    return route('i-reports.view', ['token' => $token]);
}

beforeEach(function () {
    Customer::create(['name' => 'Charlie', 'city' => 'Dhaka']);
    Customer::create(['name' => 'Alice', 'city' => 'Khulna']);
    Customer::create(['name' => 'Bob', 'city' => 'Dhaka']);
});

it('renders the first page sorted by the default sort', function () {
    $this->get(reportUrl())
        ->assertOk()
        ->assertSee('Customer List')
        ->assertSeeInOrder(['Name', 'City', 'Alice', 'Bob'])
        ->assertDontSee('Charlie');
});

it('renders the requested page', function () {
    $this->get(reportUrl(['page' => 2]))
        ->assertOk()
        ->assertSee('Charlie')
        ->assertDontSee('Alice');
});

it('searches the searchable columns', function () {
    $this->get(reportUrl(['search' => 'bo']))
        ->assertOk()
        ->assertSee('Bob')
        ->assertDontSee('Alice');
});

it('applies the report filters', function () {
    $this->get(reportUrl(['filters' => ['city' => 'Khulna']]))
        ->assertOk()
        ->assertSee('Alice')
        ->assertDontSee('Bob');
});

it('rejects an invalid token', function () {
    $this->get(route('i-reports.view', ['token' => 'e:not-a-token']))->assertForbidden();
});

it('exports every row to excel and csv', function (string $format, string $extension) {
    $response = $this->get(reportUrl(['export' => $format]))->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('customers-report-')->toContain(".{$extension}");
})->with([
    'xlsx' => ['xlsx', 'xlsx'],
    'csv' => ['csv', 'csv'],
]);

it('exports a pdf', function () {
    $response = $this->get(reportUrl(['export' => 'pdf']))->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

it('renders a printable page with every row', function () {
    $this->get(reportUrl(['export' => 'print']))
        ->assertOk()
        ->assertSee(['Alice', 'Bob', 'Charlie'])
        ->assertSee('window.print()', false);
});
