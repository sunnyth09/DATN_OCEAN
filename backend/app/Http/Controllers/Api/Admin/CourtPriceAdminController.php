<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CourtPrice;

class CourtPriceAdminController extends Controller
{
    public function index(Request $request)
    {
        $prices = CourtPrice::with('court')->get();
        return response()->json([
            'status' => 'success',
            'data' => $prices
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'court_id' => 'required|exists:courts,court_id',
            'price_name' => 'nullable|string|max:100',
            'day_type' => 'required|in:weekday,weekend,holiday,all',
            'from_time' => 'required|date_format:H:i',
            'to_time' => 'required|date_format:H:i|after:from_time',
            'price_per_hour' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $price = CourtPrice::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Price created successfully.',
            'data' => $price
        ]);
    }

    public function show($id)
    {
        $price = CourtPrice::with('court')->findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $price
        ]);
    }

    public function update(Request $request, $id)
    {
        $price = CourtPrice::findOrFail($id);

        $validated = $request->validate([
            'price_name' => 'nullable|string|max:100',
            'day_type' => 'sometimes|in:weekday,weekend,holiday,all',
            'from_time' => 'sometimes|date_format:H:i',
            'to_time' => 'sometimes|date_format:H:i|after:from_time',
            'price_per_hour' => 'sometimes|numeric|min:0',
            'is_active' => 'boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $price->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Price updated successfully.',
            'data' => $price
        ]);
    }

    public function destroy($id)
    {
        $price = CourtPrice::findOrFail($id);
        $price->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Price deleted successfully.'
        ]);
    }
}
