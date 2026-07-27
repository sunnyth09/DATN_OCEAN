<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Khởi tạo hoặc lấy lại session live chat
     */
    public function initSession(Request $request)
    {
        $sessionToken = $request->input('session_token');

        $session = null;
        if ($sessionToken) {
            $session = ChatSession::where('session_token', $sessionToken)->first();
            // Nếu session đã bị admin đóng, thì tạo một session mới thay vì tái sử dụng
            if ($session && $session->status === 'closed') {
                $session = null;
                $sessionToken = null; // Bắt buộc phải là null để tạo UUID mới, vì UUID trong db là unique
            }
        }

        // Lấy thông tin user nếu có gửi Bearer token
        $user = auth('api')->user();

        if (! $session) {
            $session = ChatSession::create([
                'session_token' => $sessionToken ?: (string) Str::uuid(),
                'user_id' => $user ? $user->user_id : null,
                'status' => 'open',
            ]);
        } elseif ($user && ! $session->user_id) {
            // Update user_id nếu khách vừa đăng nhập
            $session->update(['user_id' => $user->user_id]);
        }

        return response()->json([
            'session' => $session,
            'messages' => $session->messages()->orderBy('created_at', 'asc')->get(),
        ]);
    }

    /**
     * User gửi tin nhắn
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'session_token' => 'required|string',
            'message' => 'required|string',
        ]);

        $sessionToken = $request->input('session_token');
        $session = ChatSession::where('session_token', $sessionToken)->firstOrFail();

        if ($session->status === 'closed') {
            return response()->json([
                'success' => false,
                'is_closed' => true,
                'message' => 'Hội thoại đã kết thúc. Vui lòng kết nối lại nhân viên hỗ trợ nếu cần.',
            ]);
        }

        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender_type' => 'user',
            'message' => $request->input('message'),
            'is_read' => false,
        ]);

        $session->update(['last_message_at' => now()]);

        broadcast(new MessageSent($message, $sessionToken));

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
