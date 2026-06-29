<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * LocationController — Cung cấp danh sách Tỉnh/Quận/Phường cho frontend.
 *
 * Nguồn dữ liệu: provinces.open-api.vn (API công khai, KHÔNG cần token).
 * Trước đây code gọi GHN gateway cần token sandbox (đã hết hạn → 401 / rỗng),
 * khiến dropdown địa chỉ ở trang checkout không tải được → khách vãng lai
 * không thể đặt hàng.
 *
 * Trả về key tương thích với frontend AddressSelector (ProvinceID/ProvinceName,
 * DistrictID/DistrictName, WardCode/WardName) để không phải sửa component.
 *
 * Lưu ý: chỉ cache kết quả khi có dữ liệu (tránh cache rỗng 24h khi API lỗi tạm thời).
 */
class LocationController extends Controller
{
    private string $apiBaseUrl = 'https://provinces.open-api.vn/api/';
    private const TTL = 86400; // 24h

    /**
     * GET /api/location/provinces
     */
    public function getProvinces()
    {
        $cached = Cache::get('vn_provinces_v2');
        if (is_array($cached) && count($cached) > 0) {
            return $this->ok($cached);
        }

        $data = [];
        try {
            $response = Http::timeout(10)->get($this->apiBaseUrl, ['depth' => 1]);

            if ($response->successful()) {
                $data = collect($response->json())->map(fn ($p) => [
                    'ProvinceID'   => $p['code'],
                    'ProvinceName' => $p['name'],
                ])->values()->toArray();
            }
        } catch (\Throwable $e) {
            $data = [];
        }

        if (count($data) > 0) {
            Cache::put('vn_provinces_v2', $data, self::TTL);
        }

        return $this->ok($data);
    }

    /**
     * GET /api/location/districts/{provinceCode}
     */
    public function getDistricts($provinceCode)
    {
        $cacheKey = "vn_districts_v2_{$provinceCode}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) > 0) {
            return $this->ok($cached);
        }

        $data = [];
        try {
            $response = Http::timeout(10)->get($this->apiBaseUrl . "p/{$provinceCode}", ['depth' => 2]);

            if ($response->successful()) {
                $data = collect($response->json()['districts'] ?? [])->map(fn ($d) => [
                    'DistrictID'   => $d['code'],
                    'DistrictName' => $d['name'],
                ])->values()->toArray();
            }
        } catch (\Throwable $e) {
            $data = [];
        }

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
        $cacheKey = "vn_wards_v2_{$districtCode}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) > 0) {
            return $this->ok($cached);
        }

        $data = [];
        try {
            $response = Http::timeout(10)->get($this->apiBaseUrl . "d/{$districtCode}", ['depth' => 2]);

            if ($response->successful()) {
                $data = collect($response->json()['wards'] ?? [])->map(fn ($w) => [
                    'WardCode' => (string) $w['code'],
                    'WardName' => $w['name'],
                ])->values()->toArray();
            }
        } catch (\Throwable $e) {
            $data = [];
        }

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
                'status'  => 'error',
                'message' => 'Từ khóa tìm kiếm phải có ít nhất 2 ký tự.',
            ], 422);
        }

        try {
            $response = Http::timeout(10)->get($this->apiBaseUrl . 'p/search/', ['q' => $keyword]);

            if ($response->successful()) {
                return $this->ok($response->json());
            }
        } catch (\Throwable $e) {
            // fallthrough
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'Không thể tìm kiếm địa điểm.',
        ], 500);
    }

    private function ok($data)
    {
        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }
}
