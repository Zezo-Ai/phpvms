<?php

declare(strict_types=1);

use App\Http\Requests\SearchAirportsRequest;
use App\Models\Airport;
use App\Queries\AirportSearchQuery;

function airportSearchQueryFor(array $params): AirportSearchQuery
{
    $request = SearchAirportsRequest::create('/api/airports', 'GET', $params);
    $request->setContainer(app())->validateResolved();

    return new AirportSearchQuery($request);
}

test('AirportSearchQuery returns a Builder ordered by icao asc by default', function () {
    foreach (['KZZZ', 'KAAA', 'KMMM'] as $icao) {
        Airport::factory()->create(['id' => $icao, 'icao' => $icao]);
    }

    $results = airportSearchQueryFor([])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KAAA', 'KMMM', 'KZZZ']);
});

test('AirportSearchQuery filters to hubs with ?hub=1', function () {
    Airport::factory()->count(3)->create(['hub' => false]);
    Airport::factory()->create(['icao' => 'KORD', 'hub' => true]);

    $results = airportSearchQueryFor(['hub' => '1'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KORD']);
});

test('AirportSearchQuery filters to hubs with ?hubs=true', function () {
    Airport::factory()->count(2)->create(['hub' => false]);
    Airport::factory()->create(['icao' => 'KORD', 'hub' => true]);

    $results = airportSearchQueryFor(['hubs' => 'true'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KORD']);
});

test('AirportSearchQuery free-text search matches across icao/iata/name', function () {
    Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK', 'iata' => 'JFK', 'name' => 'Kennedy']);
    Airport::factory()->create(['id' => 'KORD', 'icao' => 'KORD', 'iata' => 'ORD', 'name' => "O'Hare"]);

    // Match by ICAO substring
    $byIcao = airportSearchQueryFor(['search' => 'JFK'])->build()->get();
    expect($byIcao->pluck('icao')->all())->toBe(['KJFK']);

    // Match by name substring
    $byName = airportSearchQueryFor(['search' => 'Kennedy'])->build()->get();
    expect($byName->pluck('icao')->all())->toBe(['KJFK']);

    // Match by IATA substring
    $byIata = airportSearchQueryFor(['search' => 'ORD'])->build()->get();
    expect($byIata->pluck('icao')->all())->toBe(['KORD']);
});

test('AirportSearchQuery field-specific search uses LIKE for icao:value', function () {
    foreach (['EGLL', 'KAUS', 'KJFK', 'KSFO'] as $a) {
        Airport::factory()->create(['id' => $a, 'icao' => $a]);
    }

    $results = airportSearchQueryFor(['search' => 'icao:E'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['EGLL']);
});

test('AirportSearchQuery field-specific search supports multiple fields', function () {
    Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK', 'name' => 'Kennedy International']);
    Airport::factory()->create(['id' => 'KORD', 'icao' => 'KORD', 'name' => 'OHare International']);
    Airport::factory()->create(['id' => 'EGLL', 'icao' => 'EGLL', 'name' => 'Heathrow International']);

    // ICAO starts with K AND name contains "Kennedy"
    $results = airportSearchQueryFor(['search' => 'icao:K;name:Kennedy'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KJFK']);
});

test('AirportSearchQuery search is case insensitive', function () {
    foreach (['EGLL', 'KAUS', 'KJFK', 'KSFO'] as $a) {
        Airport::factory()->create(['id' => $a, 'icao' => $a]);
    }

    $results = airportSearchQueryFor(['search' => 'kj'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KJFK']);
});

test('AirportSearchQuery honors orderBy and sortedBy', function () {
    foreach (['KAAA', 'KZZZ', 'KMMM'] as $icao) {
        Airport::factory()->create(['id' => $icao, 'icao' => $icao]);
    }

    $results = airportSearchQueryFor(['orderBy' => 'icao', 'sortedBy' => 'desc'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KZZZ', 'KMMM', 'KAAA']);
});

test('AirportSearchQuery composes hub filter and search', function () {
    Airport::factory()->create(['id' => 'KORD', 'icao' => 'KORD', 'hub' => true]);
    Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK', 'hub' => false]);
    Airport::factory()->create(['id' => 'KAAA', 'icao' => 'KAAA', 'hub' => true]);

    // Search for "K" + only hubs
    $results = airportSearchQueryFor(['search' => 'icao:K', 'hub' => '1'])->build()->get();

    expect($results->pluck('icao')->all())->toBe(['KAAA', 'KORD']);
});

test('AirportSearchQuery does not add empty-value LIKE clauses', function () {
    Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK']);

    // ?search=icao: — empty value should be silently dropped, NOT translated to LIKE '%%'
    $sql = airportSearchQueryFor(['search' => 'icao:'])->build()->toRawSql();

    // The bug: SQL contains "icao like '%%'" (matches everything by accident)
    // The fix: SQL contains no LIKE clause at all
    expect($sql)->not->toContain("like '%%'");
});
