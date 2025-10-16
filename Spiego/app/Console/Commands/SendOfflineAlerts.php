<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AdminSetting;
use App\Models\Device;
use App\Mail\OfflineAlert;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendOfflineAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-offline-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send alerts for devices that have gone offline';

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

        if (!AdminSetting::get('offline_alert_enabled', false)) {
            $this->info('Offline alerts are disabled in settings.');
            return 0;
        }

        $adminEmail = AdminSetting::get('admin_email');
        if (empty($adminEmail)) {
            $this->error('Admin email not configured.');
            return 1;
        }

        $thresholdMinutes = AdminSetting::get('offline_threshold_minutes', 60);
        $thresholdTime = Carbon::now()->subMinutes($thresholdMinutes);

        // Find devices that haven't been seen since threshold
        $offlineDevices = Device::where('last_seen', '<', $thresholdTime)->get();

        foreach ($offlineDevices as $device) {
            $offlineMinutes = Carbon::now()->diffInMinutes($device->last_seen);
            
            $this->info("Sending offline alert for {$device->hostname} (offline: {$offlineMinutes} min)");
            Mail::to($adminEmail)->send(new OfflineAlert($device, $offlineMinutes));
        }

        $this->info("Offline alerts check completed. Found " . count($offlineDevices) . " offline device(s).");
        return 0;
    }
}
