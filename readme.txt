=== Disable All Thumbnails ===
Contributors: ivanlin
Tags: images, thumbnails, media, optimization, performance
Requires at least: 5.3
Tested up to: 7.1
Requires PHP: 7.0
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
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

= 1.3.0 =
* Fix: WordPress 7.1 regenerated disabled sizes anyway. Two code paths ask `wp_get_missing_image_subsizes()` what still needs generating and skip the `intermediate_image_sizes_advanced` filter entirely: the long-standing post-upload recovery pass, and 7.1's client-side media processing, where the browser reads the missing list from the REST response, generates those sizes locally and sideloads them back. The plugin now filters that list too, so a disabled size stays disabled in both paths.
* Fix: The bulk delete now keeps the companion files WordPress 7.1 stores beside the main image - `original_image` (the pre-scale upload), `source_image` (for example the HEIC a JPEG was derived from) and `animated_video` / `animated_video_poster` (what an animated GIF is converted to). 7.1 can register one physical file under several size names, so a size entry may point straight at one of these; deleting it left the attachment referencing a missing file. The size entry is still removed, the shared file is not.
* Fix: The settings screen showed no dimensions for the built-in 1536x1536 and 2048x2048 sizes. WordPress registers them with `add_image_size()` and never creates the `*_size_w` / `*_size_h` options the screen was reading. Dimensions now come from `wp_get_registered_image_subsizes()`, with the fixed dimensions core names them after as a fallback for when disabling them drops them from the registry.
* Fix: An unconstrained axis now reads "auto" instead of "0px", so Medium Large shows as `768px × auto` - it is 768px wide at whatever height preserves the aspect ratio. The multiplication sign also renders correctly instead of showing the literal text `&times;`.
* Tested against WordPress 7.1.

= 1.2.0 =
* Fix: The site icon is now protected. "Delete All Thumbnails" walked every image attachment and blanked its whole `sizes` metadata, which removed the `site_icon-32`, `site_icon-180`, `site_icon-192` and `site_icon-270` files that back the favicon, the Apple touch icon and the Windows tile. Running it once broke the favicon site-wide, and regenerating thumbnails could not bring it back because the metadata was gone. The attachment currently set as the site icon is now skipped entirely; images that were the site icon in the past are still cleaned up, since nothing references them any more.
* Fix: Deletion now removes each size individually instead of blanking the whole `sizes` array, so preserved sizes survive and attachments with nothing to delete are no longer rewritten.
* Fix: Site icon sub-sizes are no longer listed on the settings screen and can no longer be switched off, which previously broke the favicon for any site icon set afterwards. Stale entries saved by earlier versions are ignored.
* New: `disable_all_thumbnails_is_protected_size` and `disable_all_thumbnails_skip_attachment` filters for protecting additional sizes or attachments.

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
