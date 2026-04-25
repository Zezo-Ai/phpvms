<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;

/**
 * Validates query string for /api/airports and /api/airports/search.
 *
 * Backward compatible with the legacy Prettus RequestCriteria syntax:
 *   ?search=KJ                        free-text across icao/iata/name
 *   ?search=icao:KJ                   field-specific (LIKE %KJ%)
 *   ?search=icao:K;name:Inter        multi-field (multiple LIKE clauses, AND-combined)
 *
 * Fields:
 *   search    Free-text or `field:value[;field:value...]` (LIKE-style)
 *   hub       Truthy => filter to hubs only (legacy /api/airports name)
 *   hubs      Truthy => filter to hubs only (legacy /api/airports/search name)
 *   orderBy   Allowlisted column to sort by; defaults to icao
 *   sortedBy  asc | desc; defaults to asc
 */
class SearchAirportsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            // hub/hubs intentionally permissive: get_truth_state() in the
            // Query class coerces yes/y/on/true/1 (and falsy variants).
            // Strict validation would break clients sending ?hub=yes.
            'hub'      => ['sometimes'],
            'hubs'     => ['sometimes'],
            'orderBy'  => ['sometimes', 'in:id,iata,icao,name,hub'],
            'sortedBy' => ['sometimes', 'in:asc,desc'],
        ];
    }
}
