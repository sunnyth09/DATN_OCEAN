<?php

namespace App\Services\Crawler;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use App\Services\Crawler\Parser\ProductParser;
use App\Services\Crawler\Parser\VariantParser;
use App\Services\Crawler\Resolver\CategoryResolver;
use App\Services\Crawler\Resolver\BrandResolver;
use App\Services\Crawler\Importer\DatabaseImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShopVnbCrawlerService
{
    private $client;
    private $productParser;
    private $variantParser;
    private $categoryResolver;
    private $brandResolver;
    private $importer;
    private $imageDownloader;

    private $sportsConfig = [
        'badminton' => [
            'name' => 'Badminton',
            'urls' => [
                'Vợt' => 'https://shopvnb.com/vot-cau-long.html',
                'Giày' => 'https://shopvnb.com/giay-cau-long.html',
                'Áo' => 'https://shopvnb.com/ao-cau-long.html',
                'Balo' => 'https://shopvnb.com/balo-cau-long.html',
            ],
            'limit' => 34
        ],
        'pickleball' => [
            'name' => 'Pickleball',
            'urls' => [
                'Vợt' => 'https://shopvnb.com/vot-pickleball.html',
                'Giày' => 'https://shopvnb.com/giay-pickleball.html',
                'Balo' => 'https://shopvnb.com/balo-pickleball.html',
            ],
            'limit' => 33
        ],
        'tennis' => [
            'name' => 'Tennis',
            'urls' => [
                'Vợt' => 'https://shopvnb.com/vot-tennis.html',
                'Giày' => 'https://shopvnb.com/giay-tennis.html',
                'Balo' => 'https://shopvnb.com/balo-tennis.html',
            ],
            'limit' => 33
        ]
    ];

    public function __construct(
        ProductParser $productParser,
        VariantParser $variantParser,
        CategoryResolver $categoryResolver,
        BrandResolver $brandResolver,
        DatabaseImporter $importer,
        ImageDownloader $imageDownloader
    ) {
        $this->client = new Client([
            'timeout' => 15.0,
            'verify' => false,
        ]);
        $this->productParser = $productParser;
        $this->variantParser = $variantParser;
        $this->categoryResolver = $categoryResolver;
        $this->brandResolver = $brandResolver;
        $this->importer = $importer;
        $this->imageDownloader = $imageDownloader;
    }

    public function crawl(Command $command, int $totalLimit, array $selectedSports, bool $downloadImages)
    {
        $stats = [
            'total' => 0,
            'sports' => [],
            'categories' => [],
            'brands' => [],
            'images' => 0,
            'variants' => 0,
            'skipped' => 0,
        ];

        // Adjust limits if specific sports are selected
        if (!empty($selectedSports)) {
            $sportCount = count($selectedSports);
            $limitPerSport = (int) ceil($totalLimit / $sportCount);
            foreach ($this->sportsConfig as $k => &$config) {
                if (in_array($k, $selectedSports)) {
                    $config['limit'] = $limitPerSport;
                } else {
                    $config['limit'] = 0;
                }
            }
        } else {
            // Distribute $totalLimit according to ratios 34-33-33
            $this->sportsConfig['badminton']['limit'] = (int) ceil($totalLimit * 0.34);
            $this->sportsConfig['pickleball']['limit'] = (int) ceil($totalLimit * 0.33);
            $this->sportsConfig['tennis']['limit'] = $totalLimit - $this->sportsConfig['badminton']['limit'] - $this->sportsConfig['pickleball']['limit'];
        }

        $bar = $command->getOutput()->createProgressBar($totalLimit);
        $bar->start();

        foreach ($this->sportsConfig as $sportKey => $sportData) {
            if ($sportData['limit'] <= 0) continue;

            $sportName = $sportData['name'];
            $urls = $sportData['urls'];
            $limitPerCategory = (int) ceil($sportData['limit'] / count($urls));
            
            $sportStatsCount = 0;

            foreach ($urls as $catName => $catUrl) {
                if ($sportStatsCount >= $sportData['limit']) break;

                $command->info("\nFetching category: $sportName - $catName");
                // Fetch up to 40 links to have a buffer in case of duplicates/errors
                $productLinks = $this->getProductLinksFromCategory($catUrl, 40);

                foreach ($productLinks as $link) {
                    if ($sportStatsCount >= $sportData['limit']) break;
                    if ($stats['total'] >= $totalLimit) break 3;

                    // Random delay
                    usleep(rand(500000, 1500000));

                    $command->line("Processing: " . $link);
                    
                    try {
                        $html = $this->fetchHtml($link);
                        if (!$html) {
                            $stats['skipped']++;
                            continue;
                        }

                        $productData = $this->productParser->parse($html, $link);
                        if (empty($productData['name'])) {
                            $stats['skipped']++;
                            continue;
                        }

                        $variantsData = $this->variantParser->parse($html, $productData);

                        $categoryId = $this->categoryResolver->resolve($sportName, $catName);
                        $brandId = $this->brandResolver->resolve($productData['brand']);

                        $imagesData = [];
                        if ($downloadImages && !empty($productData['images'])) {
                            $imagesData = $this->imageDownloader->downloadImages($productData['images']);
                            $stats['images'] += count($imagesData);
                        } else {
                            $imagesData = $productData['images']; // store original URLs if not downloading
                        }

                        $product = $this->importer->import($productData, $variantsData, $categoryId, $brandId, $imagesData);

                        if ($product) {
                            $stats['total']++;
                            $sportStatsCount++;
                            $stats['variants'] += count($variantsData);
                            
                            $stats['sports'][$sportName] = ($stats['sports'][$sportName] ?? 0) + 1;
                            $stats['categories'][$catName] = ($stats['categories'][$catName] ?? 0) + 1;
                            if ($productData['brand']) {
                                $stats['brands'][$productData['brand']] = ($stats['brands'][$productData['brand']] ?? 0) + 1;
                            }
                            
                            $command->line("<info>✓ Saved:</info> {$productData['name']}");
                            $bar->advance();
                        } else {
                            $command->line("<comment>✓ Skipped (exists):</comment> {$productData['name']}");
                            $stats['skipped']++;
                        }

                    } catch (\Exception $e) {
                        Log::error("Crawler error on $link: " . $e->getMessage());
                        $command->line("<error>✗ Error:</error> " . $e->getMessage());
                    }
                }
            }
        }

        $bar->finish();
        $command->line("\n");

        return $stats;
    }

    private function getProductLinksFromCategory(string $url, int $limit): array
    {
        $links = [];
        $html = $this->fetchHtml($url);
        if (!$html) return $links;

        $crawler = new Crawler($html);
        
        $crawler->filter('.item_product_main .product-thumbnail > a')->each(function (Crawler $node) use (&$links, $limit) {
            if (count($links) >= $limit) return;
            $href = $node->attr('href');
            if ($href) {
                // Ensure absolute URL
                if (strpos($href, 'http') !== 0) {
                    $href = 'https://shopvnb.com/' . ltrim($href, '/');
                }
                $links[] = $href;
            }
        });

        return $links;
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ]
            ]);
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            Log::warning("Crawler failed to fetch URL: $url");
            return null;
        }
    }
}
