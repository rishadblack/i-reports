<?php

use App\Models\Country;
use App\Models\Customer;
use App\Models\User;
use Rishadblack\IReports\Helpers\RequestHelper;
use Rishadblack\IReports\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * Build a tokenised report URL.
 *
 * @param  array<string, mixed>  $params
 */
function reportUrl(array $params = [], string $report = 'customers'): string
{
    $token = (new RequestHelper($params + ['report' => $report, 'per_page' => 2]))->generateToken();

    return route('i-reports.view', ['token' => $token]);
}

/**
 * Three customers in two countries. Sorted by name: Alice, Bob, Charlie.
 *
 * @return array<string, Customer>
 */
function seedCustomers(): array
{
    $bangladesh = Country::create(['name' => 'Bangladesh']);
    $india = Country::create(['name' => 'India']);

    return [
        'charlie' => Customer::create(['name' => 'Charlie', 'city' => 'Dhaka', 'country_id' => $bangladesh->id, 'amount' => 100, 'active' => true, 'joined_at' => '2024-01-10', 'secret' => 'charlie-secret']),
        'alice' => Customer::create(['name' => 'Alice', 'city' => 'Khulna', 'country_id' => $bangladesh->id, 'amount' => 250.5, 'active' => false, 'joined_at' => '2024-02-15', 'secret' => 'alice-secret']),
        'bob' => Customer::create(['name' => 'Bob', 'city' => 'Dhaka', 'country_id' => $india->id, 'amount' => 75, 'active' => true, 'joined_at' => '2023-12-01', 'secret' => 'bob-secret']),
    ];
}

function makeUser(string $name = 'Tester'): User
{
    return User::create(['name' => $name]);
}
