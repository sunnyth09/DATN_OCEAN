<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * TicketController — API hệ thống hỗ trợ khách hàng (ticket).
 *
 * Quản lý tạo ticket, phản hồi, đóng ticket,
 * và thông báo realtime khi có cập nhật mới.
 */
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
                $query->where(function ($q) use ($search) {
                    $q->where('reason', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($qu) use ($search) {
                            $qu->where('full_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('order', function ($qo) use ($search) {
                            $qo->where('order_code', 'like', "%{$search}%");
                        });
                });
            }

            $perPage = $request->input('per_page', 10);
            $tickets = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $tickets,
            ]);
        } catch (\Exception $e) {
            Log::error('Ticket list error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi lấy danh sách khiếu nại',
            ], 500);
        }
    }

    public function adminUpdate(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,processing,resolved,closed',
            'admin_reply' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $ticket = Ticket::findOrFail($id);
            $oldStatus = $ticket->status;
            $oldReply = $ticket->admin_reply;

            $ticket->status = $request->status;
            if ($request->has('admin_reply')) {
                $ticket->admin_reply = $request->admin_reply;
            }
            $ticket->save();

            // Gửi thông báo cho user nếu trạng thái thay đổi hoặc có phản hồi mới
            if ($ticket->status != $oldStatus || ($ticket->admin_reply && $ticket->admin_reply != $oldReply)) {
                $user = User::find($ticket->user_id);
                if ($user) {
                    $statusText = match ($ticket->status) {
                        'pending' => 'Chờ xử lý',
                        'processing' => 'Đang xử lý',
                        'resolved' => 'Đã giải quyết',
                        'closed' => 'Đã đóng',
                        default => $ticket->status
                    };
                    $title = 'Cập nhật khiếu nại #'.$ticket->ticket_id;
                    $message = 'Khiếu nại của bạn đã chuyển sang: '.$statusText.'.';
                    if ($ticket->admin_reply && $ticket->admin_reply != $oldReply) {
                        $message .= ' Admin: '.substr($ticket->admin_reply, 0, 60).'...';
                    }

                    $notificationData = [
                        'title' => $title,
                        'message' => $message,
                        'url_redirect' => '/profile', // user có thể xem trong profile
                        'icon' => 'bell',
                    ];

                    $user->notify(new SystemNotification(
                        $notificationData['title'],
                        $notificationData['message'],
                        $notificationData['url_redirect'],
                        $notificationData['icon']
                    ));
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật khiếu nại thành công',
                'data' => $ticket,
            ]);
        } catch (\Exception $e) {
            Log::error('Ticket update error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi cập nhật khiếu nại',
            ], 500);
        }
    }

    public function adminShow($id)
    {
        try {
            $ticket = Ticket::with(['user', 'order', 'product'])->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $ticket,
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
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $request->user()->user_id;

        // Guard 1: Đơn hàng phải thuộc về chính user này (tránh khiếu nại giả mạo đơn người khác)
        $order = Order::where('order_id', $request->order_id)
            ->where('user_id', $userId)
            ->first();

        if (! $order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Đơn hàng không tồn tại hoặc không thuộc về bạn.',
            ], 403);
        }

        // Guard 2: Giới hạn tối đa 3 ticket đang chờ xử lý (pending/processing) cùng lúc
        $openTicketCount = Ticket::where('user_id', $userId)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($openTicketCount >= 3) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn đang có '.$openTicketCount.' khiếu nại chờ xử lý. Vui lòng chờ xử lý trước khi gửi thêm.',
            ], 429);
        }

        try {
            $ticket = new Ticket;
            $ticket->user_id = $userId;
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
                'data' => $ticket,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Ticket create error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi tạo khiếu nại',
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
                'data' => $tickets,
            ]);
        } catch (\Exception $e) {
            Log::error('Ticket list error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi lấy danh sách khiếu nại',
            ], 500);
        }
    }
}
