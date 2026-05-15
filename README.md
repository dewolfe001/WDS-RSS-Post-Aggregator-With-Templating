# WDS RSS Post Aggregator #
**Contributors:**      [jtsternberg](https://github.com/jtsternberg), [JayWood](https://github.com/JayWood), [stacyk](https://github.com/stacyk), [blobaugh](https://github.com/blobaugh), [lswilson](https://github.com/lswilson), [imBigWill](https://github.com/ImBigWill), [coreymcollins](https://github.com/coreymcollins), [dewolfe001](https://github.com/dewolfe001)
**Tags:**              post import, feed import, rss import, rss aggregator
**Requires at least:** 6.0
**Tested up to:**      7.0
**Stable tag:**        0.2.9
**License:**           GPLv2 or later
**License URI:**       http://www.gnu.org/licenses/gpl-2.0.html
**Requires PHP:**       8.3
**Donate link:**        [Donate to Web321 via PayPal](https://paypal.me/web321co)

Allows you to selectively import posts to your WordPress installation from RSS Feeds and save them locally so they're never lost. If this plugin helps you, please consider [donating to Web321 via PayPal](https://paypal.me/web321co).

## Description ##

WDS RSS Post Aggregator provides site owners the ability to selectively import RSS posts to their blog using WordPress' built in post selection interface.  Once a feed is selected and a post is imported, the excerpt, title, and all the usual things you would expect are editable.  You can even categorize and tag the posts in their own taxonomies.

With RSS Post Aggregator, the following is pulled in during the import process:

* Post Title
* Original Post URL
* Post Content
* Post Thumbnail
* Retained RSS item custom fields/post meta for title, iTunes metadata, summaries/descriptions, encoded content, enclosure data, GUID data, and publication date

## Installation ##

### Manual Installation ###

1. Upload the entire `/rss-post-aggregator` directory to the `/wp-content/plugins/` directory.
2. Activate RSS Post Aggregator through the 'Plugins' menu in WordPress.

### Dev Documentation ###
Imported RSS Posts are automatically included with regular posts on the main blog/home query so the newest podcast/blog entries can appear on the initial posts screen. To disable that behavior, return `false` from the `rss_post_aggregator_include_rss_posts_on_home` filter.

Imported RSS Post permalinks now open the local WordPress entry by default so visitors can read the editable description/content page. Podcast audio enclosure URLs are saved in the RSS Item Info box as `Podcast Audio URL` and are displayed with a WordPress audio player on the local detail page when available. To restore the older behavior of sending RSS Post links directly to the source URL, return `true` from the `rss_post_aggregator_link_to_original_url` filter.

Use the Featured image panel on each imported RSS Post to upload or replace the per-entry podcast title image. Re-importing an existing item will not overwrite a manually selected Featured image.

Feed links can be configured for automatic hourly imports from the RSS Feed Links taxonomy screen. Each feed can import into RSS Posts or another public post type, and existing imported items are skipped instead of updated so manually edited content remains untouched. The RSS Posts > Settings screen documents the available template tokens and controls the post content template used for new imports.

You may also want to access the category information, which is housed in the `rss-category` taxonomy.  'Rss Feed Links' are housed in the `rss-feed-links` taxonomy as well.

## Frequently Asked Questions ##
[Open A Ticket](https://github.com/WebDevStudios/WDS-RSS-Post-Aggregator/issues)

* None Yet

## Screenshots ##

![Importing RSS Posts](https://raw.githubusercontent.com/WebDevStudios/WDS-RSS-Post-Aggregator/master/screenshot-1.jpg)
"Add New RSS Post" dialog

![RSS Feed Links](https://raw.githubusercontent.com/WebDevStudios/WDS-RSS-Post-Aggregator/master/screenshot-2.jpg)
"RSS Feed Links" page, very similar to tags/categories

![RSS Feed Categories](https://raw.githubusercontent.com/WebDevStudios/WDS-RSS-Post-Aggregator/master/screenshot-3.jpg)
"RSS Feed Categories" page

![Imported Posts](https://raw.githubusercontent.com/WebDevStudios/WDS-RSS-Post-Aggregator/master/screenshot-4.jpg)
Imported posts with imported featured image ( It's automatic!!! )

![Post Edit Screen](https://raw.githubusercontent.com/WebDevStudios/WDS-RSS-Post-Aggregator/master/screenshot-5.jpg)
Post Edit Screen - Manually set RSS feed link.


## Changelog ##

### 0.2.9 ###
* Decode HTML5/XML and double-encoded entities in imported RSS content.

### 0.2.8 ###
* Fix duplicate detection for feeds that reuse one link across multiple RSS items by using GUID/enclosure identifiers.

### 0.2.6 ###
* Added manual import controls for all automatic feeds and individual feed terms.

### 0.2.5 ###
* Add an RSS Posts settings page with import template documentation and a configurable post content template.
* Register RSS Feed Links for all importable post types so per-feed destination post type selections are honored when terms are assigned during import.

### 0.2.4 ###
* Add hourly scheduled imports for configured feeds, with per-feed destination post type settings.
* Skip previously imported feed items without updating existing posts.
* Add AJAX nonce/capability checks for manual imports.

### 0.2.3 ###
* Capture requested RSS item fields, including iTunes podcast metadata, content, enclosure attributes, GUID attributes, and publication date, as post meta during import.
* Display retained RSS item fields in the RSS Item Info metabox on imported posts.

### 0.2.2 ###
* Retain podcast audio enclosure URLs during import, expose them in the RSS Item Info metabox, and show a WordPress audio player on local detail pages.

### 0.2.1 ###
* Include imported RSS Posts in the main blog/home query so the newest imported podcast entries can appear on the initial posts screen.
* Link imported RSS Posts to local detail pages by default, while retaining a filter to opt back into source URL redirects.
* Surface Featured image guidance for per-entry podcast title images and preserve manually selected images on re-import.
* Fix the RSS Feed with Images widget title markup and prefer local imported post links/images when available.

### 0.2.0 ###
* Add PHP 8.3+ compatibility updates, including explicit class properties and safer DOM parsing.
* Update compatibility metadata for WordPress 6.x and WordPress 7.
* Add Web321 donation link.

### 0.1.1 ###
* Removed CMB2 dependancy - [Fixes #2](https://github.com/WebDevStudios/WDS-RSS-Post-Aggregator/issues/2)
* Code cleanup and docblocks

### 0.1.0 ###
* First release
