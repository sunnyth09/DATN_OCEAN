<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BrandController extends Controller
{
    /**
     * Lấy danh sách tất cả các thương hiệu (Brands).
     * Dữ liệu được cache trong 1 ngày (86400 giây) để tăng hiệu suất.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $brands = Cache::remember('brands:all', 86400, function () {
            return Brand::all();
        });

        return response()->json($brands);
    }
}
