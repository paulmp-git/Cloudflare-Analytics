# Cloudflare Analytics for WordPress

![Version](https://img.shields.io/badge/version-1.3-blue.svg)
![License](https://img.shields.io/badge/license-GPLv2-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.6%2B-blue.svg)

A secure and optimized WordPress plugin that displays Cloudflare traffic analytics directly in your WordPress dashboard.

## Features

### Dashboard Analytics
*   **Unique Visitors**: Total unique visitors to your site
*   **Total Requests**: All HTTP requests handled by Cloudflare
*   **Pageviews**: Total page views
*   **Bandwidth**: Total data transferred
*   **Cache Ratio**: Percentage of requests served from cache
*   **Threats Blocked**: Number of malicious requests blocked
*   **HTTPS Traffic**: Percentage of encrypted traffic

### Time Ranges
*   Last 24 Hours
*   Last 7 Days
*   Last 30 Days

### Performance
*   **Stale-While-Revalidate Caching**: Dashboard loads instantly with cached data while fresh data is fetched in the background
*   **Smart Cache Optimization**: Automatic cache cleanup with intelligent table optimization
*   **Request Deduplication**: Prevents duplicate API calls

### Security
*   **AES-256-CBC Encryption**: API tokens encrypted with random IV for each encryption
*   **Rate Limiting**: Protects against API abuse with configurable limits
*   **Input Validation**: Zone ID and API token format validation
*   **XSS Protection**: All output properly escaped
*   **Security Headers**: CSP, X-Frame-Options, X-XSS-Protection, and more
*   **Security Logging**: Optional logging of security events

### REST API
Access your analytics data programmatically:
```
GET /wp-json/cloudflare-analytics/v1/stats?time_range=24
POST /wp-json/cloudflare-analytics/v1/test-connection
```

### Developer Features
*   **Connection Test**: Verify API credentials before saving
*   **Custom Capability**: `manage_cloudflare_analytics` for granular access control
*   **Internationalization Ready**: Full i18n support with POT file included
*   **Dark Mode Support**: Automatic styling for WordPress dark admin themes

## Requirements

*   WordPress 5.6 or higher
*   PHP 7.4 or higher (with OpenSSL extension)
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
    *   Create a token at [Cloudflare Dashboard](https://dash.cloudflare.com/profile/api-tokens)
    *   Required permissions: `Zone:Read` and `Analytics:Read`
3.  Enter your **Zone ID** (32-character hex string found on your domain overview page).
4.  Enter your **Cloudflare Account Email**.
5.  Click **Test Connection** to verify your credentials.
6.  Save Settings.

## Advanced Configuration

Add these constants to your `wp-config.php` for additional options:

```php
// Trust proxy headers (enable if behind Cloudflare proxy)
define('CLOUDFLARE_ANALYTICS_TRUST_PROXY', true);
```

## Security

This plugin implements multiple security layers:

*   **Encryption**: API tokens encrypted using AES-256-CBC with random IV and SHA-256 key derivation
*   **Validation**: Zone IDs validated as 32-character hex strings; API tokens validated for proper format
*   **Rate Limiting**: Configurable request limits per hour with automatic lockout
*   **Headers**: Strict security headers on all admin pages
*   **Logging**: Optional security event logging to protected directory
*   **Capability Checks**: Custom capability for fine-grained access control

## Directory Structure

```
cloudflare-analytics/
├── assets/
│   ├── css/
│   │   └── analytics.css
│   └── js/
│       ├── analytics.js
│       └── settings.js
├── includes/
│   ├── Admin/
│   │   ├── Dashboard.php
│   │   └── Settings.php
│   ├── Core/
│   │   ├── Installer.php
│   │   └── Plugin.php
│   └── Services/
│       ├── API.php
│       ├── Cache.php
│       └── Security.php
├── languages/
│   └── cloudflare-analytics.pot
├── cloudflare-analytics.php
├── LICENSE
└── README.md
```

## Hooks & Filters

### Actions
*   `cloudflare_analytics_cache_cleanup` - Daily cache cleanup
*   `cloudflare_refresh_cache` - Async cache refresh

### Capabilities
*   `manage_cloudflare_analytics` - View and manage analytics (granted to administrators)

## Changelog

### 1.3
*   **Security**: Implemented random IV for AES-256-CBC encryption
*   **Security**: Added Zone ID and API token format validation
*   **Security**: Improved IP detection with proxy support
*   **Feature**: Added extended metrics (bandwidth, cache ratio, threats, HTTPS %)
*   **Feature**: Added REST API endpoints
*   **Feature**: Added connection test button
*   **Feature**: Added security event logging
*   **Performance**: Optimized cache key generation
*   **Performance**: Smart table optimization scheduling
*   **UI**: Improved responsive grid layout
*   **UI**: Added dark mode support
*   **Code**: Added PHP 7.4+ type hints throughout
*   **Code**: Implemented proper singleton pattern
*   **i18n**: Added translation support with POT file

### 1.2
*   Initial public release

## License

This project is licensed under the GNU General Public License v2 or later.

## Credits

Developed by [Paul Pichugin](https://lucentdigital.net)
