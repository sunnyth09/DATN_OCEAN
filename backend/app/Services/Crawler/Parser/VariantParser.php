<?php

namespace App\Services\Crawler\Parser;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;

class VariantParser
{
    /**
     * Parse variants from product page HTML.
     */
    public function parse(string $html, array $baseData): array
    {
        $crawler = new Crawler($html);
        $variants = [];
        
        $colors = [];
        $sizes = [];

        try {
            $crawler->filter('.swatch-element.color label, .color-swatch label, select[name="color"] option, .swatch-color label')->each(function (Crawler $node) use (&$colors) {
                $color = trim($node->text());
                if (!empty($color)) $colors[] = $color;
            });
            
            $crawler->filter('.swatch-element.size label, .size-swatch label, select[name="size"] option, .swatch-size label')->each(function (Crawler $node) use (&$sizes) {
                $size = trim($node->text());
                if (!empty($size)) $sizes[] = $size;
            });
        } catch (\Exception $e) {
        }
        
        $colors = array_unique($colors);
        $sizes = array_unique($sizes);
        
        if (empty($colors) && empty($sizes)) {
            $variants[] = [
                'variant_name' => 'Mặc định',
                'sku' => $baseData['sku'] ?? '',
                'color' => null,
                'size' => null,
                'price' => $baseData['original_price'],
                'sale_price' => $baseData['sale_price'],
                'stock' => 10,
            ];
            return $variants;
        }

        if (!empty($colors) && empty($sizes)) {
            foreach ($colors as $color) {
                $variants[] = $this->makeVariantData($baseData, $color, null);
            }
        } elseif (empty($colors) && !empty($sizes)) {
            foreach ($sizes as $size) {
                $variants[] = $this->makeVariantData($baseData, null, $size);
            }
        } else {
            foreach ($colors as $color) {
                foreach ($sizes as $size) {
                    $variants[] = $this->makeVariantData($baseData, $color, $size);
                }
            }
        }

        return $variants;
    }
    
    private function makeVariantData(array $baseData, ?string $color, ?string $size): array
    {
        $nameParts = array_filter([$color, $size]);
        return [
            'variant_name' => implode(' - ', $nameParts),
            'sku' => ($baseData['sku'] ?? '') . '-' . Str::slug(implode('-', $nameParts)),
            'color' => $color,
            'size' => $size,
            'price' => $baseData['original_price'],
            'sale_price' => $baseData['sale_price'],
            'stock' => 10,
        ];
    }
}
