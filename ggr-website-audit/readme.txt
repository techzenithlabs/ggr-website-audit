=== SEO Audit & Analyzer by GGR ===
Contributors: hitman2019
Tags: seo audit, seo analyzer, website audit, seo tool, on-page seo
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 2.4.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://techzenithlabs.com/donate/

Audit and fix SEO issues directly inside WordPress — even before publishing. Fast, lightweight, and no external APIs.

== Description ==

SEO Audit & Analyzer by GGR is a lightweight WordPress SEO plugin that analyzes your content directly inside the editor — even before publishing.

Unlike traditional SEO tools that rely only on live URLs, GGR allows you to audit draft and private posts, helping you fix SEO issues before your content goes live.

It evaluates key on-page SEO signals like headings, metadata, links, structured data, and content quality to provide actionable insights.

With an admin bar SEO score, instant rescan option, and content-based analysis engine, you can optimize your pages faster without relying on external tools.

No external APIs. No tracking. Fully local and performance-friendly.

== What the plugin checks ==

* Title tag presence
* Meta description detection
* Heading hierarchy (H1 – H4)
* Content length indicators
* Internal and external links
* Image ALT attributes
* Canonical tag presence
* Noindex detection
* Structured data (JSON-LD)
* Basic technical SEO signals

== Features ==

* Simple and user-friendly audit dashboard
* One-click page analysis with instant results
* Visual SEO health score with breakdown
* Clear issue categories: Good, Needs Improvement, Critical
* Actionable recommendations with explanations
* Admin bar SEO score indicator on edit screens
* Per-page score tracking (saved for each post/page)
* Automatic setup screen after plugin activation
* Quick scan workflow for faster onboarding
* AJAX-powered fast audit processing
* Lightweight and performance-friendly
* No external API calls or tracking
* Audit draft and private posts before publishing
* No dependency on live URLs (content-based analysis)
* Works with all public post types (including custom post types)
* Smart permalink warnings for SEO best practices
* Admin bar SEO score with instant rescan option

== Installation ==

1. Upload the `ggr-website-audit` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. After activation, you will be redirected to the setup screen.
4. Enter a page URL and run your first audit.
5. View SEO score and detailed analysis instantly.

== Frequently Asked Questions ==

= Does this plugin guarantee SEO rankings? =

No. The plugin provides analysis of on-page SEO signals. Rankings depend on many factors like content quality, backlinks, and competition.

= Does the plugin send data externally? =

No. All analysis runs locally inside your WordPress installation. No external API calls or tracking are used.

= Can I audit any website? =

The plugin is intended primarily for analyzing pages within your own WordPress site.

= Does the plugin modify themes or styles? =

No. It does not modify theme files or override global styles.

= Does this plugin store cookies? =

A temporary cookie may be used to prevent repeated scans in a short time. No personal data is stored.

== Screenshots ==

1. SEO audit dashboard with one-click scan interface
2. Quick setup screen to get started in seconds
3. Real-time SEO analysis in progress
4. Detailed SEO report with categorized issues and actionable insights
5. Quick actions to fix SEO issues and preview your page instantly
6. Instant SEO score directly in WordPress editor
7. Smart post editor integration with one-click SEO audit from the top admin bar — shows post type and status (Draft, Published, Private) for better context
8. Real-time SEO audit for individual posts directly inside the admin panel — no dependency on permalinks (supports drafts, private posts, and numeric URLs)


== Changelog ==


= 2.4.6 =
* NEW: Support for draft and private post SEO audit (pre-publish optimization)
* NEW: Content-based audit engine (removed dependency on live URLs)
* NEW: Support for all public post types including custom post types
* NEW: Admin bar enhancements with post type badge and status indicator
* NEW: Smart permalink warning for SEO-friendly URLs
* IMPROVEMENT: Faster and more reliable audit processing
* FIX: Removed “page does not exist” error for draft/preview URLs
* DEV: Refactored audit engine to support post_id based analysis

= 2.4.5 =
* FIX: Resolved issue where admin bar scan always used homepage instead of current page URL
* FIX: Corrected nonce handling for secure scan requests
* FIX: Fixed URL encoding/decoding issue causing incorrect scan_url in dashboard
* FIX: Resolved admin bar click behavior (SEO Score no longer clickable, Rescan works correctly)
* IMPROVEMENT: Better URL detection for post editor and frontend pages
* DEV: Refactored scan URL handling logic for consistency and reliability

= 2.4.4 =
* Added admin bar SEO score indicator on post and page edit screens
* Implemented per-page SEO score storage and tracking
* Introduced automatic setup screen after plugin activation
* Improved first-time user onboarding with quick scan flow
* Enhanced UI/UX for better clarity and usability
* Improved timezone handling and UTC compatibility
* Optimized audit processing performance
* Fixed minor bugs and improved stability
* Improved: Refined "Fix SEO" and "Preview" action buttons with updated labels and modern UI styling

= 2.4.3 =
* Improved plugin naming and SEO positioning
* Enhanced readme content for better discoverability
* Added visual SEO score system
* Improved UI with clearer issue categorization
* Added better explanations for SEO issues

= 2.4.2 =
* Improved WordPress coding standards compliance
* Fixed prefix naming consistency
* Security and escaping improvements

= 2.4.1 =
* Added AJAX-based audit execution
* Improved permission checks
* Internal stability improvements

== Upgrade Notice ==

= 2.4.6 =
Major update: Audit draft and private posts before publishing, no dependency on live URLs, and enhanced admin bar SEO insights.

= 2.4.4 =
Adds admin bar SEO score, per-page tracking, setup flow, and major UI improvements.