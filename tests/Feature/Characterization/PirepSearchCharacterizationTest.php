<?php

declare(strict_types=1);

use App\Http\Requests\SearchPirepsRequest;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Models\User;
use App\Queries\PirepSearchQuery;

/*
 * Pins behavior of PirepSearchQuery applied to /api/pireps + /pireps.
 *  - field:value;field2:value2 search syntax (OR-joined)
 *  - free-text fallback across id + flight_number
 *  - orderBy + sortedBy ordering
 *  - default ordering preserved (none — caller adds default)
 *
 * Deleted once Tasks 9-10 swap controllers to use this query class.
 */

function pirepSearchRequest(array $params): SearchPirepsRequest
{
    $req = SearchPirepsRequest::create('/api/pireps', 'GET', $params);
    $req->setContainer(app());
    $req->validateResolved();

    return $req;
}

test('field-specific search filters by user_id', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    $p1 = Pirep::factory()->create(['user_id' => $u1->id, 'state' => PirepState::ACCEPTED]);
    Pirep::factory()->create(['user_id' => $u2->id, 'state' => PirepState::ACCEPTED]);

    $req = pirepSearchRequest(['search' => 'user_id:'.$u1->id]);
    $results = (new PirepSearchQuery())->build($req)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toEqual($p1->id);
});

test('free-text search falls back to id LIKE when no field prefix matches', function () {
    $u = User::factory()->create();
    $p1 = Pirep::factory()->create(['user_id' => $u->id, 'flight_number' => 'AB123']);
    Pirep::factory()->create(['user_id' => $u->id, 'flight_number' => 'XY999']);

    $req = pirepSearchRequest(['search' => 'AB123']);
    $results = (new PirepSearchQuery())->build($req)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toEqual($p1->id);
});

test('orderBy + sortedBy applied when present', function () {
    $u = User::factory()->create();
    $a = Pirep::factory()->create(['user_id' => $u->id, 'flight_number' => 'AAA']);
    $b = Pirep::factory()->create(['user_id' => $u->id, 'flight_number' => 'BBB']);

    $req = pirepSearchRequest(['orderBy' => 'flight_number', 'sortedBy' => 'desc']);
    $results = (new PirepSearchQuery())->build($req)->get();

    expect($results->pluck('flight_number')->take(2)->all())->toEqual(['BBB', 'AAA']);
});
