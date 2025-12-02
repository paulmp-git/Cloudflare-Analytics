=== Cloudflare Analytics ===
Contributors: paulpichugin
Tags: cloudflare, analytics, statistics, traffic, security
Requires at least: 5.6
Tested up to: 6.4
Stable tag: 1.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A secure and optimized plugin to display Cloudflare traffic analytics in the WordPress dashboard.

== Description ==

Cloudflare Analytics brings your website's traffic data directly into your WordPress dashboard. View unique visitors, total requests, and pageviews without leaving your site.

This plugin is built with performance and security in mind:
*   **Secure:** Uses AES-256-CBC encryption for storing API tokens and implements strict security headers for admin pages.
*   **Fast:** Implements Stale-While-Revalidate caching strategy, ensuring your dashboard never hangs while waiting for data.
*   **Lightweight:** Modular architecture with minimal footprint.

**Key Features**
*   Dashboard Widget with key metrics (Visitors, Requests, Pageviews).
*   Adjustable time ranges (24 Hours, 7 Days, 30 Days).
*   Secure handling of Cloudflare API credentials.
*   Data caching to minimize API requests.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/cloudflare-analytics` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Navigate to **Settings > Cloudflare Analytics** to configure your API credentials.
4.  Enter your Cloudflare API Token, Zone ID, and Account Email.
5.  Go to your Dashboard to view the analytics widget.

== Frequently Asked Questions ==

= Where do I find my Cloudflare API Token? =
You can generate an API Token in your Cloudflare Profile settings. Ensure the token has "Zone.Analytics:Read" permissions.

= Where do I find my Zone ID? =
Your Zone ID is located on the Overview page of your domain in the Cloudflare dashboard, usually in the right sidebar.

= Is my API Token secure? =
Yes. We encrypt your API token using AES-256-CBC encryption before storing it in the database. It is never stored in plain text.

== Screenshots ==

1.  **Dashboard Widget** - View your traffic stats at a glance.
2.  **Settings Page** - Easily configure your Cloudflare credentials.

== Changelog ==

= 1.2 =
*   Security: Implemented AES-256-CBC encryption for API tokens.
*   Security: Scoped security headers to admin area.
*   Performance: Added Stale-While-Revalidate caching.
*   Refactor: Moved to modular architecture.

= 1.0 =
*   Initial release.
