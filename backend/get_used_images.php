<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

$usedImages = [];

// Helper to add paths, ensuring they don't have leading slashes
function addPath(&$usedImages, $path)
{
    if (! $path) {
        return;
    }

    // Normalize path to remove 'storage/' prefix if present
    $path = str_replace('storage/', '', $path);
    $path = ltrim($path, '/');
    $usedImages[] = $path;
}

// 1. Categories
$categories = DB::table('categories')->pluck('image');
foreach ($categories as $img) {
    addPath($usedImages, $img);
}

// 2. Products
$products = DB::table('products')->pluck('thumbnail_url');
foreach ($products as $img) {
    addPath($usedImages, $img);
}

// 3. Product Images
$productImages = DB::table('product_images')->pluck('image_url');
foreach ($productImages as $img) {
    addPath($usedImages, $img);
}

// 4. Product Variants
$productVariants = DB::table('product_variants')->pluck('image_url');
foreach ($productVariants as $img) {
    addPath($usedImages, $img);
}

// 5. Users
$users = DB::table('users')->pluck('avatar_url');
foreach ($users as $img) {
    if ($img && ! str_starts_with($img, 'http')) {
        addPath($usedImages, $img);
    }
}

// 6. Admins
if (Schema::hasColumn('admins', 'avatar')) {
    $admins = DB::table('admins')->pluck('avatar');
    foreach ($admins as $img) {
        if ($img && ! str_starts_with($img, 'http')) {
            addPath($usedImages, $img);
        }
    }
}

// 7. Posts
if (Schema::hasTable('posts')) {
    $postsThumbnail = DB::table('posts')->pluck('thumbnail_url');
    foreach ($postsThumbnail as $img) {
        addPath($usedImages, $img);
    }

    $postsBanner = DB::table('posts')->pluck('banner_url');
    foreach ($postsBanner as $img) {
        addPath($usedImages, $img);
    }
}

// 8. Attendances
if (Schema::hasTable('attendances') && Schema::hasColumn('attendances', 'check_out_image_path')) {
    $attendances = DB::table('attendances')->pluck('check_out_image_path');
    foreach ($attendances as $img) {
        addPath($usedImages, $img);
    }
}

// 9. Product Comments (JSON)
if (Schema::hasTable('product_comments') && Schema::hasColumn('product_comments', 'images')) {
    $comments = DB::table('product_comments')->pluck('images');
    foreach ($comments as $imagesJson) {
        if ($imagesJson) {
            $images = json_decode($imagesJson, true);
            if (is_array($images)) {
                foreach ($images as $img) {
                    addPath($usedImages, $img);
                }
            }
        }
    }
}

// 10. Return Requests (JSON)
if (Schema::hasTable('return_requests') && Schema::hasColumn('return_requests', 'images')) {
    $returns = DB::table('return_requests')->pluck('images');
    foreach ($returns as $imagesJson) {
        if ($imagesJson) {
            $images = json_decode($imagesJson, true);
            if (is_array($images)) {
                foreach ($images as $img) {
                    addPath($usedImages, $img);
                }
            }
        }
    }
}

// 11. Face Encodings
if (Schema::hasTable('face_encodings') && Schema::hasColumn('face_encodings', 'image_path')) {
    $faceEncodings = DB::table('face_encodings')->pluck('image_path');
    foreach ($faceEncodings as $img) {
        addPath($usedImages, $img);
    }
}

$usedImages = array_unique(array_filter($usedImages));

// Scan all files in storage/app/public
$allFiles = Storage::disk('public')->allFiles();

$unusedFiles = [];
foreach ($allFiles as $file) {
    // Skip .gitignore
    if (basename($file) === '.gitignore') {
        continue;
    }

    // Check if the file is in the usedImages array
    if (! in_array($file, $usedImages)) {
        $unusedFiles[] = $file;
    }
}

$result = [
    'used_count' => count($usedImages),
    'all_count' => count($allFiles),
    'unused_count' => count($unusedFiles),
    'unused_files' => $unusedFiles,
];

file_put_contents(__DIR__.'/unused_images.json', json_encode($result, JSON_PRETTY_PRINT));
echo 'Scan complete. Unused count: '.count($unusedFiles)."\n";
