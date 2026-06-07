<?php

namespace App\Services\Storefront\Resources;

class SettingsResource extends BaseResource
{
    /**
     * Fetch all settings for a group from the storefront.
     * Returns a flat key → value map regardless of the API response shape.
     *
     * Handles two common storefront shapes:
     *   Shape A (flat object): { "default_courier": "pathao", ... }
     *   Shape B (list):        [ { "key": "default_courier", "value": "pathao" }, ... ]
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $resp = $this->c->request('GET', "settings/{$group}");
        $data = $resp['data'] ?? $resp;

        if (! is_array($data)) return [];

        // Shape B — list of key/value records
        if (array_is_list($data)) {
            $flat = [];
            foreach ($data as $row) {
                if (isset($row['key'])) {
                    $flat[$row['key']] = $row['value'] ?? null;
                }
            }
            return $flat;
        }

        // Shape A — already a flat associative array
        return $data;
    }
}
