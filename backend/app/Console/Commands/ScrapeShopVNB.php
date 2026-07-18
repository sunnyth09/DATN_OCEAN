<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ScrapeShopVNB extends Command
{
    protected $signature = 'scrape:shopvnb {--limit=30 : Số sản phẩm tối đa mỗi danh mục} {--output=storage/app/shopvnb_products.json : File xuất JSON}';
    protected $description = 'Quét sản phẩm từ shopvnb.com (tên, ảnh, giá) và xuất ra JSON';

    /**
     * Danh mục cần quét từ shopvnb.com
     * key = slug trên shopvnb, value = tên category tương ứng trong hệ thống
     */
    private array $categories = [
        'vot-cau-long.html'        => 'Cầu lông',
        'giay-cau-long.html'       => 'Cầu lông',
        'ao-cau-long.html'         => 'Đồ thể thao',
        'vot-pickleball.html'      => 'Pickleball',
        'giay-pickleball.html'     => 'Pickleball',
        'phu-kien-cau-long.html'   => 'Phụ kiện thể thao',
        'tui-vot-cau-long.html'    => 'Phụ kiện thể thao',
        'vot-tennis.html'          => 'Thể thao vợt & bóng',
    ];

    public function handle(): int
    {
        $limit  = (int) $this->option('limit');
        $output = $this->option('output');
        $allProducts = [];

        $this->info("🔍 Bắt đầu quét shopvnb.com...");
        $this->info("   Giới hạn: {$limit} sản phẩm/danh mục");
        $this->newLine();

        foreach ($this->categories as $slug => $localCategory) {
            $url = "https://shopvnb.com/{$slug}";
            $this->comment("📂 Đang quét: {$url} → [{$localCategory}]");

            $products = $this->scrapeCategory($url, $localCategory, $limit);
            $count = count($products);
            $this->info("   ✅ Tìm thấy {$count} sản phẩm");

            $allProducts = array_merge($allProducts, $products);

            // Delay giữa các request để không bị block
            sleep(1);
        }

        // Loại bỏ trùng lặp theo tên
        $unique = [];
        $seen = [];
        foreach ($allProducts as $p) {
            $key = mb_strtolower(trim($p['name']));
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $p;
            }
        }

        $totalPath = base_path($output);
        $dir = dirname($totalPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $totalPath,
            json_encode($unique, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $this->newLine();
        $this->info("🎉 Hoàn tất! Tổng cộng: " . count($unique) . " sản phẩm (đã loại trùng)");
        $this->info("📄 Đã lưu vào: {$totalPath}");

        return Command::SUCCESS;
    }

    private function scrapeCategory(string $url, string $localCategory, int $limit): array
    {
        $products = [];
        $page = 1;

        while (count($products) < $limit) {
            $pageUrl = $page === 1 ? $url : $this->buildPageUrl($url, $page);
            $html = $this->fetchHtml($pageUrl);

            if (!$html) {
                $this->warn("   ⚠️ Không thể tải trang: {$pageUrl}");
                break;
            }

            $parsed = $this->parseProductsFromHtml($html, $localCategory);

            if (empty($parsed)) {
                break; // Hết sản phẩm
            }

            foreach ($parsed as $p) {
                if (count($products) >= $limit) break;
                $products[] = $p;
            }

            $page++;

            // Nếu trang trả về ít hơn 20 sản phẩm, có thể đã hết
            if (count($parsed) < 20) {
                break;
            }

            usleep(500000); // 0.5s delay giữa các trang
        }

        return $products;
    }

    private function buildPageUrl(string $baseUrl, int $page): string
    {
        // shopvnb dùng format: vot-cau-long.html?page=2
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        return "{$baseUrl}{$separator}page={$page}";
    }

    private function fetchHtml(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml',
                'Accept-Language: vi-VN,vi;q=0.9,en;q=0.8',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return $response;
    }

    private function parseProductsFromHtml(string $html, string $localCategory): array
    {
        $products = [];

        // Pattern 1: Lấy tên sản phẩm từ class="product-name"
        // <h3 class="product-name"><a href="..." title="Tên SP">Tên SP</a></h3>
        preg_match_all(
            '/class="product-name">\s*<a\s+href="([^"]+)"\s+title="([^"]+)"/',
            $html,
            $nameMatches,
            PREG_SET_ORDER
        );

        // Pattern 2: Lấy giá từ class="price"
        // <span class="price">638.000 ₫</span>
        preg_match_all(
            '/class="price">([\d.,]+)\s*₫/',
            $html,
            $priceMatches,
            PREG_SET_ORDER
        );

        // Pattern 3: Lấy ảnh từ data-src trong product-thumbnail
        // Tìm tất cả ảnh sản phẩm (300x300)
        preg_match_all(
            '/data-src="(https:\/\/cdn\.shopvnb\.com\/img\/300x300\/uploads\/(?:san_pham|gallery)\/[^"]+)"/',
            $html,
            $imageMatches,
            PREG_SET_ORDER
        );

        // Ghép dữ liệu: mỗi product-name tương ứng với 1 price và 1 image
        $nameCount = count($nameMatches);

        for ($i = 0; $i < $nameCount; $i++) {
            $name = html_entity_decode(trim($nameMatches[$i][2]), ENT_QUOTES, 'UTF-8');
            $slug = trim($nameMatches[$i][1], '/');

            // Lấy giá tương ứng (nếu có)
            $price = 0;
            if (isset($priceMatches[$i][1])) {
                $price = (int) str_replace(['.', ','], '', $priceMatches[$i][1]);
            }

            // Lấy ảnh tương ứng (nếu có)
            $imageUrl = '';
            if (isset($imageMatches[$i][1])) {
                $imageUrl = $imageMatches[$i][1];
                // Đổi sang ảnh lớn hơn (600x600)
                $imageUrl = str_replace('/img/300x300/', '/img/600x600/', $imageUrl);
            }

            // Bỏ qua nếu không có tên hoặc tên quá ngắn
            if (mb_strlen($name) < 5) continue;

            $products[] = [
                'name'           => $name,
                'slug_source'    => $slug,
                'price'          => $price,
                'image_url'      => $imageUrl,
                'category_local' => $localCategory,
                'source'         => 'shopvnb.com',
            ];
        }

        return $products;
    }
}
