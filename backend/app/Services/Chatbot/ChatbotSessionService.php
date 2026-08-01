<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChatbotSessionService
{
    /**
     * Lấy hoặc tạo session_id cho Chatbot
     */
    public function getSessionId(?string $providedSessionId, $user = null): string
    {
        if ($user) {
            return 'user_'.$user->user_id;
        }

        if ($providedSessionId && Str::length($providedSessionId) > 10) {
            return 'guest_'.$providedSessionId;
        }

        return 'guest_'.Str::uuid()->toString();
    }

    /**
     * Lưu danh sách ID sản phẩm vừa được AI gợi ý vào Context
     */
    public function saveRecommendedProducts(string $sessionId, array $productIds): void
    {
        $key = 'chatbot_context_'.$sessionId.'_products';
        Cache::put($key, $productIds, now()->addMinutes(30));
    }

    /**
     * Lấy Product ID từ câu lệnh "Cái đầu tiên", "Cái thứ hai"
     */
    public function getProductFromContext(string $sessionId, string $reference): ?int
    {
        $key = 'chatbot_context_'.$sessionId.'_products';
        $productIds = Cache::get($key, []);

        if (empty($productIds)) {
            return null;
        }

        $normalizedRef = mb_strtolower($reference);
        $index = -1;

        if (preg_match('/dau tien|thu 1|so 1|thu nhat/u', $normalizedRef)) {
            $index = 0;
        } elseif (preg_match('/thu 2|so 2|thu hai/u', $normalizedRef)) {
            $index = 1;
        } elseif (preg_match('/thu 3|so 3|thu ba/u', $normalizedRef)) {
            $index = 2;
        } elseif (preg_match('/cuoi cung|cuoi/u', $normalizedRef)) {
            $index = count($productIds) - 1;
        }

        if ($index >= 0 && isset($productIds[$index])) {
            return (int) $productIds[$index];
        }

        return null;
    }

    /**
     * Dọn dẹp context
     */
    public function clearContext(string $sessionId): void
    {
        $key = 'chatbot_context_'.$sessionId.'_products';
        Cache::forget($key);
    }
}
