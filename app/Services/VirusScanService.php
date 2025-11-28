<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Log;

/**
 * Virus Scanning Service
 * 
 * Supports ClamAV via clamd socket or clamscan binary
 */
class VirusScanService
{
    /**
     * Socket path for clamd (daemon mode - faster)
     */
    protected ?string $socketPath;
    
    /**
     * Binary path for clamscan (fallback - slower)
     */
    protected ?string $binaryPath;
    
    /**
     * Whether scanning is enabled
     */
    protected bool $enabled;

    public function __construct()
    {
        $this->enabled = config('bbs.files.virus_scan_enabled', false);
        $this->socketPath = config('bbs.files.clamav_socket', '/var/run/clamav/clamd.ctl');
        $this->binaryPath = config('bbs.files.clamscan_path', '/usr/bin/clamscan');
    }

    /**
     * Check if virus scanning is available
     */
    public function isAvailable(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // Check socket
        if ($this->socketPath && file_exists($this->socketPath)) {
            return true;
        }

        // Check binary
        if ($this->binaryPath && is_executable($this->binaryPath)) {
            return true;
        }

        return false;
    }

    /**
     * Scan a file for viruses
     * 
     * @param string $filePath Full path to file
     * @return array ['clean' => bool, 'result' => string|null]
     */
    public function scan(string $filePath): array
    {
        if (!$this->enabled) {
            return ['clean' => true, 'result' => 'Scanning disabled'];
        }

        if (!file_exists($filePath)) {
            return ['clean' => false, 'result' => 'File not found'];
        }

        // Try socket first (faster)
        if ($this->socketPath && file_exists($this->socketPath)) {
            return $this->scanViaSocket($filePath);
        }

        // Fallback to binary
        if ($this->binaryPath && is_executable($this->binaryPath)) {
            return $this->scanViaBinary($filePath);
        }

        Log::warning('VirusScan: No scanning method available');
        return ['clean' => true, 'result' => 'Scanner not available'];
    }

    /**
     * Scan via clamd socket (faster for batch scanning)
     */
    protected function scanViaSocket(string $filePath): array
    {
        try {
            $socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
            if (!$socket) {
                throw new \Exception('Could not create socket');
            }

            if (!@socket_connect($socket, $this->socketPath)) {
                throw new \Exception('Could not connect to clamd socket');
            }

            // Send SCAN command
            $command = "SCAN {$filePath}\n";
            socket_write($socket, $command, strlen($command));

            // Read response
            $response = '';
            while ($chunk = socket_read($socket, 4096)) {
                $response .= $chunk;
            }
            socket_close($socket);

            // Parse response
            // Format: /path/to/file: OK or /path/to/file: VirusName FOUND
            $response = trim($response);
            
            if (str_contains($response, ' OK')) {
                return ['clean' => true, 'result' => 'OK'];
            }

            if (str_contains($response, ' FOUND')) {
                // Extract virus name
                preg_match('/: (.+) FOUND/', $response, $matches);
                $virusName = $matches[1] ?? 'Unknown threat';
                Log::warning("VirusScan: Threat detected in {$filePath}: {$virusName}");
                return ['clean' => false, 'result' => $virusName];
            }

            return ['clean' => false, 'result' => 'Unknown response: ' . $response];

        } catch (\Exception $e) {
            Log::error('VirusScan socket error: ' . $e->getMessage());
            // Fallback to binary
            return $this->scanViaBinary($filePath);
        }
    }

    /**
     * Scan via clamscan binary (slower but more reliable)
     */
    protected function scanViaBinary(string $filePath): array
    {
        if (!$this->binaryPath || !is_executable($this->binaryPath)) {
            return ['clean' => true, 'result' => 'Scanner binary not available'];
        }

        // Escape the file path for shell
        $escapedPath = escapeshellarg($filePath);
        
        // Run clamscan with no recursion, quiet mode
        $command = "{$this->binaryPath} --no-summary --infected {$escapedPath} 2>&1";
        
        exec($command, $output, $returnCode);
        
        // Return codes: 0 = clean, 1 = virus found, 2 = error
        if ($returnCode === 0) {
            return ['clean' => true, 'result' => 'OK'];
        }

        if ($returnCode === 1) {
            // Virus found - parse output for virus name
            $virusName = 'Threat detected';
            foreach ($output as $line) {
                if (str_contains($line, 'FOUND')) {
                    preg_match('/: (.+) FOUND/', $line, $matches);
                    $virusName = $matches[1] ?? $virusName;
                    break;
                }
            }
            Log::warning("VirusScan: Threat detected in {$filePath}: {$virusName}");
            return ['clean' => false, 'result' => $virusName];
        }

        // Error occurred
        $errorMessage = implode(' ', $output);
        Log::error("VirusScan error (code {$returnCode}): {$errorMessage}");
        return ['clean' => true, 'result' => 'Scan error: ' . $errorMessage];
    }

    /**
     * Scan and update a File model
     */
    public function scanFile(File $file): bool
    {
        $storagePath = storage_path('app/' . $file->storage_path);
        
        if (!file_exists($storagePath)) {
            Log::error("VirusScan: File not found at {$storagePath}");
            $file->markVirusScanned(false, 'File not found');
            return false;
        }

        $result = $this->scan($storagePath);
        
        $file->markVirusScanned($result['clean'], $result['result']);
        
        return $result['clean'];
    }

    /**
     * Scan all pending files
     */
    public function scanPendingFiles(): array
    {
        $files = File::where('virus_scanned', false)
            ->where('status', File::STATUS_PENDING)
            ->limit(50)
            ->get();

        $results = [
            'scanned' => 0,
            'clean' => 0,
            'infected' => 0,
            'errors' => 0,
        ];

        foreach ($files as $file) {
            $results['scanned']++;
            
            try {
                if ($this->scanFile($file)) {
                    $results['clean']++;
                } else {
                    $results['infected']++;
                }
            } catch (\Exception $e) {
                $results['errors']++;
                Log::error("VirusScan error for file {$file->id}: " . $e->getMessage());
            }
        }

        return $results;
    }
}
