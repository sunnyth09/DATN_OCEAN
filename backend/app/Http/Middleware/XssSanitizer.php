<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mews\Purifier\Facades\Purifier;

class XssSanitizer
{
    /**
     * Các field chứa HTML rich (từ WYSIWYG) cần được LỌC (sanitize) chứ không encode.
     * Chỉ những field này được đưa qua HTMLPurifier để loại bỏ script/XSS mà vẫn
     * giữ được các thẻ an toàn.
     */
    private const HTML_FIELDS = ['content', 'description', 'body', 'html_content'];

    public function handle(Request $request, Closure $next)
    {
        // KHÔNG encode toàn bộ input ở tầng này nữa.
        //
        // Lý do: encode output-time (htmlspecialchars) tại tầng input sẽ:
        //   - Làm hỏng dữ liệu (tên, địa chỉ, mã coupon chứa & < > " ' bị lưu mã hóa),
        //   - Double-encode lũy tiến mỗi lần sửa (& → &amp; → &amp;amp;),
        //   - Mangle mật khẩu/token trước khi hash/verify,
        //   - KHÔNG chống được SQLi (đã dùng Eloquent parameterized) và cũng không
        //     phải là lớp bảo vệ đúng chỗ cho stored-XSS (phải escape lúc render).
        //
        // Chống XSS đúng cách = escape lúc OUTPUT (Blade {{ }} / auto-escape ở frontend).
        // Ở đây ta chỉ SANITIZE các field HTML rich thật sự (WYSIWYG) để loại bỏ
        // script/tag nguy hiểm mà vẫn giữ định dạng.
        $input = $request->all();
        $touched = false;

        array_walk_recursive($input, function (&$value, $key) use (&$touched, $request) {
            if (is_string($value) && in_array($key, self::HTML_FIELDS, true)) {
                if ($this->shouldSkipPurification($request, $key)) {
                    return;
                }
                $value = $this->sanitizeHtml($value);
                $touched = true;
            }
        });

        if ($touched) {
            $request->merge($input);
        }

        return $next($request);
    }

    private function shouldSkipPurification(Request $request, string $key): bool
    {
        if ($key === 'content') {
            if ($request->is('api/posts/*/comments') 
                || $request->is('api/orders/feedback*') 
                || $request->is('api/chat/*') 
                || $request->is('api/live-chat/*') 
                || $request->is('api/chatbot/*')) {
                return true;
            }
        }

        if ($key === 'description') {
            if ($request->is('api/tickets')) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeHtml(string $value): string
    {
        if (class_exists(Purifier::class)) {
            return Purifier::clean($value);
        }

        return strip_tags($value);
    }
}
