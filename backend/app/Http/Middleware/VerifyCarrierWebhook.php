<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác thực webhook từ hãng vận chuyển (Ocean Express / GHN).
 *
 * Vì sao bắt buộc: webhook là kênh duy nhất được phép đẩy đơn sang trạng thái
 * giao vận, và nhánh `cancelled` kích hoạt hoàn tiền ví + đảo chiết khấu + hoàn
 * tồn kho. tracking_number được in trên nhãn dán ngoài thùng hàng, nên nếu
 * endpoint không xác thực thì bất kỳ ai đọc được nhãn đều có thể giả webhook để
 * rút tiền ví.
 *
 * Dùng: Route::middleware('carrier.webhook:ocean_express')
 * Đọc cấu hình từ config/{$carrier}.php:
 *   webhook_token, webhook_secret, webhook_allowed_ips, webhook_require_auth
 *
 * Ba lớp, theo thứ tự rẻ → đắt:
 *   1. IP whitelist (nếu cấu hình)
 *   2. Shared token: ?token= hoặc header X-Webhook-Token (so sánh hash_equals)
 *   3. HMAC-SHA256 raw body, header X-Signature (mạnh nhất, chống replay body)
 *
 * Fail-closed: nếu webhook_require_auth=true mà chưa cấu hình token lẫn secret,
 * request bị từ chối. Thiếu biến môi trường không được phép âm thầm biến
 * endpoint thành công khai.
 */
class VerifyCarrierWebhook
{
    public function handle(Request $request, Closure $next, string $carrier): Response
    {
        $token = config("{$carrier}.webhook_token");
        $secret = config("{$carrier}.webhook_secret");
        $allowedIps = config("{$carrier}.webhook_allowed_ips", []);
        $requireAuth = (bool) config("{$carrier}.webhook_require_auth", false);

        // ── Lớp 1: IP whitelist ──
        if (! empty($allowedIps) && ! in_array($request->ip(), $allowedIps, true)) {
            return $this->reject($carrier, 'ip_not_whitelisted', $request);
        }

        $hasToken = ! empty($token);
        $hasSecret = ! empty($secret);

        // ── Fail-closed khi chưa cấu hình gì ──
        if (! $hasToken && ! $hasSecret) {
            if ($requireAuth) {
                Log::critical("Carrier webhook [{$carrier}] chưa cấu hình xác thực — request bị từ chối", [
                    'ip' => $request->ip(),
                    'hint' => 'Đặt '.strtoupper($carrier).'_WEBHOOK_TOKEN hoặc _WEBHOOK_SECRET trong .env',
                ]);

                return response()->json(['message' => 'Webhook authentication not configured'], 503);
            }

            // Chỉ đi tới đây khi vận hành chủ động tắt (dev/local)
            Log::warning("Carrier webhook [{$carrier}] đang chạy KHÔNG xác thực (require_auth=false)", [
                'ip' => $request->ip(),
            ]);

            return $next($request);
        }

        // ── Lớp 2: shared token ──
        $tokenOk = false;
        if ($hasToken) {
            $provided = $request->query('token') ?: $request->header('X-Webhook-Token', '');
            $tokenOk = is_string($provided) && hash_equals((string) $token, $provided);
        }

        // ── Lớp 3: HMAC-SHA256 trên raw body ──
        $signatureOk = false;
        if ($hasSecret) {
            $provided = (string) ($request->header('X-Signature') ?: $request->header('X-Hub-Signature-256', ''));
            // Chấp nhận cả dạng "sha256=<hex>"
            $provided = preg_replace('/^sha256=/i', '', $provided);
            $expected = hash_hmac('sha256', $request->getContent(), (string) $secret);
            $signatureOk = $provided !== '' && hash_equals($expected, $provided);
        }

        // Cấu hình cái nào thì cái đó phải hợp lệ — thoả MỘT trong các cơ chế đã bật là đủ,
        // nhưng nếu chỉ bật secret thì buộc phải có signature đúng.
        if (! $tokenOk && ! $signatureOk) {
            return $this->reject($carrier, 'invalid_token_or_signature', $request);
        }

        return $next($request);
    }

    private function reject(string $carrier, string $reason, Request $request): Response
    {
        Log::warning("Carrier webhook [{$carrier}] bị từ chối", [
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
