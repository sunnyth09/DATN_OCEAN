<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class GeoHelper
{
    /**
     * Bán kính trái đất tính bằng mét.
     */
    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * Tính khoảng cách giữa 2 điểm GPS bằng Haversine Formula.
     *
     * @param  float  $lat1  Vĩ độ điểm 1
     * @param  float  $lon1  Kinh độ điểm 1
     * @param  float  $lat2  Vĩ độ điểm 2
     * @param  float  $lon2  Kinh độ điểm 2
     * @return float Khoảng cách tính bằng mét
     */
    public static function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * Kiểm tra điểm GPS có nằm trong bán kính cho phép không.
     *
     * @param  float  $lat  Vĩ độ điểm cần kiểm tra
     * @param  float  $lon  Kinh độ điểm cần kiểm tra
     * @param  float  $centerLat  Vĩ độ tâm (vị trí làm việc)
     * @param  float  $centerLon  Kinh độ tâm (vị trí làm việc)
     * @param  float  $radiusMeters  Bán kính cho phép (mét)
     * @return array ['is_valid' => bool, 'distance_meters' => float]
     */
    public static function isWithinRadius(
        float $lat, float $lon,
        float $centerLat, float $centerLon,
        float $radiusMeters
    ): array {
        $distance = self::haversineDistance($lat, $lon, $centerLat, $centerLon);

        return [
            'is_valid' => $distance <= $radiusMeters,
            'distance_meters' => round($distance, 2),
        ];
    }

    /**
     * Tìm work_location gần nhất mà user đang nằm trong bán kính cho phép.
     * Duyệt qua tất cả locations, tìm location valid có khoảng cách nhỏ nhất.
     *
     * @param  float  $lat  Vĩ độ user
     * @param  float  $lon  Kinh độ user
     * @param  Collection  $locations  Collection các WorkLocation (phải có latitude, longitude, radius_meters)
     * @return array|null ['location' => WorkLocation, 'distance_meters' => float] hoặc null nếu không tìm thấy
     */
    public static function findNearestValidLocation(float $lat, float $lon, Collection $locations): ?array
    {
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($locations as $location) {
            $result = self::isWithinRadius(
                $lat, $lon,
                (float) $location->latitude,
                (float) $location->longitude,
                (float) $location->radius_meters
            );

            if ($result['is_valid'] && $result['distance_meters'] < $minDistance) {
                $minDistance = $result['distance_meters'];
                $nearest = [
                    'location' => $location,
                    'distance_meters' => $result['distance_meters'],
                ];
            }
        }

        return $nearest;
    }

    /**
     * Tìm work_location gần nhất (bất kể có nằm trong bán kính hay không).
     * Dùng để trả về thông tin khi user check-in ngoài phạm vi.
     *
     * @param  float  $lat  Vĩ độ user
     * @param  float  $lon  Kinh độ user
     * @param  Collection  $locations  Collection các WorkLocation
     * @return array|null ['location' => WorkLocation, 'distance_meters' => float]
     */
    public static function findNearestLocation(float $lat, float $lon, Collection $locations): ?array
    {
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($locations as $location) {
            $distance = self::haversineDistance(
                $lat, $lon,
                (float) $location->latitude,
                (float) $location->longitude
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = [
                    'location' => $location,
                    'distance_meters' => round($distance, 2),
                ];
            }
        }

        return $nearest;
    }
}
