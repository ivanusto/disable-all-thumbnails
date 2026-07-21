=== Disable All Thumbnails ===
Contributors: ivanlin
Tags: images, thumbnails, media, optimization, performance
Requires at least: 5.3
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 1.1.1
License: Apache-2.0
License URI: http://www.apache.org/licenses/LICENSE-2.0
Donate link: https://yblog.org

Prevent the generation of specific thumbnail formats to save disk space and improve performance.

== Description ==

"Disable All Thumbnails" is a simple and powerful WordPress plugin that allows you to:

* Selectively disable any registered WordPress image size formats.
* Safely delete existing thumbnail files in paginated batches to prevent server timeouts.
* Support cleanup of WebP and AVIF thumbnail formats.
* Save server storage space.
* Speed up image upload times.
* Reduce server CPU load during uploads.

Key Features:

1. Freely select which thumbnail formats to disable.
2. Supports all default WordPress image sizes (thumbnail, medium, large, etc.).
3. Supports custom image sizes registered by other plugins or themes.
4. Provides a paginated AJAX batch deletion engine with a native progress bar UI.
5. Complies with WordPress best practices, enqueuing JavaScript files cleanly.

This plugin is one of the origin projects of Omni Webmaster & SEO Suite, an all-in-one webmaster toolkit by the same author that consolidates and optimizes these standalone plugins: https://github.com/ivanusto/omni-webmaster-seo-suite

== Installation ==

1. Upload and activate the plugin in the WordPress admin panel.
2. Go to [Settings] > [Disable Thumbnails] page.
3. Check the thumbnail sizes you want to disable.
4. Click [Save Changes].

== Frequently Asked Questions ==

= Will disabling thumbnails affect my site's appearance? =

It might, if your theme relies on a specific disabled size to show images. It is recommended to only disable sizes that you are sure your theme does not use.

= Can I restore thumbnails after deleting them? =

No, deleting thumbnails is a permanent operation. However, you can deactivate the plugin and use a plugin like "Regenerate Thumbnails" to rebuild them.

= Will this plugin improve my site performance? =

Yes! It improves performance by:
* Reducing processing time when uploading images.
* Reducing server storage usage.
* Reducing backup file sizes.

== Changelog ==

= 1.1.1 =
* Fix: Removed persistent updates to core media options (thumbnail_size_w/h). Disabling built-in sizes is now handled at runtime via the intermediate_image_sizes_advanced filter, so WordPress media settings are no longer altered after the feature is disabled.

= 1.1.0 =
* New: Paginated AJAX batch deletion engine (avoids timeouts on large libraries)
* New: Support for WebP and AVIF thumbnail format cleanup
* New: Native progress bar UI on the settings page
* Optimize: Replaced register_uninstall_hook with a standard uninstall.php
* Optimize: Extracted inline scripts to enqueued JavaScript with localized variables
* Optimize: Complete code and translation refactoring (En/Zh-TW)

= 1.0.0 =
* Initial release
