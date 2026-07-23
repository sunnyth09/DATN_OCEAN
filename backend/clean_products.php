<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$count = Product::doesntHave('images')->count();
echo "Found $count products without images. Deleting...\n";
Product::doesntHave('images')->forceDelete();
echo "Done.\n";
