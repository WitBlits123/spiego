#!/usr/bin/env php
<?php
/**
 * Spiego License Key Generator
 * 
 * Standalone tool to generate valid Spiego license keys
 * Can be used by administrators to create license keys for customers
 * 
 * Usage: php generate_licenses.php [count]
 * Example: php generate_licenses.php 10
 */

class SpiegoLicenseGenerator
{
    // Fixed secret phrase for license validation
    // This is used instead of APP_KEY so licenses work across all installations
    private const SECRET_PHRASE = 'spiegosoftwearismonitoring';
    
    /**
     * Generate a new license key
     */
    public static function generate(): string
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
     * Validate a license key
     */
    public static function validate(string $key): bool
    {
        // License key format: SPIEGO-XXXXX-XXXXX-XXXXX-XXXXX
        // Must be 30 characters
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
        $dataString = $parts[1] . $parts[2] . $parts[3];
        $lastPart = $parts[4];
        $providedChecksum = substr($lastPart, -2);
        $data = substr($lastPart, 0, 3);

        // Calculate expected checksum
        $expectedChecksum = self::calculateChecksum($dataString . $data);

        return $providedChecksum === $expectedChecksum;
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
        
        // Use fixed secret phrase for checksum
        $hash = md5($data . self::SECRET_PHRASE);
        
        // Convert hash to our character set
        $num = hexdec(substr($hash, 0, 8)); // Use first 8 hex chars
        $char1 = $characters[$num % strlen($characters)];
        $char2 = $characters[($num >> 5) % strlen($characters)];
        
        return $char1 . $char2;
    }

    /**
     * Format license key for display
     */
    public static function formatForDisplay(string $key, bool $valid): string
    {
        $status = $valid ? '✓ VALID' : '✗ INVALID';
        $color = $valid ? "\033[0;32m" : "\033[0;31m";
        $reset = "\033[0m";
        
        return sprintf("  %s%s%s  %s", $color, $key, $reset, $status);
    }
}

// CLI Interface
if (php_sapi_name() === 'cli') {
    echo "\n";
    echo "╔════════════════════════════════════════════════════════╗\n";
    echo "║         Spiego License Key Generator v1.0              ║\n";
    echo "╚════════════════════════════════════════════════════════╝\n";
    echo "\n";

    // Get count from arguments
    $count = isset($argv[1]) ? (int)$argv[1] : 1;
    
    if ($count < 1 || $count > 100) {
        echo "Error: Count must be between 1 and 100\n";
        exit(1);
    }

    echo "Generating {$count} license key(s)...\n\n";
    echo "─────────────────────────────────────────────────────────\n\n";

    $licenses = [];
    
    for ($i = 0; $i < $count; $i++) {
        $key = SpiegoLicenseGenerator::generate();
        $valid = SpiegoLicenseGenerator::validate($key);
        
        echo SpiegoLicenseGenerator::formatForDisplay($key, $valid) . "\n";
        
        if ($valid) {
            $licenses[] = $key;
        }
    }

    echo "\n─────────────────────────────────────────────────────────\n";
    echo "\n✓ Generated {$count} license key(s) successfully!\n";
    
    if ($count <= 10) {
        echo "\nCopy and paste these keys to activate Spiego:\n\n";
        foreach ($licenses as $license) {
            echo "  {$license}\n";
        }
    }
    
    // Optionally save to file
    if ($count > 10) {
        $filename = 'spiego_licenses_' . date('Y-m-d_His') . '.txt';
        file_put_contents($filename, implode("\n", $licenses));
        echo "\nLicense keys saved to: {$filename}\n";
    }
    
    echo "\n";
    echo "Instructions:\n";
    echo "  1. Copy a license key\n";
    echo "  2. Open Spiego dashboard\n";
    echo "  3. Click 'License' button\n";
    echo "  4. Paste the key and click 'Activate'\n";
    echo "\n";
    
} else {
    // Web interface (if accessed via browser)
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Spiego License Generator</title>
        <style>
            body {
                font-family: 'Courier New', monospace;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 50px;
                margin: 0;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                background: rgba(255, 255, 255, 0.1);
                padding: 40px;
                border-radius: 20px;
                backdrop-filter: blur(10px);
            }
            h1 { text-align: center; margin-bottom: 30px; }
            .license {
                background: rgba(0, 0, 0, 0.3);
                padding: 15px;
                margin: 10px 0;
                border-radius: 10px;
                font-size: 1.2em;
                letter-spacing: 2px;
            }
            button {
                background: white;
                color: #667eea;
                border: none;
                padding: 15px 30px;
                font-size: 1.1em;
                border-radius: 10px;
                cursor: pointer;
                width: 100%;
                margin-top: 20px;
            }
            button:hover { transform: scale(1.05); }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔑 Spiego License Generator</h1>
            <?php
            if (isset($_GET['generate'])) {
                $count = isset($_GET['count']) ? min(10, max(1, (int)$_GET['count'])) : 1;
                echo "<p>Generated {$count} license key(s):</p>";
                
                for ($i = 0; $i < $count; $i++) {
                    $key = SpiegoLicenseGenerator::generate();
                    $valid = SpiegoLicenseGenerator::validate($key);
                    $status = $valid ? '✓' : '✗';
                    echo "<div class='license'>{$status} {$key}</div>";
                }
                
                echo "<button onclick='window.print()'>Print Licenses</button>";
                echo "<button onclick='location.href=\"?\"'>Generate More</button>";
            } else {
                ?>
                <form method="get">
                    <p>How many license keys to generate?</p>
                    <input type="number" name="count" value="5" min="1" max="10" 
                           style="width: 100%; padding: 15px; font-size: 1.1em; border-radius: 10px; border: none; margin-bottom: 10px;">
                    <button type="submit" name="generate" value="1">Generate Licenses</button>
                </form>
                <?php
            }
            ?>
        </div>
    </body>
    </html>
    <?php
}
