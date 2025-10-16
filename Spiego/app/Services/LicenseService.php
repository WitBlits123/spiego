<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class LicenseService
{
    /**
     * Check if the application has a valid license or is within trial period
     */
    public static function isValid(): bool
    {
        // Check if a valid license key is set
        $licenseKey = env('LICENSE_KEY');
        if (!empty($licenseKey) && self::validateLicenseKey($licenseKey)) {
            return true;
        }

        // Check if still in trial period
        return self::isInTrialPeriod();
    }

    /**
     * Validate a license key format and checksum
     */
    public static function validateLicenseKey(string $key): bool
    {
        // License key format: SPIEGO-XXXXX-XXXXX-XXXXX-XXXXX
        // Must be 30 characters (6 + 5 + 5 + 5 + 5 + 4 hyphens = 30)
        if (strlen($key) !== 30) {
            return false;
        }

        // Must start with SPIEGO-
        if (!str_starts_with($key, 'SPIEGO-')) {
            return false;
        }

        // Split into parts
        $parts = explode('-', $key);
        if (count($parts) !== 5) {
            return false;
        }

        // Extract checksum (last 2 characters of last part)
        $lastPart = $parts[4];
        $providedChecksum = substr($lastPart, -2);
        $data = substr($lastPart, 0, 3);
        $dataString = $parts[1] . $parts[2] . $parts[3] . $data;

        // Calculate expected checksum
        $expectedChecksum = self::calculateChecksum($dataString);

        return $providedChecksum === $expectedChecksum;
    }

    /**
     * Generate a new license key
     */
    public static function generateLicenseKey(): string
    {
        // Generate three random 5-character segments
        $part1 = self::generateRandomSegment(5);
        $part2 = self::generateRandomSegment(5);
        $part3 = self::generateRandomSegment(5);

        // Generate random 3-character data
        $data = self::generateRandomSegment(3);

        // Calculate checksum
        $checksum = self::calculateChecksum($part1 . $part2 . $part3 . $data);

        // Combine into license key
        return "SPIEGO-{$part1}-{$part2}-{$part3}-{$data}{$checksum}";
    }

    /**
     * Generate a random alphanumeric segment
     */
    private static function generateRandomSegment(int $length): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Exclude ambiguous: 0, O, 1, I
        $segment = '';
        
        for ($i = 0; $i < $length; $i++) {
            $segment .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $segment;
    }

    /**
     * Calculate a 2-character checksum
     */
    private static function calculateChecksum(string $data): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Same as segment characters
        
        // Use a fixed secret phrase for checksum validation
        // This ensures license keys work across different installations
        $secret = 'spiegosoftwearismonitoring';
        $hash = md5($data . $secret);
        
        // Convert hash to our character set
        $num = hexdec(substr($hash, 0, 8)); // Use first 8 hex chars
        $char1 = $characters[$num % strlen($characters)];
        $char2 = $characters[($num >> 5) % strlen($characters)];
        
        return $char1 . $char2;
    }

    /**
     * Check if the application is still in trial period
     */
    public static function isInTrialPeriod(): bool
    {
        $trialStart = self::getTrialStartTime();
        $trialMinutes = (int) env('TRIAL_MINUTES', 10);
        
        $expiresAt = $trialStart->copy()->addMinutes($trialMinutes);
        
        return Carbon::now()->lessThan($expiresAt);
    }

    /**
     * Get remaining trial time in minutes
     */
    public static function getRemainingTrialMinutes(): int
    {
        if (!self::isInTrialPeriod()) {
            return 0;
        }

        $trialStart = self::getTrialStartTime();
        $trialMinutes = (int) env('TRIAL_MINUTES', 10);
        $expiresAt = $trialStart->copy()->addMinutes($trialMinutes);
        
        return (int) Carbon::now()->diffInMinutes($expiresAt, false);
    }

    /**
     * Get the trial start time (when the app was first accessed)
     */
    private static function getTrialStartTime(): Carbon
    {
        return Cache::rememberForever('trial_start_time', function () {
            return Carbon::now();
        });
    }

    /**
     * Reset the trial period (for testing)
     */
    public static function resetTrial(): void
    {
        Cache::forget('trial_start_time');
    }

    /**
     * Get license status information
     */
    public static function getStatus(): array
    {
        $licenseKey = env('LICENSE_KEY');
        
        if (!empty($licenseKey) && self::validateLicenseKey($licenseKey)) {
            return [
                'valid' => true,
                'type' => 'licensed',
                'message' => 'Valid license',
            ];
        }

        if (self::isInTrialPeriod()) {
            $remaining = self::getRemainingTrialMinutes();
            return [
                'valid' => true,
                'type' => 'trial',
                'message' => "Trial period: {$remaining} minutes remaining",
                'remaining_minutes' => $remaining,
            ];
        }

        return [
            'valid' => false,
            'type' => 'expired',
            'message' => 'Trial expired - License required',
        ];
    }
}
