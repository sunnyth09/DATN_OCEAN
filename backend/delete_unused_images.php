<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;

$jsonFile = __DIR__.'/unused_images.json';
if (!file_exists($jsonFile)) {
    die("unused_images.json not found\n");
}

$data = json_decode(file_get_contents($jsonFile), true);
if (!$data || !isset($data['unused_files'])) {
    die("Invalid JSON data\n");
}

$unusedFiles = $data['unused_files'];
$count = 0;

foreach ($unusedFiles as $file) {
    if (Storage::disk('public')->exists($file)) {
        Storage::disk('public')->delete($file);
        $count++;
    }
}

echo "Deleted $count files.\n";

// Optional: clean up empty directories
$directories = Storage::disk('public')->allDirectories();
// Sort by length descending to delete deepest directories first
usort($directories, function($a, $b) {
    return strlen($b) - strlen($a);
});

$dirCount = 0;
foreach ($directories as $dir) {
    // Check if directory is empty
    $files = Storage::disk('public')->files($dir);
    $subDirs = Storage::disk('public')->directories($dir);
    if (count($files) === 0 && count($subDirs) === 0) {
        Storage::disk('public')->deleteDirectory($dir);
        $dirCount++;
    }
}

echo "Deleted $dirCount empty directories.\n";
