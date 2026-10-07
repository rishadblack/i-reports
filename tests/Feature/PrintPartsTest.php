<?php

beforeEach(function () {
    seedCustomers();
    config()->set('i-reports.print.split_after', 2);
    config()->set('i-reports.print.rows_per_part', 2);
});

it('opens a large print at part one with next buttons and auto print', function () {
    $this->get(reportUrl(['export' => 'print']))
        ->assertOk()
        ->assertSeeInOrder(['Showing rows', '1', '2', 'of', '3', 'part 1 of 2'])
        ->assertSeeInOrder(['Alice', 'Bob'])
        ->assertDontSee('Charlie')
        ->assertDontSee('BDT 425.50')
        ->assertSee('data-part-next', false)
        ->assertSee('part=2', false)
        ->assertDontSee('data-part-prev', false)
        ->assertSee('window.print(); });', false)
        ->assertSee('<title>Customer List (part 1 of 2)</title>', false);
});

it('shows a later part with the grand total and without auto print', function () {
    $this->get(reportUrl(['export' => 'print']).'&part=2')
        ->assertOk()
        ->assertSee('Charlie')
        ->assertDontSee('Alice')
        ->assertSee('BDT 425.50')
        ->assertSee('data-part-prev', false)
        ->assertDontSee('data-part-next', false)
        ->assertDontSee('window.print(); });', false);
});

it('keeps the part number within range', function (string $part, string $expected) {
    $this->get(reportUrl(['export' => 'print']).'&part='.$part)->assertOk()->assertSee($expected);
})->with([
    'too high' => ['99', 'Charlie'],
    'too low' => ['-4', 'Alice'],
    'not a number' => ['abc', 'Alice'],
]);

it('splits before streaming, and streams everything when splitting is off', function () {
    config()->set('i-reports.stream_threshold', 0);

    $this->get(reportUrl(['export' => 'print']))->assertOk()->assertSee('part 1 of 2')->assertDontSee('Charlie');

    config()->set('i-reports.print.split_after', 0);

    $html = $this->get(reportUrl(['export' => 'print']))->assertOk()->streamedContent();

    expect($html)->toContain('Charlie')->not->toContain('Showing rows');
});

it('does not split small prints or pdf exports', function () {
    config()->set('i-reports.print.split_after', 500);

    $this->get(reportUrl(['export' => 'print']))->assertOk()->assertSee('Charlie')->assertDontSee('Showing rows');

    config()->set('i-reports.print.split_after', 2);

    expect($this->get(reportUrl(['export' => 'pdf']))->assertOk()->headers->get('content-type'))->toBe('application/pdf');
});
