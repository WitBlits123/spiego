# Spiego License Key Generator

This is a standalone tool for generating valid Spiego license keys.

## 🔐 Security Notice

**IMPORTANT:** This file should be kept secure and **NOT** distributed with the Spiego application. Only administrators should have access to this generator.

## Usage

### Command Line (Recommended)

Generate a single license key:
```bash
php generate_licenses.php
```

Generate multiple license keys:
```bash
php generate_licenses.php 10
```

This will:
- Display the generated keys in the terminal
- For 10+ keys, automatically save to a dated text file

### Web Interface

You can also access this via a web browser by placing it in your web server directory and navigating to it:

```
http://localhost/generate_licenses.php
```

This provides a simple web interface to generate keys.

## Output Format

License keys follow this format:
```
SPIEGO-XXXXX-XXXXX-XXXXX-XXXXX
```

Where:
- Each X is an alphanumeric character (excluding ambiguous ones: 0, O, 1, I)
- The last 2 characters are a checksum for validation
- Total length: 30 characters

## How Customers Use License Keys

1. Open the Spiego dashboard
2. Click the "License" button
3. Enter the license key in the activation form
4. Click "Activate License"

The application will validate the key and grant full access.

## Trial Period

Without a license key, Spiego runs in **trial mode** for 10 minutes per launch. After the trial expires, users must enter a valid license key to continue.

## Configuration

The license keys use a **fixed secret phrase** for checksum validation: `spiegosoftwearismonitoring`

This means:
- ✅ Keys work on **any Spiego installation**
- ✅ No need to sync with APP_KEY
- ✅ Generator always produces compatible keys
- ✅ One generator works for all deployments

The secret phrase is embedded in both the generator and the Spiego application code.

## Distribution Recommendations

1. **Keep this file separate** from your Spiego installation
2. **Store securely** on an admin-only machine
3. **Generate keys as needed** for customers
4. **Track issued licenses** (consider maintaining a spreadsheet)
5. **Never include in deployments** or version control

## License Validation

All generated keys are automatically validated before display. You'll see:
- ✓ VALID - Key is valid and ready to use
- ✗ INVALID - Key failed validation (shouldn't happen)

## Examples

Generate 1 key:
```bash
$ php generate_licenses.php 1

╔════════════════════════════════════════════════════════╗
║         Spiego License Key Generator v1.0              ║
╚════════════════════════════════════════════════════════╝

Generating 1 license key(s)...

─────────────────────────────────────────────────────────

  SPIEGO-7TXC8-4K37T-ZM227-EM2QZ  ✓ VALID

─────────────────────────────────────────────────────────

✓ Generated 1 license key(s) successfully!
```

Generate 50 keys (saved to file):
```bash
$ php generate_licenses.php 50

License keys saved to: spiego_licenses_2025-10-14_153045.txt
```

## Support

For questions about license generation or issues with keys, contact the development team.
