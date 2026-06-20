<?php

namespace App\Http\Controllers;

use App\Services\GHNService;
use Illuminate\Http\Request;

class GhnController extends Controller
{
    public function getLeadtime(Request $request)
    {
        $data = $request->validate([
            'to_district_id' => 'required|integer',
            'to_ward_code' => 'required|string',
        ]);

        return response()->json(GHNService::calculateLeadtime($data));
    }

    public function cancelOrder(Request $request)
    {
        $request->validate(['order_code' => 'required|string']);
        return response()->json(GHNService::cancelOrder($request->order_code));
    }

    public function printLabel(Request $request)
    {
        $request->validate(['order_code' => 'required|string']);
        return response()->json(GHNService::printLabel($request->order_code));
    }
}
