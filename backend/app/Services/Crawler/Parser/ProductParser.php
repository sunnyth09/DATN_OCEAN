<?php

namespace App\Services\Crawler\Parser;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;

class ProductParser
{
    /**
     * Parse the product detail page HTML.
     */
    public function parse(string $html, string $url): array
    {
        $crawler = new Crawler($html);

        // Remove unwanted elements
        $crawler->filter('script, iframe, style, comment, noscript, .popup, .ads, .tracking')->each(function (Crawler $node) {
            foreach ($node as $n) {
                if ($n->parentNode) {
                    $n->parentNode->removeChild($n);
                }
            }
        });

        $data = [
            'url' => $url,
            'name' => '',
            'slug' => '',
            'brand' => null,
            'original_price' => 0,
            'sale_price' => 0,
            'short_description' => '',
            'description' => '',
            'specifications' => '',
            'images' => [],
            'sku' => '',
            'status' => 'active',
            'is_in_stock' => true,
        ];

        try {
            // Tên sản phẩm
            $titleNode = $crawler->filter('h1');
            if ($titleNode->count()) {
                $data['name'] = trim($titleNode->text());
                $data['slug'] = Str::slug($data['name']);
            }
            
            // Brand
            $brandNode = $crawler->filter('.vendor a, .product-vendor a, .brand-name');
            if ($brandNode->count()) {
                $data['brand'] = trim($brandNode->text());
            }
            
            // Prices
            $priceNode = $crawler->filter('.product-price del, .price-box .old-price .price');
            $salePriceNode = $crawler->filter('.product-price .pro-price, .price-box .special-price .price');
            
            if ($salePriceNode->count()) {
                preg_match('/([0-9][0-9.,]*)/', $salePriceNode->first()->text(), $matches);
                $data['sale_price'] = !empty($matches) ? (float) preg_replace('/[^0-9]/', '', $matches[1]) : 0;
            }
            if ($priceNode->count()) {
                preg_match('/([0-9][0-9.,]*)/', $priceNode->first()->text(), $matches);
                $data['original_price'] = !empty($matches) ? (float) preg_replace('/[^0-9]/', '', $matches[1]) : 0;
            } else {
                $data['original_price'] = $data['sale_price'];
            }
            
            // Description & Specs
            $descNode = $crawler->filter('#content-desc, .product-description');
            if ($descNode->count()) {
                $data['description'] = $descNode->html();
                $data['short_description'] = Str::limit(strip_tags($data['description']), 200);
            }
            
            $specNode = $crawler->filter('#content-spec, .product-specifications');
            if ($specNode->count()) {
                $data['specifications'] = $specNode->html();
            }
            
            // Images
            $crawler->filter('img')->each(function (Crawler $node) use (&$data) {
                $src = $node->attr('data-image') ?? $node->attr('data-src') ?? $node->attr('src');
                
                if ($src && strpos($src, 'data:image') === false && strpos($src, 'captcha') === false && strpos($src, 'themes_new') === false) {
                    if (strpos($src, 'http') !== 0) {
                        $src = 'https://cdn.shopvnb.com' . (strpos($src, '/') === 0 ? '' : '/') . $src;
                    }
                    if (!in_array($src, $data['images'])) {
                        $data['images'][] = $src;
                    }
                }
            });
            
        } catch (\Exception $e) {
            // Ignore parse errors, returning what we have
        }

        return $data;
    }
}
