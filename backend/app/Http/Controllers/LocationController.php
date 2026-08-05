<?php

namespace App\Http\Controllers;

use App\Services\LocationService;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct(private LocationService $locationService) {}

    public function getProvinces()
    {
        $data = $this->locationService->getProvinces();

        return $this->ok($data);
    }

    public function getWards(string $provinceCode)
    {
        $data = $this->locationService->getWards($provinceCode);

        return $this->ok($data);
    }

    public function search(Request $request)
    {
        $keyword = $request->get('q', '');

        if (strlen($keyword) < 2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Từ khóa tìm kiếm phải có ít nhất 2 ký tự.',
            ], 422);
        }

        $provinces = collect($this->locationService->getProvinces());
        $matches = $provinces
            ->filter(fn ($p) => str_contains(mb_strtolower($p['name']), mb_strtolower($keyword)))
            ->values();

        return $this->ok($matches);
    }

    private function ok($data)
    {
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
