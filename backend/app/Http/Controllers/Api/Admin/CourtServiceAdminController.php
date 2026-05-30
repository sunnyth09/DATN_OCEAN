<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CourtService;

class CourtServiceAdminController extends Controller
{
    public function index(Request $request)
    {
        $services = CourtService::orderBy('sort_order')->get();
        return response()->json([
            'status' => 'success',
            'data' => $services
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_name' => 'required|string|max:100',
            'service_code' => 'required|string|max:30|unique:court_services,service_code',
            'unit' => 'required|in:piece,bottle,set,hour,other',
            'unit_price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $service = CourtService::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Service created successfully.',
            'data' => $service
        ]);
    }

    public function show($id)
    {
        $service = CourtService::findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $service
        ]);
    }

    public function update(Request $request, $id)
    {
        $service = CourtService::findOrFail($id);

        $validated = $request->validate([
            'service_name' => 'sometimes|string|max:100',
            'service_code' => 'sometimes|string|max:30|unique:court_services,service_code,' . $service->service_id . ',service_id',
            'unit' => 'sometimes|in:piece,bottle,set,hour,other',
            'unit_price' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $service->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Service updated successfully.',
            'data' => $service
        ]);
    }

    public function destroy($id)
    {
        $service = CourtService::findOrFail($id);
        $service->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Service deleted successfully.'
        ]);
    }
}
