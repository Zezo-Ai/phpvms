<?php

namespace App\Queries;

use App\Http\Requests\SearchAirportsRequest;
use App\Models\Airport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Build an Eloquent\Builder for airport listing/search endpoints.
 *
 * Replaces inline Prettus RequestCriteria + WhereCriteria logic that
 * previously lived in `App\Http\Controllers\Api\AirportController::index`
 * and `::search`. Caller decides ->paginate() / ->get() / ->count().
 *
 * Backward-compatible search syntax:
 *   ''                       no filter
 *   'KJ'                     LIKE %KJ% across icao + iata + name (OR)
 *   'icao:K'                 LIKE %K% on icao only
 *   'icao:K;name:Inter'      LIKE %K% on icao AND LIKE %Inter% on name
 *
 * Allowed search fields (pinned by `SEARCHABLE_FIELDS`): icao, iata, name.
 */
class AirportSearchQuery
{
    /** @var list<string> Fields supported by the legacy `field:value` search syntax */
    private const array SEARCHABLE_FIELDS = ['icao', 'iata', 'name'];

    public function __construct(private readonly SearchAirportsRequest $request) {}

    public function build(): Builder
    {
        $data = $this->request->validated();

        $query = Airport::query();

        if ($this->shouldFilterToHubs($data)) {
            $query->byHub();
        }

        if (!empty($data['search'])) {
            $this->applySearch($query, $data['search']);
        }

        $orderBy = $data['orderBy'] ?? 'icao';
        $sortedBy = $data['sortedBy'] ?? 'asc';
        $query->orderBy($orderBy, $sortedBy);

        return $query;
    }

    private function shouldFilterToHubs(array $data): bool
    {
        if (array_key_exists('hub', $data)) {
            return get_truth_state($data['hub']);
        }
        if (array_key_exists('hubs', $data)) {
            return get_truth_state($data['hubs']);
        }

        return false;
    }

    private function applySearch(Builder $query, string $search): void
    {
        // Field-specific syntax: "field:value" or "f1:v1;f2:v2"
        if (str_contains($search, ':')) {
            $query->where(function (Builder $sub) use ($search): void {
                foreach (explode(';', $search) as $part) {
                    [$field, $value] = array_pad(explode(':', $part, 2), 2, null);
                    if ($value !== null && $value !== '' && in_array($field, self::SEARCHABLE_FIELDS, true)) {
                        $sub->where($field, 'like', '%'.$value.'%');
                    }
                }
            });

            return;
        }

        // Free-text search across all searchable fields (OR-combined)
        $query->where(function (Builder $sub) use ($search): void {
            foreach (self::SEARCHABLE_FIELDS as $field) {
                $sub->orWhere($field, 'like', '%'.$search.'%');
            }
        });
    }
}
