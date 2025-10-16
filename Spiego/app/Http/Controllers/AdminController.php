<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AdminSetting;

class AdminController extends Controller
{
    /**
     * Show the admin settings page
     */
    public function index()
    {
        $settings = [
            'admin_email' => AdminSetting::get('admin_email', ''),
            'notifications_enabled' => AdminSetting::get('notifications_enabled', false),
            'idle_alert_enabled' => AdminSetting::get('idle_alert_enabled', false),
            'idle_threshold_minutes' => AdminSetting::get('idle_threshold_minutes', 30),
            'summary_report_enabled' => AdminSetting::get('summary_report_enabled', false),
            'summary_report_frequency' => AdminSetting::get('summary_report_frequency', 'daily'),
            'offline_alert_enabled' => AdminSetting::get('offline_alert_enabled', false),
            'offline_threshold_minutes' => AdminSetting::get('offline_threshold_minutes', 60),
        ];

        return view('admin.settings', compact('settings'));
    }

    /**
     * Update admin settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'admin_email' => 'nullable|email',
            'notifications_enabled' => 'nullable|boolean',
            'idle_alert_enabled' => 'nullable|boolean',
            'idle_threshold_minutes' => 'nullable|integer|min:1',
            'summary_report_enabled' => 'nullable|boolean',
            'summary_report_frequency' => 'nullable|in:daily,weekly,monthly',
            'offline_alert_enabled' => 'nullable|boolean',
            'offline_threshold_minutes' => 'nullable|integer|min:1',
        ]);

        // Save all settings
        AdminSetting::set('admin_email', $request->input('admin_email', ''));
        AdminSetting::set('notifications_enabled', $request->has('notifications_enabled'));
        AdminSetting::set('idle_alert_enabled', $request->has('idle_alert_enabled'));
        AdminSetting::set('idle_threshold_minutes', $request->input('idle_threshold_minutes', 30), 'integer');
        AdminSetting::set('summary_report_enabled', $request->has('summary_report_enabled'));
        AdminSetting::set('summary_report_frequency', $request->input('summary_report_frequency', 'daily'));
        AdminSetting::set('offline_alert_enabled', $request->has('offline_alert_enabled'));
        AdminSetting::set('offline_threshold_minutes', $request->input('offline_threshold_minutes', 60), 'integer');

        return redirect()->route('admin.settings')->with('success', 'Settings saved successfully!');
    }
}
