<?php

declare(strict_types=1);

use App\Http\Requests\SearchAirportsRequest;
use Illuminate\Support\Facades\Validator;

function validateSearchAirports(array $data): Illuminate\Contracts\Validation\Validator
{
    $request = new SearchAirportsRequest();

    return Validator::make($data, $request->rules());
}

test('SearchAirportsRequest passes with empty input', function () {
    expect(validateSearchAirports([])->passes())->toBeTrue();
});

test('SearchAirportsRequest passes with search term', function () {
    expect(validateSearchAirports(['search' => 'KJ'])->passes())->toBeTrue();
});

test('SearchAirportsRequest passes with field-specific search syntax', function () {
    expect(validateSearchAirports(['search' => 'icao:e'])->passes())->toBeTrue();
});

test('SearchAirportsRequest passes with hub flag', function () {
    expect(validateSearchAirports(['hub' => '1'])->passes())->toBeTrue();
    expect(validateSearchAirports(['hubs' => 'true'])->passes())->toBeTrue();
});

test('SearchAirportsRequest passes with valid orderBy', function () {
    expect(validateSearchAirports(['orderBy' => 'icao', 'sortedBy' => 'asc'])->passes())->toBeTrue();
});

test('SearchAirportsRequest rejects orderBy on disallowed column', function () {
    expect(validateSearchAirports(['orderBy' => 'notes'])->fails())->toBeTrue();
});

test('SearchAirportsRequest rejects sortedBy outside asc/desc', function () {
    expect(validateSearchAirports(['sortedBy' => 'sideways'])->fails())->toBeTrue();
});

test('SearchAirportsRequest rejects search longer than 255 chars', function () {
    expect(validateSearchAirports(['search' => str_repeat('x', 256)])->fails())->toBeTrue();
});
