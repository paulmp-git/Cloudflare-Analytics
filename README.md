# Cloudflare Analytics for WordPress

![Version](https://img.shields.io/badge/version-1.2-blue.svg)
![License](https://img.shields.io/badge/license-GPLv2-green.svg)

A secure and optimized WordPress plugin that displays Cloudflare traffic analytics directly in your WordPress dashboard.

## Features

*   **Dashboard Integration**: View Unique Visitors, Total Requests, and Pageviews directly in your WP Admin Dashboard.
*   **Time Ranges**: Filter data by Last 24 Hours, Last 7 Days, or Last 30 Days.
*   **High Performance**: Uses asynchronous "Stale-While-Revalidate" caching so your dashboard loads instantly, even if the Cloudflare API is slow.
*   **Security First**: 
    *   API Tokens are encrypted using **AES-256-CBC**.
    *   Strict Content Security Policy (CSP) and security headers applied to plugin pages.
    *   Input sanitization and validation.

## Requirements

*   WordPress 5.6 or higher
*   PHP 7.4 or higher
*   A Cloudflare account with an active zone

## Installation

1.  Download the plugin zip file.
2.  Log in to your WordPress admin area.
3.  Go to **Plugins > Add New**.
4.  Click **Upload Plugin** and select the zip file.
5.  Activate the plugin.

## Configuration

1.  Navigate to **Settings > Cloudflare Analytics**.
2.  Enter your **Cloudflare API Token**.
    *   *Note: Create a token with `Zone:Read` and `Analytics:Read` permissions.*
3.  Enter your **Zone ID** (found on your Cloudflare domain overview page).
4.  Enter your **Cloudflare Account Email**.
5.  Save Settings.

## Security

This plugin takes security seriously:
*   **Encryption**: Sensitive data (API Tokens) is encrypted using OpenSSL (AES-256-CBC) with WordPress salts before storage.
*   **Headers**: Admin pages related to the plugin are protected with `X-Frame-Options`, `X-XSS-Protection`, and strict `Content-Security-Policy`.
*   **Validation**: strict type checking and sanitization for all inputs.

## Development

### Directory Structure

```
cloudflare-analytics/
├── assets/             # CSS and JS files
├── includes/           # PHP Classes
│   ├── Admin/          # Admin UI (Dashboard, Settings)
│   ├── Core/           # Core Logic (Plugin, Installer)
│   └── Services/       # Services (API, Cache, Security)
├── cloudflare-analytics.php  # Main plugin file
└── README.md
```

## License

This project is licensed under the GNU General Public License v2 or later.
