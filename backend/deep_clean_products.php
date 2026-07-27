<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;

$count = Product::withTrashed()->count();
echo "Total products (including trashed): $count\n";

$trashedCount = Product::onlyTrashed()->count();
echo "Trashed products: $trashedCount\n";

// Force delete all trashed
Product::onlyTrashed()->forceDelete();

// Also force delete any products that were inserted today (from crawler) to start fresh
Product::where('created_at', '>=', now()->subDay())->forceDelete();

echo "Cleanup complete.\n";
