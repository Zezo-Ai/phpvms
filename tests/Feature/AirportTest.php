<?php

use App\Exceptions\AirportNotFound;
use App\Models\Airport;
use App\Models\User;
use App\Repositories\AirportRepository;
use App\Services\AirportService;

it('can create an airport from api response', function () {
    // This is the response from the API
    $airportResponse = [
        'icao'    => 'KJFK',
        'iata'    => 'JFK',
        'name'    => 'John F Kennedy International Airport',
        'city'    => 'New York',
        'country' => 'United States',
        'tz'      => 'America/New_York',
        'lat'     => 40.63980103,
        'lon'     => -73.77890015,
    ];

    $airport = new Airport($airportResponse);
    expect($airport->icao)->toEqual($airportResponse['icao'])
        ->and($airport->timezone)->toEqual($airportResponse['tz']);
});

it('can search airports', function () {
    foreach (['EGLL', 'KAUS', 'KJFK', 'KSFO'] as $a) {
        Airport::factory()->create(['id' => $a, 'icao' => $a]);
    }

    $uri = '/api/airports/search?search=icao:e';
    $res = $this->get($uri);

    $airports = $res->json('data');
    expect($airports)->toHaveCount(1)
        ->and($airports[0]['icao'])->toEqual('EGLL');

    $uri = '/api/airports/search?search=KJ';
    $res = $this->get($uri);

    $airports = $res->json('data');
    expect($airports)->toHaveCount(1)
        ->and($airports[0]['icao'])->toEqual('KJFK');
});

it('can search airports with multi letter', function () {
    foreach (['EGLL', 'KAUS', 'KJFK', 'KSFO'] as $a) {
        Airport::factory()->create(['id' => $a, 'icao' => $a]);
    }

    $uri = '/api/airports/search?search=Kj';
    $res = $this->get($uri);

    $airports = $res->json('data');
    expect($airports)->toHaveCount(1)
        ->and($airports[0]['icao'])->toEqual('KJFK');
});

test('airport search missing', function () {
    foreach (['EGLL', 'KAUS', 'KJFK', 'KSFO'] as $a) {
        Airport::factory()->create(['id' => $a, 'icao' => $a]);
    }

    $uri = '/api/airports/search?search=icao:X';
    $res = $this->get($uri);

    $airports = $res->json('data');
    expect($airports)->toHaveCount(0);
});

it('returns cached data if available', function () {
    Config::set('cache.keys.AIRPORT_VACENTRAL_LOOKUP.key', 'air_');
    Cache::shouldReceive('get')->with('air_KJFK')->andReturn(['name' => 'Kennedy']);

    // Resolve from container instead of 'new'
    $result = app(AirportService::class)->lookupAirport('KJFK');

    expect($result)->toBe(['name' => 'Kennedy']);
});

it('creates a generic airport if auto_lookup is disabled', function () {
    Config::set('general.auto_airport_lookup', false);

    // Partial mock the repository so the service uses our mock instead of the real DB
    $this->mock(AirportRepository::class, function ($mock) {
        $mock->shouldReceive('findWithoutFail')->andReturn(null);
    });

    $result = app(AirportService::class)->lookupAirportIfNotFound('KORD');

    expect($result->icao)->toBe('KORD');
    $this->assertDatabaseHas('airports', ['icao' => 'KORD']);
});

it('calculates distance between two known points', function () {
    $this->mock(AirportRepository::class, function ($mock) {
        $mock->shouldReceive('find')->with('KJFK', ['lat', 'lon'])
            ->andReturn((object) ['lat' => 40.6413, 'lon' => -73.7781]);
        $mock->shouldReceive('find')->with('KLAX', ['lat', 'lon'])
            ->andReturn((object) ['lat' => 33.9416, 'lon' => -118.4085]);
    });

    $distance = app(AirportService::class)->calculateDistance('KJFK', 'KLAX');

    expect($distance->toUnit('mi'))->toBeBetween(2472, 2473);
});

it('throws exception when origin airport is missing', function () {
    $this->mock(AirportRepository::class, function ($mock) {
        $mock->shouldReceive('find')->andReturn(null);
    });

    expect(fn () => app(AirportService::class)->calculateDistance('KJFK', 'KLAX'))
        ->toThrow(AirportNotFound::class);
});

test('airport list filters to hubs when ?hub=true', function () {
    $user = User::factory()->create();
    apiAs($user);

    Airport::factory()->count(3)->create(['hub' => false]);
    Airport::factory()->create(['icao' => 'KORD', 'hub' => true]);
    Airport::factory()->create(['icao' => 'KJFK', 'hub' => true]);

    $response = $this->get('/api/airports?hub=1');
    $response->assertOk();

    $icaos = collect($response->json('data'))->pluck('icao')->all();
    expect($icaos)->toContain('KORD', 'KJFK')
        ->and(count($icaos))->toBe(2);
});

test('airport search filters to hubs when ?hubs=true', function () {
    $user = User::factory()->create();
    apiAs($user);

    Airport::factory()->count(3)->create(['hub' => false, 'name' => 'Test Airport']);
    Airport::factory()->create(['icao' => 'KORD', 'hub' => true, 'name' => 'Hub Airport']);

    $response = $this->get('/api/airports/search?hubs=1');
    $response->assertOk();

    $hubs = collect($response->json('data'))->pluck('icao')->all();
    expect($hubs)->toEqual(['KORD']);
});

test('airport list orders by icao ascending by default', function () {
    $user = User::factory()->create();
    apiAs($user);

    foreach (['KZZZ', 'KAAA', 'KMMM'] as $icao) {
        Airport::factory()->create(['id' => $icao, 'icao' => $icao]);
    }

    $response = $this->get('/api/airports');
    $response->assertOk();

    $order = collect($response->json('data'))->pluck('icao')->all();
    expect($order)->toBe(['KAAA', 'KMMM', 'KZZZ']);
});

test('airport list honors ?limit query param', function () {
    $user = User::factory()->create();
    apiAs($user);

    Airport::factory()->count(8)->create();

    $response = $this->get('/api/airports?limit=3');
    $response->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('meta.per_page'))->toBe(3);
});

test('GET /api/airports/{airport} resolves lowercase ICAO via route binding', function () {
    $user = User::factory()->create();
    apiAs($user);

    Airport::factory()->create(['id' => 'KJFK', 'icao' => 'KJFK']);

    $response = $this->get('/api/airports/kjfk');

    $response->assertOk();
    expect($response->json('data.icao'))->toBe('KJFK');
});

it('preserves ?limit= and ?hub= in /api/airports pagination metadata', function () {
    $user = User::factory()->create();
    apiAs($user);

    Airport::factory()->count(5)->create(['hub' => true]);

    $res = $this->get('/api/airports?limit=2&hub=1');
    $res->assertSuccessful();

    // Phase 2 precedent: the legacy Repository::paginate() honored
    // request()->except(['page', 'user']) via ->appends(), forwarding
    // every query param to meta.next_page. The migration must preserve
    // this so clients can follow pagination without losing filters.
    expect($res->json('meta.per_page'))->toEqual(2);

    $next = $res->json('meta.next_page');
    expect($next)->not->toBeNull()
        ->and($next)->toContain('limit=2')
        ->and($next)->toContain('hub=1');
});

it('preserves ?search= in /api/airports/search pagination metadata', function () {
    $user = User::factory()->create();
    apiAs($user);

    foreach (['KAAA', 'KBBB', 'KCCC', 'KDDD', 'KEEE'] as $icao) {
        Airport::factory()->create(['id' => $icao, 'icao' => $icao]);
    }

    $res = $this->get('/api/airports/search?search=icao:K&limit=2');
    $res->assertSuccessful();

    expect($res->json('meta.per_page'))->toEqual(2);

    $next = $res->json('meta.next_page');
    expect($next)->not->toBeNull()
        ->and($next)->toContain('limit=2')
        ->and($next)->toContain('search=icao%3AK');  // URL-encoded colon
});

it('preserves ?limit= in /api/airports/hubs pagination metadata', function () {
    $user = User::factory()->create();
    apiAs($user);

    Airport::factory()->count(5)->create(['hub' => true]);

    $res = $this->get('/api/airports/hubs?limit=2');
    $res->assertSuccessful();

    expect($res->json('meta.per_page'))->toEqual(2);

    $next = $res->json('meta.next_page');
    expect($next)->not->toBeNull()
        ->and($next)->toContain('limit=2');
});
