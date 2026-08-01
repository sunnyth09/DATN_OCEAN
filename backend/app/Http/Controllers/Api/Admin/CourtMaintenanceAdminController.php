<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourtMaintenance;
use Illuminate\Http\Request;

class CourtMaintenanceAdminController extends Controller
{
    public function index(Request $request)
    {
        $maintenances = CourtMaintenance::with(['court', 'createdBy'])->orderBy('start_datetime', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $maintenances,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'court_id' => 'required|exists:courts,court_id',
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
        ]);

        $validated['created_by'] = auth()->guard('admin')->id();

        $maintenance = CourtMaintenance::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Maintenance created successfully.',
            'data' => $maintenance,
        ]);
    }

    public function show($id)
    {
        $maintenance = CourtMaintenance::with(['court', 'createdBy'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $maintenance,
        ]);
    }

    public function update(Request $request, $id)
    {
        $maintenance = CourtMaintenance::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'start_datetime' => 'sometimes|date',
            'end_datetime' => 'sometimes|date|after:start_datetime',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled',
        ]);

        $maintenance->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Maintenance updated successfully.',
            'data' => $maintenance,
        ]);
    }

    public function destroy($id)
    {
        $maintenance = CourtMaintenance::findOrFail($id);
        $maintenance->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Maintenance deleted successfully.',
        ]);
    }
}
