<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AdminSetting;
use App\Models\Device;
use App\Http\Controllers\DeviceController;
use App\Mail\SummaryReport;
use Illuminate\Support\Facades\Mail;

class SendSummaryReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-summary-reports {--frequency= : daily, weekly, or monthly}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send activity summary reports via email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if notifications are enabled
        if (!AdminSetting::get('notifications_enabled', false)) {
            $this->info('Notifications are disabled in settings.');
            return 0;
        }

        if (!AdminSetting::get('summary_report_enabled', false)) {
            $this->info('Summary reports are disabled in settings.');
            return 0;
        }

        $adminEmail = AdminSetting::get('admin_email');
        if (empty($adminEmail)) {
            $this->error('Admin email not configured.');
            return 1;
        }

        // Get frequency from option or from settings
        $frequency = $this->option('frequency') ?? AdminSetting::get('summary_report_frequency', 'daily');

        $this->info("Generating {$frequency} summary reports...");

        // Get all devices
        $devices = Device::all();
        
        if ($devices->isEmpty()) {
            $this->info('No devices to report on.');
            return 0;
        }

        // Generate summaries for each device
        $summaries = [];
        $controller = new DeviceController();

        foreach ($devices as $device) {
            try {
                $response = $controller->quickSummary($device->hostname);
                if ($response instanceof \Illuminate\Http\JsonResponse) {
                    $summaries[$device->hostname] = json_decode($response->getContent(), true);
                }
            } catch (\Exception $e) {
                $this->warn("Failed to generate summary for {$device->hostname}: " . $e->getMessage());
            }
        }

        // Send the email
        Mail::to($adminEmail)->send(new SummaryReport($devices, $frequency, $summaries));

        $this->info("Summary report sent to {$adminEmail}");
        return 0;
    }
}
