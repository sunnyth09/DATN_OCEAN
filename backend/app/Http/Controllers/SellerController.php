<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    public function index()
    {
        $user = User::whereIn('role', ['seller', 'staff', 'admin'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $user,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'in:admin,staff,seller',
        ]);

        $user = User::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role ?? 'seller',
            'status' => 'active',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $user,
            'message' => 'Đã tạo nhân sự mới',
        ]);
    }

    public function show($id)
    {
        $user = User::find($id);

        return response()->json([
            'status' => 'success',
            'data' => $user,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        $user->update($request->all());

        return response()->json([
            'status' => 'success',
            'data' => $user,
        ]);
    }

    public function destroy($id) // Xóa mềm
    {
        $user = User::find($id);
        $user->delete();

        return response()->json([
            'status' => 'success',
            'data' => $user,
        ]);
    }
}
