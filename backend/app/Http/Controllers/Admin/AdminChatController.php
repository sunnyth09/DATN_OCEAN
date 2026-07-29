<?php

namespace App\Http\Controllers\Admin;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Http\Request;

class AdminChatController extends Controller
{
    /**
     * Lấy danh sách các session
     */
    public function getSessions()
    {
        $sessions = ChatSession::with('user')
            ->orderBy('last_message_at', 'desc')
            ->get()
            ->map(function ($session) {
                // Đếm tin nhắn chưa đọc
                $unreadCount = $session->messages()->where('sender_type', 'user')->where('is_read', false)->count();
                $session->unread_count = $unreadCount;

                return $session;
            });

        return response()->json($sessions);
    }

    /**
     * Lấy lịch sử chat
     */
    public function getMessages($id)
    {
        $session = ChatSession::with('user')->findOrFail($id);

        // Đánh dấu đã đọc
        $session->messages()->where('sender_type', 'user')->update(['is_read' => true]);

        return response()->json([
            'session' => $session,
            'messages' => $session->messages()->orderBy('created_at', 'asc')->get(),
        ]);
    }

    /**
     * Trả lời khách hàng
     */
    public function replyMessage(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $session = ChatSession::findOrFail($id);

        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender_type' => 'admin',
            'message' => $request->input('message'),
            'is_read' => false,
        ]);

        $session->update(['last_message_at' => now(), 'status' => 'open']);

        broadcast(new MessageSent($message, $session->session_token));

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * Đóng kết nối / Phiên
     */
    public function closeSession($id)
    {
        $session = ChatSession::findOrFail($id);
        $session->update(['status' => 'closed']);

        // Gửi thông điệp hệ thống để Client biết phiên đã kết thúc và LƯU LẠI log
        $systemMessage = ChatMessage::create([
            'sender_type' => 'admin',
            'message' => 'SYSTEM_SESSION_CLOSED',
            'chat_session_id' => $session->id,
            'is_read' => true,
        ]);
        broadcast(new MessageSent($systemMessage, $session->session_token));

        return response()->json(['success' => true]);
    }
}
