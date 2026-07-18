<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Str;

class IntentParserService
{
    private array $categoryMap = [
        'mat kinh' => 'Mắt kính',
        'kinh' => 'Mắt kính',
        'ao' => 'Áo',
        'quan' => 'Quần',
        'giay' => 'Giày',
        'dep' => 'Dép',
        'tui' => 'Túi',
        'balo' => 'Balo',
        'mu' => 'Mũ',
        'non' => 'Nón',
        'phu kien' => 'Phụ kiện',
        'vot' => 'Vợt',
        'bong' => 'Bóng',
    ];

    private array $colorMap = [
        'den' => 'đen', 'black' => 'đen',
        'trang' => 'trắng', 'white' => 'trắng',
        'xanh' => 'xanh', 'blue' => 'xanh', 'green' => 'xanh',
        'do' => 'đỏ', 'red' => 'đỏ',
        'hong' => 'hồng', 'pink' => 'hồng',
        'xam' => 'xám', 'gray' => 'xám', 'grey' => 'xám',
        'nau' => 'nâu', 'brown' => 'nâu',
        'be' => 'be', 'beige' => 'be',
        'vang' => 'vàng', 'yellow' => 'vàng'
    ];

    /**
     * Dịch từ tin nhắn sang Intent format
     */
    public function parse(string $message): array
    {
        $normalized = $this->normalizeVietnameseText($message);
        
        // 1. Fallback nếu câu quá ngắn
        if ($normalized === '' || mb_strlen($normalized) <= 2) {
            return $this->buildResult('greeting');
        }

        // 2. Chào hỏi
        if (preg_match('/\b(chao|hello|hi|hey|xin chao)\b/u', $normalized)) {
            return $this->buildResult('greeting');
        }

        // 3. Chính sách, cửa hàng
        if (preg_match('/\b(doi tra|bao hanh|van chuyen|ship|lien he|hotline|thanh toan)\b/u', $normalized)) {
            $topic = 'general';
            if (str_contains($normalized, 'doi tra') || str_contains($normalized, 'bao hanh')) $topic = 'return_policy';
            if (str_contains($normalized, 'van chuyen') || str_contains($normalized, 'ship')) $topic = 'shipping';
            if (str_contains($normalized, 'thanh toan')) $topic = 'payment';
            if (str_contains($normalized, 'lien he') || str_contains($normalized, 'hotline')) $topic = 'contact';
            
            return $this->buildResult('get_store_info', ['topic' => $topic]);
        }

        // 4. Mã giảm giá
        if (preg_match('/\b(ma giam gia|voucher|khuyen mai|coupon)\b/u', $normalized)) {
            return $this->buildResult('get_available_coupons');
        }

        // 5. Trạng thái đơn hàng
        if (preg_match('/\b(don hang|order cua toi|xem don|trang thai don|don cua toi)\b/u', $normalized)) {
            return $this->buildResult('get_order_status');
        }

        // 6. Địa chỉ
        if (preg_match('/\b(dia chi|giao toi)\b/u', $normalized)) {
            return $this->buildResult('get_my_addresses');
        }

        // 7. Sản phẩm bán chạy
        if (preg_match('/\b(san pham ban chay|ban chay|hot trend|dang hot)\b/u', $normalized)) {
            return $this->buildResult('search_products', ['is_best_seller' => true]);
        }

        // 8. Sản phẩm giảm giá
        if (preg_match('/\b(giam gia|dang giam|hang sale|san pham sale|san pham giam|khuyen mai san pham|sale)\b/u', $normalized) 
            && !str_contains($normalized, 'flash sale')) {
            return $this->buildResult('search_products', ['on_sale' => true]);
        }

        // 9. Lệnh Mua / Auto Order
        if ($this->looksLikeBuyIntent($normalized)) {
            $args = $this->extractAutoOrderArgs($message, $normalized);
            return $this->buildResult('auto_order', $args);
        }

        // 10. Chọn theo ngữ cảnh (Context)
        if (preg_match('/\b(cai nay|san pham nay|cai do|cai dau tien|cai thu 1|cai so 1|cai thu nhat|cai thu 2|cai so 2|cai thu hai|cai thu 3|cai so 3|cai thu ba|cai cuoi cung|cai cuoi)\b/u', $normalized, $matches)) {
            return $this->buildResult('context_selection', [
                'reference' => $matches[1],
                'action' => 'select'
            ]);
        }
        
        // 11. Hỏi chi tiết sản phẩm
        if ($this->looksLikeProductDetailQuery($normalized, $message)) {
            return $this->buildResult('get_product_detail', [
                'product_name' => $this->extractProductNameForDetail($message)
            ]);
        }

        // 12. Tìm kiếm sản phẩm
        if ($this->looksLikeProductSearch($normalized)) {
            return $this->buildResult('search_products', $this->extractProductSearchArgs($message, $normalized));
        }

        return $this->buildResult('unknown');
    }

    private function buildResult(string $intent, array $entities = []): array
    {
        return [
            'intent' => $intent,
            'entities' => $entities
        ];
    }

    public function normalizeVietnameseText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $map = [
            'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
            'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
            'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
            'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
            'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
            'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d',
        ];
        return strtr($text, $map);
    }

    private function looksLikeBuyIntent(string $normalized): bool
    {
        $buyVerbs = [
            'dat cho toi', 'mua cho toi', 'order cho toi',
            'dat giup toi', 'mua giup toi',
            'dat ngay', 'mua ngay', 'chot don',
            'order ngay', 'toi muon mua', 'toi can mua',
            'toi muon dat', 'cho toi dat', 'cho toi mua',
            'order 1', 'dat 1', 'mua 1',
            'order 2', 'dat 2', 'mua 2',
        ];
        foreach ($buyVerbs as $verb) {
            if (str_contains($normalized, $verb)) {
                return true;
            }
        }

        if (preg_match('/\bhay\s+(dat|mua|order)\b/u', $normalized)) return true;
        if (preg_match('/\b(dat|mua|order)\b/u', $normalized) && str_contains($normalized, 'giup toi')) return true;
        if (preg_match('/\b(dat|mua|order)\s+\d+\s+\w/u', $normalized)) return true;
        
        // Thêm rule cho các câu như "thêm cái này vào giỏ", "lấy cái này"
        if (preg_match('/\b(lay cai|mua cai|dat cai|them vao gio)\b/u', $normalized)) return true;

        return false;
    }

    private function extractAutoOrderArgs(string $message, string $normalized): array
    {
        $buyPatterns = [
            '/hãy\s+đặt\s+cho\s+tôi/iu', '/hãy\s+mua\s+cho\s+tôi/iu', '/đặt\s+cho\s+tôi/iu',
            '/mua\s+cho\s+tôi/iu', '/order\s+cho\s+tôi/iu', '/đặt\s+giúp\s+tôi/iu',
            '/mua\s+giúp\s+tôi/iu', '/cho\s+tôi\s+đặt/iu', '/cho\s+tôi\s+mua/iu',
            '/tôi\s+muốn\s+mua/iu', '/tôi\s+muốn\s+đặt/iu', '/tôi\s+cần\s+mua/iu',
            '/đặt\s+ngay/iu', '/mua\s+ngay/iu', '/order\s+ngay/iu', '/chốt\s+đơn/iu',
            '/đặt\s+luôn/iu', '/mua\s+luôn/iu', '/hãy/iu', '/thêm\s+vào\s+giỏ/iu',
            '/lấy\s+cái/iu', '/mua\s+cái/iu', '/đặt\s+cái/iu'
        ];

        $cleanedOriginal = $message;
        foreach ($buyPatterns as $pattern) {
            $cleanedOriginal = preg_replace($pattern, '', $cleanedOriginal);
        }

        $qty = 1;
        if (preg_match('/^\s*(\d+)\s*(cái|chiếc|đôi|cây|cặp|bộ)?\s*/iu', trim($cleanedOriginal), $qtyMatch)) {
            $qty = max(1, min((int) $qtyMatch[1], 20));
            $cleanedOriginal = substr($cleanedOriginal, strlen($qtyMatch[0]));
        }

        // Thử match color và size
        $color = null;
        $size = null;
        
        foreach ($this->colorMap as $plain => $c) {
            if (preg_match('/\b' . $plain . '\b/u', $normalized)) {
                $color = $c;
                break;
            }
        }
        
        if (preg_match('/\b(xs|s|m|l|xl|xxl|2xl|3xl|4xl|[3-4][0-9])\b/u', $normalized, $matches)) {
            $size = strtoupper($matches[1]);
        }
        
        // Remove color và size words khỏi keyword
        $keyword = trim($cleanedOriginal);
        
        // Remove color phrases
        $keyword = preg_replace('/\b(màu|mau)\s+(đen|den|trắng|trang|đỏ|do|vàng|vang|xanh\s+dương|xanh\s+duong|xanh\s+navy|xanh\s+lá|xanh\s+la|xám|xam|hồng|hong|tím|tim)\b/iu', ' ', $keyword);
        // Remove standalone colors
        $keyword = preg_replace('/\b(đen|den|trắng|trang|đỏ|do|vàng|vang|xanh\s+dương|xanh\s+duong|xanh\s+navy|xanh\s+lá|xanh\s+la|xám|xam|hồng|hong|tím|tim)\b/iu', ' ', $keyword);
        // Remove size phrases
        $keyword = preg_replace('/\b(size)\s+(xs|s|m|l|xl|xxl|2xl|3xl|4xl|[3-4][0-9])\b/iu', ' ', $keyword);
        // Remove trailing words like "cái này", "nhé", "đi"
        $keyword = preg_replace('/\b(cái\s+này|sản\s+phẩm\s+này|cái\s+đó|chiếc\s+này|đôi\s+này|bộ\s+này|này|nhé|nha|đi|với|ạ)\b/iu', ' ', $keyword);
        
        // Clean up spaces
        $keyword = trim(preg_replace('/\s+/', ' ', $keyword));
        
        return [
            'keyword' => $keyword,
            'quantity' => $qty,
            'color' => $color,
            'size' => $size
        ];
    }

    private function looksLikeProductDetailQuery(string $normalized, string $original): bool
    {
        $detailKeywords = [
            'co mau gi', 'mau gi', 'nhung mau', 'co nhung mau',
            'co mau voi', 'co mau', 'co size nao', 'size nao', 'nhung size', 'size gi',
            'co size', 'con hang', 'con mau', 'con size',
            'gia bao nhieu', 'gia la bao', 'gia may',
            'thong tin', 'chi tiet', 'mo ta', 'chat lieu',
        ];

        foreach ($detailKeywords as $kw) {
            if (str_contains($normalized, $kw)) {
                return mb_strlen(trim($original)) > 10;
            }
        }
        return false;
    }

    private function extractProductNameForDetail(string $message): string
    {
        $stopPatterns = [
            '/\s+có\s+(những\s+|các\s+|mấy\s+)?(màu|size|giá|hàng|thông tin|chi tiết|mô tả|chất liệu).*$/iu',
            '/\s+(những\s+|các\s+|mấy\s+)?(màu|size)\s*(gì|nào|gi|nao).*$/iu',
            '/\s+còn\s+(những\s+|các\s+|mấy\s+)?(hàng|màu|size).*$/iu',
            '/\s+giá\s+bao\s+nhiêu.*$/iu',
            '/\s+thông\s+tin.*$/iu',
            '/\s+chi\s+tiết.*$/iu',
            '/\s+vậy\s*$/iu',
            '/\s+không\s*\?*$/iu',
            '/\?+$/u',
        ];

        $name = $message;
        foreach ($stopPatterns as $pattern) {
            $name = preg_replace($pattern, '', $name);
        }

        return mb_substr(strip_tags(trim($name)), 0, 100);
    }

    private function looksLikeProductSearch(string $normalized): bool
    {
        if (preg_match('/\b(kinh|mat kinh|ao|quan|giay|dep|tui|balo|mu|non|vot|bong|gang|phu kien|san pham|hang|mua|show|tim|kiem)\b/u', $normalized)) {
            return true;
        }
        return preg_match('/\b([0-9]+(?:[\.,][0-9]+)?\s*(k|nghin|trieu|m))\b/u', $normalized) === 1;
    }

    public function extractProductSearchArgs(string $message, string $normalized): array
    {
        $args = [];
        $categories = [];
        foreach ($this->categoryMap as $keyword => $category) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/u', $normalized)) {
                $categories[] = $category;
            }
        }
        $categories = array_values(array_unique($categories));
        if (count($categories) === 1) {
            $args['category'] = $categories[0];
        } elseif (count($categories) > 1) {
            $args['categories'] = $categories;
        }

        if (preg_match('/(?:tu|khoang|tam)?\s*([0-9]+(?:[\.,][0-9]+)?)\s*(k|nghin|trieu|m)?\s*(?:den|toi|-)\s*([0-9]+(?:[\.,][0-9]+)?)\s*(k|nghin|trieu|m)?/u', $normalized, $matches)) {
            $args['min_price'] = $this->parseVietnamesePrice($matches[1], $matches[2] ?? '');
            $args['max_price'] = $this->parseVietnamesePrice($matches[3], $matches[4] ?: ($matches[2] ?? ''));
        } elseif (preg_match('/(?:duoi|nho hon|toi da|<=|<)\s*([0-9]+(?:[\.,][0-9]+)?)\s*(k|nghin|trieu|m)?/u', $normalized, $matches)) {
            $args['max_price'] = $this->parseVietnamesePrice($matches[1], $matches[2] ?? '');
        } elseif (preg_match('/(?:tren|lon hon|tu)\s*([0-9]+(?:[\.,][0-9]+)?)\s*(k|nghin|trieu|m)?/u', $normalized, $matches)) {
            $args['min_price'] = $this->parseVietnamesePrice($matches[1], $matches[2] ?? '');
        } elseif (preg_match('/(?:tam|khoang|gia)\s*([0-9]+(?:[\.,][0-9]+)?)\s*(k|nghin|trieu|m)?/u', $normalized, $matches)) {
            $price = $this->parseVietnamesePrice($matches[1], $matches[2] ?? '');
            $args['min_price'] = max(0, (int) floor($price * 0.8));
            $args['max_price'] = (int) ceil($price * 1.2);
        }

        foreach ($this->colorMap as $plain => $color) {
            if (preg_match('/\b' . $plain . '\b/u', $normalized)) {
                $args['color'] = $color;
                break;
            }
        }

        if (preg_match('/\b(xs|s|m|l|xl|xxl|2xl|3xl|4xl|[3-4][0-9])\b/u', $normalized, $matches)) {
            $args['size'] = strtoupper($matches[1]);
        }

        if (isset($args['min_price'], $args['max_price']) && $args['min_price'] > $args['max_price']) {
            [$args['min_price'], $args['max_price']] = [$args['max_price'], $args['min_price']];
        }
        
        if (empty($args['keyword'])) {
            $cleaned = mb_strtolower(trim($message));
            
            // Remove prices
            $cleaned = preg_replace('/(?:tu|khoang|tam|duoi|nho hon|toi da|<=|<|tren|lon hon|>=|>|gia)?\s*([0-9]+(?:[\.,][0-9]+)?)\s*(k|nghin|trieu|m)\b/u', '', $cleaned);
            
            // Remove colors
            $colorsToRemove = ['đen', 'black', 'trắng', 'white', 'xanh', 'blue', 'green', 'đỏ', 'red', 'hồng', 'pink', 'xám', 'gray', 'grey', 'nâu', 'brown', 'be', 'beige', 'vàng', 'yellow', 'màu'];
            foreach ($colorsToRemove as $c) {
                $cleaned = preg_replace('/\b' . preg_quote($c, '/') . '\b/u', '', $cleaned);
            }
            
            // Remove sizes & common noise words
            $cleaned = preg_replace('/\b(size|gia|giá|duoi|dưới|trên|tren|từ|tu|đến|den|khoảng|khoang|tầm|tam)\b/u', '', $cleaned);
            $cleaned = preg_replace('/\b(xs|s|m|l|xl|xxl|2xl|3xl|4xl|[3-4][0-9])\b/u', '', $cleaned);
            
            $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

            if (!empty($cleaned)) {
                $args['keyword'] = mb_substr(strip_tags($cleaned), 0, 80);
            } elseif (count($categories) === 1) {
                $args['keyword'] = $categories[0];
            } else {
                $args['keyword'] = mb_substr(strip_tags(trim($message)), 0, 80);
            }
        }

        return $args;
    }

    private function parseVietnamesePrice(string $number, string $unit): int
    {
        $value = (float) str_replace(',', '.', $number);
        $unit = trim($unit);

        if (in_array($unit, ['k', 'nghin'], true)) return (int) round($value * 1000);
        if (in_array($unit, ['trieu', 'm'], true)) return (int) round($value * 1000000);
        if ($value < 1000) $value = $value * 1000;

        return (int) min(max(round($value), 0), 100000000);
    }
}
