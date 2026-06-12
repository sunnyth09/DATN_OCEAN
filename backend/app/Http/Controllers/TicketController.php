<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    // ====== ADMIN METHODS ======

    public function adminIndex(Request $request)
    {
        try {
            $query = Ticket::with(['user:user_id,full_name,email', 'order:order_id,order_code', 'product:product_id,name']);

            if ($request->has('status') && $request->status != 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('reason', 'like', "%{$search}%")
                      ->orWhereHas('user', function($qu) use ($search) {
                          $qu->where('full_name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                      })
                      ->orWhereHas('order', function($qo) use ($search) {
                          $qo->where('order_code', 'like', "%{$search}%");
                      });
                });
            }

            $tickets = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => $tickets
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi lấy danh sách khiếu nại',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function adminUpdate(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,processing,resolved,closed',
            'admin_reply' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ticket = Ticket::findOrFail($id);
            $ticket->status = $request->status;
            if ($request->has('admin_reply')) {
                $ticket->admin_reply = $request->admin_reply;
            }
            $ticket->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật khiếu nại thành công',
                'data' => $ticket
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi cập nhật khiếu nại',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function adminShow($id)
    {
        try {
            $ticket = Ticket::with(['user', 'order', 'product'])->findOrFail($id);
            return response()->json([
                'status' => 'success',
                'data' => $ticket
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy khiếu nại',
            ], 404);
        }
    }

    // ====== CLIENT METHODS ======

    public function clientStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,order_id',
            'product_id' => 'nullable|exists:products,product_id',
            'reason' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ticket = new Ticket();
            $ticket->user_id = $request->user()->user_id;
            $ticket->order_id = $request->order_id;
            $ticket->product_id = $request->product_id;
            $ticket->reason = $request->reason;
            $ticket->description = $request->description;
            $ticket->status = 'pending';

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('tickets', 'public');
                $ticket->image_url = $imagePath;
            }

            $ticket->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Gửi khiếu nại thành công',
                'data' => $ticket
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi tạo khiếu nại',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function clientIndex(Request $request)
    {
        try {
            $userId = $request->user()->user_id;
            $tickets = Ticket::with(['order', 'product'])
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $tickets
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi lấy danh sách khiếu nại',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
