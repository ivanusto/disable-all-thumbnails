<?php
/**
 * Disable All Thumbnails Uninstall
 *
 * @package Disable_All_Thumbnails
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('disable_thumbnails_settings');
delete_option('disable_thumbnails_known_sizes');
