<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourtService;
use Illuminate\Http\Request;

class CourtServiceAdminController extends Controller
{
    public function index(Request $request)
    {
        $services = CourtService::orderBy('sort_order')->get();

        return response()->json([
            'status' => 'success',
            'data' => $services,
        ]);
    }

    public function store(Request $request)
    {
        // Support frontend field names (name/price/status) as aliases
        $input = $request->all();
        if (! isset($input['service_name']) && isset($input['name'])) {
            $input['service_name'] = $input['name'];
        }
        if (! isset($input['unit_price']) && isset($input['price'])) {
            $input['unit_price'] = $input['price'];
        }
        if (! isset($input['is_active']) && isset($input['status'])) {
            $input['is_active'] = $input['status'] === 'active' ? true : false;
        }
        if (! isset($input['service_code'])) {
            $input['service_code'] = 'SVC-'.strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $input['service_name'] ?? 'SVC'), 0, 6)).'-'.rand(100, 999);
        }
        if (! isset($input['unit'])) {
            $input['unit'] = 'piece';
        }
        $request->merge($input);

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
            'data' => $service,
        ]);
    }

    public function show($id)
    {
        $service = CourtService::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $service,
        ]);
    }

    public function update(Request $request, $id)
    {
        $service = CourtService::findOrFail($id);

        // Support frontend field names (name/price/status) as aliases
        $input = $request->all();
        if (! isset($input['service_name']) && isset($input['name'])) {
            $input['service_name'] = $input['name'];
        }
        if (! isset($input['unit_price']) && isset($input['price'])) {
            $input['unit_price'] = $input['price'];
        }
        if (! isset($input['is_active']) && isset($input['status'])) {
            $input['is_active'] = $input['status'] === 'active' ? true : false;
        }
        $request->merge($input);

        $validated = $request->validate([
            'service_name' => 'sometimes|string|max:100',
            'service_code' => 'sometimes|string|max:30|unique:court_services,service_code,'.$service->service_id.',service_id',
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
            'data' => $service,
        ]);
    }

    public function destroy($id)
    {
        $service = CourtService::findOrFail($id);
        $service->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Service deleted successfully.',
        ]);
    }
}
