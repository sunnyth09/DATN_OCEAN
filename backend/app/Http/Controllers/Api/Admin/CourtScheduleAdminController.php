<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourtSchedule;
use Illuminate\Http\Request;

class CourtScheduleAdminController extends Controller
{
    public function index(Request $request)
    {
        $schedules = CourtSchedule::with('court')->get();

        return response()->json([
            'status' => 'success',
            'data' => $schedules,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'court_id' => 'required|exists:courts,court_id',
            'day_of_week' => 'required|integer|min:0|max:6',
            'open_time' => 'required|date_format:H:i',
            'close_time' => 'required|date_format:H:i|after:open_time',
            'is_active' => 'boolean',
        ]);

        $schedule = CourtSchedule::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Schedule created successfully.',
            'data' => $schedule,
        ]);
    }

    public function show($id)
    {
        $schedule = CourtSchedule::with('court')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $schedule,
        ]);
    }

    public function update(Request $request, $id)
    {
        $schedule = CourtSchedule::findOrFail($id);

        $validated = $request->validate([
            'day_of_week' => 'sometimes|integer|min:0|max:6',
            'open_time' => 'sometimes|date_format:H:i',
            'close_time' => 'sometimes|date_format:H:i|after:open_time',
            'is_active' => 'boolean',
        ]);

        $schedule->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Schedule updated successfully.',
            'data' => $schedule,
        ]);
    }

    public function destroy($id)
    {
        $schedule = CourtSchedule::findOrFail($id);
        $schedule->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Schedule deleted successfully.',
        ]);
    }
}
