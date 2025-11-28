<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Services\VirusScanService;
use Illuminate\Console\Command;

class ScanPendingFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bbs:scan-files {--limit=100 : Maximum files to scan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan pending/unscanned files for viruses using ClamAV';

    /**
     * Execute the console command.
     */
    public function handle(VirusScanService $virusScanService): int
    {
        if (!$virusScanService->isAvailable()) {
            $this->error('ClamAV is not available. Check configuration and clamd service.');
            return self::FAILURE;
        }
        
        $limit = (int) $this->option('limit');
        
        $this->info("Scanning up to {$limit} pending files...");
        
        // Get files that haven't been virus scanned
        $files = File::whereNull('virus_scanned_at')
            ->where('status', '!=', File::STATUS_QUARANTINE)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
            
        if ($files->isEmpty()) {
            $this->info('No files pending scan.');
            return self::SUCCESS;
        }
        
        $bar = $this->output->createProgressBar($files->count());
        $bar->start();
        
        $scanned = 0;
        $infected = 0;
        $errors = 0;
        
        foreach ($files as $file) {
            $storagePath = storage_path('app/' . $file->storage_path);
            
            if (!file_exists($storagePath)) {
                $this->warn("\nFile not found: {$file->filename}");
                $errors++;
                $bar->advance();
                continue;
            }
            
            try {
                $result = $virusScanService->scanFile($storagePath, $file);
                $scanned++;
                
                if ($result['virus_detected'] ?? false) {
                    $infected++;
                    $this->warn("\nVirus found: {$file->filename} - {$result['virus_name']}");
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("\nScan error: {$file->filename} - {$e->getMessage()}");
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("Scan complete:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Scanned', $scanned],
                ['Infected', $infected],
                ['Errors', $errors],
            ]
        );
        
        if ($infected > 0) {
            $this->warn("⚠️  {$infected} files were quarantined due to virus detection.");
        }
        
        return self::SUCCESS;
    }
}
