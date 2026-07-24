<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function getMessages(Request $request)
    {
        $user = $request->user();
        
        $session = DB::table('chat_sessions')
            ->where('user_id', $user->user_id)
            ->where('status', 'open')
            ->first();

        if (!$session) {
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        }

        $messages = DB::table('chat_messages')
            ->where('chat_session_id', $session->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $messages
        ]);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $user = $request->user();

        $session = DB::table('chat_sessions')
            ->where('user_id', $user->user_id)
            ->where('status', 'open')
            ->first();

        if (!$session) {
            $sessionId = DB::table('chat_sessions')->insertGetId([
                'session_token' => \Illuminate\Support\Str::uuid(),
                'user_id' => $user->user_id,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $sessionId = $session->id;
            DB::table('chat_sessions')->where('id', $sessionId)->update([
                'last_message_at' => now(),
                'updated_at' => now()
            ]);
        }

        $messageId = DB::table('chat_messages')->insertGetId([
            'chat_session_id' => $sessionId,
            'sender_type' => 'user',
            'message' => $request->message,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $message = DB::table('chat_messages')->where('id', $messageId)->first();

        return response()->json([
            'status' => 'success',
            'data' => $message
        ]);
    }
}
