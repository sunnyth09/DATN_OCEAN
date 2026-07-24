<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FilterProfanity
{
    /**
     * Danh sách các từ khóa thô tục cần chặn.
     * Có thể mở rộng thêm hoặc chuyển sang database/config nếu cần.
     *
     * @var array
     */
    protected array $badWords = [
        'địt', 'đụ', 'lồn', 'cặc', 'buồi', 'đéo', 'vcl', 'đcm', 'đm',
        'chó đẻ', 'mẹ mày', 'bố mày', 'cút', 'vãi lồn', 'như loz', 'vl', 'cc', 'clmm',
        'cứt', 'đĩ', 'phò', 'đĩ điếm', 'ngu lồn', 'ngu như chó', 'rác rưởi',
        // Thêm các biến thể lách luật phổ biến (không dấu, viết tắt)
        'dit', 'đit', 'dm', 'lon', 'deo', 'loz', 'cac'
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $normalizedInput = mb_strtolower($value, 'UTF-8');
                if (class_exists('Normalizer')) {
                    $normalizedInput = \Normalizer::normalize($normalizedInput, \Normalizer::FORM_C);
                }
                
                foreach ($this->badWords as $word) {
                    $normalizedWord = mb_strtolower($word, 'UTF-8');
                    if (class_exists('Normalizer')) {
                        $normalizedWord = \Normalizer::normalize($normalizedWord, \Normalizer::FORM_C);
                    }
                    
                    // Loại bỏ tất cả khoảng trắng trong từ khóa gốc (ví dụ: 'chó đẻ' -> 'chóđẻ')
                    $normalizedWord = preg_replace('/\s+/u', '', $normalizedWord);
                    
                    // Tách thành từng ký tự Unicode
                    $chars = mb_str_split($normalizedWord, 1, 'UTF-8');
                    
                    // Sinh regex: mỗi ký tự có thể lặp lại (địttt) và cách nhau bởi các ký tự không phải chữ cái (., -, _, khoảng trắng...)
                    // (?<!\p{L}) và (?!\p{L}) đảm bảo cụm từ bị chặn đứng độc lập, không nằm lẫn trong một từ hợp lệ khác (chống false positive)
                    $escapedChars = array_map(function($c) {
                        return preg_quote($c, '/') . '+';
                    }, $chars);
                    
                    $regex = '/(?<!\p{L})' . implode('[^\p{L}]*', $escapedChars) . '(?!\p{L})/u';
                    
                    if (preg_match($regex, $normalizedInput)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Nội dung của bạn chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa trước khi gửi.',
                        ], 400);
                    }
                }
            }
        }

        return $next($request);
    }
}
