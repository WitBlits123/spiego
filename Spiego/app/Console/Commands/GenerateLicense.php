<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LicenseService;

class GenerateLicense extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'license:generate {--count=1 : Number of license keys to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Spiego license keys';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->option('count');

        $this->info("Generating {$count} license key(s)...\n");

        for ($i = 0; $i < $count; $i++) {
            $licenseKey = LicenseService::generateLicenseKey();
            $valid = LicenseService::validateLicenseKey($licenseKey);

            $this->line("License Key #{" . ($i + 1) . "}: <fg=green>{$licenseKey}</>");
            
            if (!$valid) {
                $this->error("  ⚠ Validation failed!");
            }
        }

        $this->info("\n✓ License key(s) generated successfully!");
        $this->comment("\nThese keys can be activated in the Spiego dashboard or via the .env file.");

        return Command::SUCCESS;
    }
}
