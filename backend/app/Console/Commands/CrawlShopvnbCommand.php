<?php

namespace App\Console\Commands;

use App\Services\Crawler\ShopVnbCrawlerService;
use Illuminate\Console\Command;

class CrawlShopvnbCommand extends Command
{
    protected $signature = 'crawl:shopvnb {--limit=100} {--sport=*} {--images} {--resume}';

    protected $description = 'Crawl products from ShopVNB and seed the database';

    private $crawlerService;

    public function __construct(ShopVnbCrawlerService $crawlerService)
    {
        parent::__construct();
        $this->crawlerService = $crawlerService;
    }

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $sports = $this->option('sport'); // Array of selected sports
        $downloadImages = $this->option('images');
        $resume = $this->option('resume'); // To implement: save state and resume

        $this->info('Starting ShopVNB Crawler...');
        $this->info("Limit: $limit");
        $this->info('Sports: '.(empty($sports) ? 'All (Badminton, Pickleball, Tennis)' : implode(', ', $sports)));
        $this->info('Download Images: '.($downloadImages ? 'Yes' : 'No'));

        $startTime = microtime(true);

        $stats = $this->crawlerService->crawl($this, $limit, $sports, $downloadImages);

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);

        $this->info('=========================================');
        $this->info('CRAWL COMPLETED');
        $this->info('=========================================');
        $this->info('Total Products: '.$stats['total']);
        $this->info('Skipped (Duplicates): '.$stats['skipped']);
        $this->info('Total Images: '.$stats['images']);
        $this->info('Total Variants: '.$stats['variants']);
        $this->info("Execution Time: {$executionTime}s");

        $this->info("\n--- By Sport ---");
        foreach ($stats['sports'] as $sport => $count) {
            $this->line("$sport: $count");
        }

        $this->info("\n--- By Category ---");
        foreach ($stats['categories'] as $cat => $count) {
            $this->line("$cat: $count");
        }

        $this->info("\n--- By Brand ---");
        foreach ($stats['brands'] as $brand => $count) {
            $this->line("$brand: $count");
        }

        return 0;
    }
}
