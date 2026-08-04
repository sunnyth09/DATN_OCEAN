<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * LocationController — Cung cấp danh sách Tỉnh/Quận/Phường cho frontend.
 * Địa lý được lấy từ Ocean Express API (type: province / ward).
 *
 * Lưu ý: Ocean Express không có cấp Quận/Huyện riêng biệt.
 * Để giữ UI 3 bước (Tỉnh → Quận → Phường), ta dùng 1 district "ảo" mỗi tỉnh.
 * Ward được lấy theo parent_id = province_id (e.g. "VN-01").
 */
class LocationController extends Controller
{
    private const TTL = 86400; // 24h

    public function getProvinces()
    {
        $cached = Cache::get('oe_loc_provinces_v3');
        if (is_array($cached) && count($cached) > 0) {
            return $this->ok($cached);
        }

        // Gọi Ocean Express API: GET /locations?type=province
        $locations = \App\Services\OceanExpressService::getLocations('province');

        $data = collect($locations)
            ->map(fn ($p) => [
                'ProvinceID'   => $p['id'],
                'ProvinceName' => $p['name'],
            ])
            ->values()
            ->toArray();

        if (count($data) > 0) {
            Cache::put('oe_loc_provinces_v3', $data, self::TTL);
        }

        return $this->ok($data);
    }

    /**
     * Ocean Express không có cấp Quận/Huyện.
     * Trả về 1 district ảo để UI 3-bước hoạt động.
     * DistrictID = provinceCode (e.g. "VN-01") để getWards() có thể dùng làm parent_id.
     */
    public function getDistricts(string $provinceCode)
    {
        $data = [
            [
                'DistrictID'   => $provinceCode,
                'DistrictName' => 'Tất cả Quận/Huyện',
            ],
        ];

        return $this->ok($data);
    }

    /**
     * Lấy danh sách Phường/Xã theo Tỉnh.
     * districtCode ở đây chính là provinceCode (VD: "VN-01") vì district là ảo.
     * Ocean Express API: GET /locations?type=ward&parent_id=<provinceCode>
     */
    public function getWards(string $districtCode)
    {
        // districtCode = province_id (e.g. "VN-01") — district không có trong Ocean Express
        $provinceCode = $districtCode;
        $cacheKey = "oe_loc_wards_v3_{$provinceCode}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) > 0) {
            return $this->ok($cached);
        }

        // Gọi Ocean Express API: GET /locations?type=ward&parent_id=VN-01
        $locations = \App\Services\OceanExpressService::getLocations('ward', $provinceCode);

        $data = collect($locations)
            ->map(fn ($w) => [
                'WardCode' => (string) $w['id'],
                'WardName' => $w['name'],
            ])
            ->values()
            ->toArray();

        if (count($data) > 0) {
            Cache::put($cacheKey, $data, self::TTL);
        }

        return $this->ok($data);
    }

    public function search(Request $request)
    {
        $keyword = $request->get('q', '');

        if (strlen($keyword) < 2) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Từ khóa tìm kiếm phải có ít nhất 2 ký tự.',
            ], 422);
        }

        $provinces = collect($this->getProvinces()->getData(true)['data'] ?? []);
        $matches   = $provinces
            ->filter(fn ($p) => str_contains(mb_strtolower($p['ProvinceName']), mb_strtolower($keyword)))
            ->values();

        return $this->ok($matches);
    }

    private function ok($data)
    {
        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }
}
