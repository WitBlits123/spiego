<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LicenseService;

class LicenseController extends Controller
{
    /**
     * Show the trial expired page
     */
    public function expired()
    {
        $status = LicenseService::getStatus();
        return view('license.expired', compact('status'));
    }

    /**
     * Show license management page
     */
    public function index()
    {
        $status = LicenseService::getStatus();
        return view('license.index', compact('status'));
    }

    /**
     * Activate a license key
     */
    public function activate(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string',
        ]);

        $licenseKey = strtoupper(trim($request->license_key));

        if (LicenseService::validateLicenseKey($licenseKey)) {
            // Update .env file
            $this->updateEnvFile('LICENSE_KEY', $licenseKey);

            return redirect()->route('license.index')
                ->with('success', 'License activated successfully!');
        }

        return back()->withErrors(['license_key' => 'Invalid license key format.']);
    }

    /**
     * Generate a new license key (admin only - for testing)
     */
    public function generate()
    {
        $licenseKey = LicenseService::generateLicenseKey();
        return response()->json([
            'license_key' => $licenseKey,
            'valid' => LicenseService::validateLicenseKey($licenseKey),
        ]);
    }

    /**
     * Update .env file with new value
     */
    private function updateEnvFile($key, $value)
    {
        $path = base_path('.env');

        if (file_exists($path)) {
            $content = file_get_contents($path);

            // Check if key exists
            if (preg_match("/^{$key}=.*/m", $content)) {
                // Update existing key
                $content = preg_replace(
                    "/^{$key}=.*/m",
                    "{$key}={$value}",
                    $content
                );
            } else {
                // Add new key
                $content .= "\n{$key}={$value}\n";
            }

            file_put_contents($path, $content);
        }
    }
}
