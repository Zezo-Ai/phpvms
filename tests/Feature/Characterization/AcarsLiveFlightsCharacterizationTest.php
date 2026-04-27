<?php

declare(strict_types=1);

use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Models\User;
use Carbon\Carbon;

/*
 * Pinned the behavior of Pirep::scopeActiveFlights (formerly AcarsRepository::getPositions):
 *  - Returns only PIREPs with state = IN_PROGRESS
 *  - Filters by updated_at >= now - $liveTime hours when $liveTime > 0
 *  - Returns no time-window filter when $liveTime = 0
 *  - Orders by updated_at desc
 *  - Eager-loads aircraft, airline, arr_airport, dpt_airport, position, user
 */

test('IN_PROGRESS only, within live_time window, ordered desc', function () {
    $user = User::factory()->create();

    // Wrong state: must be filtered out.
    Pirep::factory()->create([
        'user_id'    => $user->id,
        'state'      => PirepState::ACCEPTED,
        'updated_at' => Carbon::now()->subMinutes(1),
    ]);
    // Right state but outside window: must be filtered out.
    Pirep::factory()->create([
        'user_id'    => $user->id,
        'state'      => PirepState::IN_PROGRESS,
        'updated_at' => Carbon::now()->subHours(5),
    ]);
    // Right state, in window, older.
    $older = Pirep::factory()->create([
        'user_id'    => $user->id,
        'state'      => PirepState::IN_PROGRESS,
        'updated_at' => Carbon::now()->subMinutes(30),
    ]);
    // Right state, in window, newest.
    $newer = Pirep::factory()->create([
        'user_id'    => $user->id,
        'state'      => PirepState::IN_PROGRESS,
        'updated_at' => Carbon::now()->subMinutes(1),
    ]);

    $positions = Pirep::activeFlights(2)->get();

    expect($positions)->toHaveCount(2);
    expect($positions->first()->id)->toEqual($newer->id);
    expect($positions->last()->id)->toEqual($older->id);
});

test('with live_time = 0 returns all in-progress regardless of updated_at', function () {
    $user = User::factory()->create();

    $stale = Pirep::factory()->create([
        'user_id'    => $user->id,
        'state'      => PirepState::IN_PROGRESS,
        'updated_at' => Carbon::now()->subDays(30),
    ]);
    $fresh = Pirep::factory()->create([
        'user_id'    => $user->id,
        'state'      => PirepState::IN_PROGRESS,
        'updated_at' => Carbon::now()->subMinutes(5),
    ]);

    // Refetch so id matches the keyType=string cast applied on hydration.
    $stale = $stale->fresh();
    $fresh = $fresh->fresh();

    $positions = Pirep::activeFlights(0)->get();

    expect($positions->pluck('id')->all())
        ->toContain($stale->id)
        ->toContain($fresh->id);
});

test('eager-loads expected relations', function () {
    $user = User::factory()->create();
    Pirep::factory()->create([
        'user_id' => $user->id,
        'state'   => PirepState::IN_PROGRESS,
    ]);

    $positions = Pirep::activeFlights(0)->get();

    /** @var Pirep $pirep */
    $pirep = $positions->first();

    expect($pirep->relationLoaded('aircraft'))->toBeTrue();
    expect($pirep->relationLoaded('airline'))->toBeTrue();
    expect($pirep->relationLoaded('arr_airport'))->toBeTrue();
    expect($pirep->relationLoaded('dpt_airport'))->toBeTrue();
    expect($pirep->relationLoaded('user'))->toBeTrue();
});
