<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AdminSetting;
use App\Models\Device;
use App\Models\Event;
use App\Mail\IdleAlert;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendIdleAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-idle-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send alerts for devices with prolonged mouse idle time';

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

        if (!AdminSetting::get('idle_alert_enabled', false)) {
            $this->info('Idle alerts are disabled in settings.');
            return 0;
        }

        $adminEmail = AdminSetting::get('admin_email');
        if (empty($adminEmail)) {
            $this->error('Admin email not configured.');
            return 1;
        }

        $thresholdMinutes = AdminSetting::get('idle_threshold_minutes', 30);
        $thresholdTime = Carbon::now()->subMinutes($thresholdMinutes);

        // Get all devices
        $devices = Device::all();

        foreach ($devices as $device) {
            // Check for recent mouse_active events (if no recent activity, check mouse_idle)
            $lastActive = Event::where('hostname', $device->hostname)
                ->where('event_type', 'mouse_active')
                ->where('timestamp', '>', $thresholdTime)
                ->orderBy('timestamp', 'desc')
                ->first();

            if (!$lastActive) {
                // Check for mouse_idle event with long duration
                $idleEvent = Event::where('hostname', $device->hostname)
                    ->where('event_type', 'mouse_idle')
                    ->orderBy('timestamp', 'desc')
                    ->first();

                if ($idleEvent) {
                    $data = $idleEvent->data;
                    $idleSeconds = 0;
                    if (is_array($data) && isset($data['idle_seconds'])) {
                        $idleSeconds = $data['idle_seconds'];
                    } elseif (is_object($data) && isset($data->idle_seconds)) {
                        $idleSeconds = $data->idle_seconds;
                    }

                    $idleMinutes = $idleSeconds / 60;

                    if ($idleMinutes >= $thresholdMinutes) {
                        $this->info("Sending idle alert for {$device->hostname} (idle: {$idleMinutes} min)");
                        Mail::to($adminEmail)->send(new IdleAlert($device, round($idleMinutes)));
                    }
                }
            }
        }

        $this->info('Idle alerts check completed.');
        return 0;
    }
}
