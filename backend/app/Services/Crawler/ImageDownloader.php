<?php

namespace App\Services\Crawler;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ImageDownloader
{
    /**
     * Download images and return local paths.
     */
    public function downloadImages(array $imageUrls): array
    {
        $downloadedPaths = [];
        
        foreach ($imageUrls as $url) {
            try {
                if (empty($url)) continue;
                
                // Add https if missing
                if (strpos($url, '//') === 0) {
                    $url = 'https:' . $url;
                }

                if (!filter_var($url, FILTER_VALIDATE_URL)) continue;

                $response = Http::timeout(10)->retry(3, 1000)->get($url);
                if ($response->successful()) {
                    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
                    if (empty($ext)) $ext = 'jpg';
                    
                    $filename = Str::uuid() . '.' . $ext;
                    $path = 'products/' . $filename;
                    
                    Storage::disk('public')->put($path, $response->body());
                    $downloadedPaths[] = '/storage/' . $path;
                }
            } catch (\Exception $e) {
                Log::warning("Crawler ImageDownloader failed for url: $url. Error: " . $e->getMessage());
            }
        }
        
        return $downloadedPaths;
    }
}
