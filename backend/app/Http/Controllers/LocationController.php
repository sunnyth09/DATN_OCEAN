<?php

namespace App\Http\Controllers;

use App\Services\GHNService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * LocationController — Cung cấp danh sách Tỉnh/Quận/Phường chuẩn GHN cho frontend.
 */
class LocationController extends Controller
{
    private const TTL = 86400; // 24h

    /**
     * GET /api/location/provinces
     */
    public function getProvinces()
    {
        $cached = Cache::get('ghn_provinces_v2');
        if (is_array($cached) && count($cached) > 0) {
            return $this->ok($cached);
        }

        $data = collect(GHNService::getProvinces())
            ->reject(fn ($p) => $this->isSandboxTestLocation($p['ProvinceName'] ?? $p['province_name'] ?? null, $p['ProvinceID'] ?? $p['province_id'] ?? null))
            ->map(fn ($p) => [
                'ProvinceID' => $p['ProvinceID'] ?? $p['province_id'] ?? null,
                'ProvinceName' => $p['ProvinceName'] ?? $p['province_name'] ?? null,
            ])
            ->filter(fn ($p) => $p['ProvinceID'] && $p['ProvinceName'])
            ->values()
            ->toArray();

        if (count($data) > 0) {
            Cache::put('ghn_provinces_v2', $data, self::TTL);
        }

        return $this->ok($data);
    }

    /**
     * GET /api/location/districts/{provinceCode}
     */
    public function getDistricts($provinceCode)
    {
        $cacheKey = "ghn_districts_v2_{$provinceCode}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) > 0) {
            return $this->ok($cached);
        }

        $data = collect(GHNService::getDistricts((int) $provinceCode))
            ->reject(fn ($d) => $this->isSandboxTestLocation($d['DistrictName'] ?? $d['district_name'] ?? null, $d['DistrictID'] ?? $d['district_id'] ?? null))
            ->map(fn ($d) => [
                'DistrictID' => $d['DistrictID'] ?? $d['district_id'] ?? null,
                'DistrictName' => $d['DistrictName'] ?? $d['district_name'] ?? null,
            ])
            ->filter(fn ($d) => $d['DistrictID'] && $d['DistrictName'])
            ->values()
            ->toArray();

        if (count($data) > 0) {
            Cache::put($cacheKey, $data, self::TTL);
        }

        return $this->ok($data);
    }

    /**
     * GET /api/location/wards/{districtCode}
     */
    public function getWards($districtCode)
    {
        $cacheKey = "ghn_wards_v2_{$districtCode}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) > 0) {
            return $this->ok($cached);
        }

        $data = collect(GHNService::getWards((int) $districtCode))
            ->reject(fn ($w) => $this->isSandboxTestLocation($w['WardName'] ?? $w['ward_name'] ?? null, $w['WardCode'] ?? $w['ward_code'] ?? null))
            ->map(fn ($w) => [
                'WardCode' => (string) ($w['WardCode'] ?? $w['ward_code'] ?? ''),
                'WardName' => $w['WardName'] ?? $w['ward_name'] ?? null,
            ])
            ->filter(fn ($w) => $w['WardCode'] && $w['WardName'])
            ->values()
            ->toArray();

        if (count($data) > 0) {
            Cache::put($cacheKey, $data, self::TTL);
        }

        return $this->ok($data);
    }

    /**
     * GET /api/location/search?q=keyword
     */
    public function search(Request $request)
    {
        $keyword = $request->get('q', '');

        if (strlen($keyword) < 2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Từ khóa tìm kiếm phải có ít nhất 2 ký tự.',
            ], 422);
        }

        $provinces = collect($this->getProvinces()->getData(true)['data'] ?? []);
        $matches = $provinces->filter(fn ($p) => str_contains(mb_strtolower($p['ProvinceName']), mb_strtolower($keyword)))->values();

        return $this->ok($matches);
    }

    private function isSandboxTestLocation(?string $name, mixed $id = null): bool
    {
        if (!$name) {
            return false;
        }

        $normalized = mb_strtolower($name);

        return in_array((string) $id, ['298', '2002'], true)
            || str_contains($normalized, 'test')
            || str_contains($normalized, 'alert')
            || trim($normalized) === 'hà nội 02';
    }

    private function ok($data)
    {
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
