<?php

namespace App\Http\Controllers;

use App\Events\TicketCreatedAdmin;
use App\Mail\TicketReplyMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
            $oldReply = trim((string) $ticket->admin_reply);
            $newStatus = $request->status;
            $newReply = trim((string) ($request->admin_reply ?? ''));

            $statusLabels = [
                'pending' => 'Chờ xử lý',
                'processing' => 'Đang xử lý',
                'resolved' => 'Đã giải quyết',
                'closed' => 'Đã đóng',
            ];

            // State Machine Transition Rules
            $allowedTransitions = [
                'pending' => ['pending', 'processing', 'resolved', 'closed'],
                'processing' => ['processing', 'resolved', 'closed'],
                'resolved' => ['resolved', 'closed'],
                'closed' => ['closed'],
            ];

            if (! in_array($newStatus, $allowedTransitions[$oldStatus] ?? [])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không thể chuyển trạng thái khiếu nại từ "'.($statusLabels[$oldStatus] ?? $oldStatus).'" sang "'.($statusLabels[$newStatus] ?? $newStatus).'".',
                ], 422);
            }

            // Nếu khiếu nại đã đóng (closed), khóa hoàn toàn không cho sửa đổi thêm
            if ($oldStatus === 'closed') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Khiếu nại này đã đóng, không thể chỉnh sửa thêm.',
                ], 422);
            }

            $isStatusChanged = ($oldStatus !== $newStatus);
            $isReplyChanged = ($oldReply !== $newReply);

            // Nếu không có thay đổi nào về cả status lẫn reply, trả về thành công nhưng không gửi email thừa
            if (! $isStatusChanged && ! $isReplyChanged) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Không có thay đổi nào cần cập nhật',
                    'data' => $ticket,
                ]);
            }

            $ticket->status = $newStatus;
            $ticket->admin_reply = $newReply ?: null;
            $ticket->save();

            // Gửi thông báo & email cho user CHỈ KHI có thay đổi thực sự
            if ($isStatusChanged || ($isReplyChanged && ! empty($ticket->admin_reply))) {
                $user = User::find($ticket->user_id);
                if ($user) {
                    $statusText = $statusLabels[$ticket->status] ?? $ticket->status;
                    $title = 'Cập nhật khiếu nại #'.$ticket->ticket_id;
                    $message = 'Khiếu nại của bạn đã chuyển sang: '.$statusText.'.';
                    if ($isReplyChanged && ! empty($ticket->admin_reply)) {
                        $message .= ' Admin: '.Str::limit($ticket->admin_reply, 60);
                    }

                    // Gửi email thông báo (bỏ vào Queue để không block request)
                    try {
                        Mail::to($user->email)->queue(new TicketReplyMail($ticket));
                    } catch (\Exception $mailEx) {
                        Log::warning('TicketReplyMail failed to queue: '.$mailEx->getMessage());
                    }

                    // Gửi thông báo in-app
                    $user->notify(new SystemNotification(
                        $title,
                        $message,
                        '/profile/tickets',
                        'bell'
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
            event(new TicketCreatedAdmin($ticket));

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
