<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Enums\UserState;
use App\Models\User;

test('User::active returns only ACTIVE users', function () {
    User::factory()->create(['name' => 'a', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'p', 'state' => UserState::PENDING]);
    User::factory()->create(['name' => 'r', 'state' => UserState::REJECTED]);

    $results = User::active()->pluck('name')->all();

    expect($results)->toBe(['a']);
});

test('User::pending returns only PENDING users', function () {
    User::factory()->create(['name' => 'a', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'p', 'state' => UserState::PENDING]);

    $results = User::pending()->pluck('name')->all();

    expect($results)->toBe(['p']);
});

test('User::inState filters by arbitrary state', function () {
    User::factory()->create(['name' => 'a', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'l', 'state' => UserState::ON_LEAVE]);

    $results = User::inState(UserState::ON_LEAVE)->pluck('name')->all();

    expect($results)->toBe(['l']);
});

test('User::forAirline filters by airline_id', function () {
    $airline = Airline::factory()->create();
    User::factory()->create(['name' => 'a', 'airline_id' => $airline->id]);
    User::factory()->create(['name' => 'b', 'airline_id' => Airline::factory()->create()->id]);

    $results = User::forAirline($airline->id)->pluck('name')->all();

    expect($results)->toBe(['a']);
});

test('User::notRejected excludes rejected users', function () {
    User::factory()->create(['name' => 'a', 'state' => UserState::ACTIVE]);
    User::factory()->create(['name' => 'p', 'state' => UserState::PENDING]);
    User::factory()->create(['name' => 'r', 'state' => UserState::REJECTED]);

    $results = User::notRejected()->pluck('name')->sort()->values()->all();

    expect($results)->toBe(['a', 'p']);
});

test('User scopes compose with each other', function () {
    $airline = Airline::factory()->create();
    User::factory()->create(['name' => 'match',          'state' => UserState::ACTIVE,  'airline_id' => $airline->id]);
    User::factory()->create(['name' => 'wrong-state',    'state' => UserState::PENDING, 'airline_id' => $airline->id]);
    User::factory()->create(['name' => 'wrong-airline',  'state' => UserState::ACTIVE,  'airline_id' => Airline::factory()->create()->id]);

    $results = User::active()->forAirline($airline->id)->pluck('name')->all();

    expect($results)->toBe(['match']);
});
