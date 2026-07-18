<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Court;

class CourtAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Court::with(['schedules', 'prices']);
        
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $courts = $query->get();
        return response()->json([
            'status' => 'success',
            'data' => $courts
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'court_name' => 'required|string|max:100',
            'court_code' => 'required|string|max:20|unique:courts,court_code',
            'type' => 'required|in:standard,vip,outdoor,indoor',
            'status' => 'required|in:active,inactive,maintenance,closed',
        ]);

        $court = Court::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Court created successfully.',
            'data' => $court
        ]);
    }

    public function show($id)
    {
        $court = Court::findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $court
        ]);
    }

    public function update(Request $request, $id)
    {
        $court = Court::findOrFail($id);
        
        $validated = $request->validate([
            'court_name' => 'sometimes|string|max:100',
            'court_code' => 'sometimes|string|max:20|unique:courts,court_code,' . $court->court_id . ',court_id',
            'type' => 'sometimes|in:standard,vip,outdoor,indoor',
            'status' => 'sometimes|in:active,inactive,maintenance,closed',
        ]);

        $court->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Court updated successfully.',
            'data' => $court
        ]);
    }

    public function destroy($id)
    {
        $court = Court::findOrFail($id);
        $court->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Court deleted successfully.'
        ]);
    }
}
