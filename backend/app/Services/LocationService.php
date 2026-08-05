<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class LocationService
{
    private const TTL = 86400; // 24h

    public function getProvinces(): array
    {
        $cached = Cache::get('oe_loc_provinces_v4');
        if (is_array($cached) && count($cached) > 0) {
            return $cached;
        }

        $locations = OceanExpressService::getLocations('province');

        $data = collect($locations)
            ->map(fn ($p) => [
                'id' => $p['id'],
                'name' => $p['name'],
            ])
            ->values()
            ->toArray();

        if (count($data) > 0) {
            Cache::put('oe_loc_provinces_v4', $data, self::TTL);
        }

        return $data;
    }

    public function getWards(string $provinceCode): array
    {
        $cacheKey = "oe_loc_wards_v4_{$provinceCode}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) > 0) {
            return $cached;
        }

        $locations = OceanExpressService::getLocations('ward', $provinceCode);

        $data = collect($locations)
            ->map(fn ($w) => [
                'id' => (string) $w['id'],
                'name' => $w['name'],
            ])
            ->values()
            ->toArray();

        if (count($data) > 0) {
            Cache::put($cacheKey, $data, self::TTL);
        }

        return $data;
    }
}
