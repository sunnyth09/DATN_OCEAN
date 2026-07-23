<?php
namespace App\Http\Controllers;
class TestCorsController extends Controller {
    public function test() {
        return response()->json(['status' => 'success', 'message' => 'Test CORS 200 OK']);
    }
}
