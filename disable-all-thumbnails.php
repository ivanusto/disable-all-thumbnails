<?php
/**
 * Plugin Name: Disable All Thumbnails
 * Plugin URI: https://yblog.org
 * Description: Prevent the generation of specific thumbnail formats to save disk space and improve performance. / 停用 WordPress 所有縮圖格式生成功能，優化網站空間使用並提升效能。
 * Version: 1.3.0
 * Author: Ivan Lin
 * Author URI: https://yblog.org
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: disable-all-thumbnails
 * Domain Path: /languages
 * Requires at least: 5.3
 * Tested up to: 7.1
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class DisableAllThumbnails {
    
    private $option_name = 'disable_thumbnails_settings';
    private $sizes_option_name = 'disable_thumbnails_known_sizes';
    private $cache_group = 'disable_thumbnails';
    private $settings_page = '';
    
    public function __construct() {
        // Basic functionality
        add_action('init', array($this, 'disable_existing_image_sizes'), 999);
        add_filter('intermediate_image_sizes_advanced', array($this, 'disable_image_sizes'), 999);

        // Two code paths ask wp_get_missing_image_subsizes() what still needs
        // generating and skip intermediate_image_sizes_advanced entirely: the
        // post-upload recovery in wp_update_image_subsizes(), and - since
        // WordPress 7.1 - client-side media processing, where the browser reads
        // missing_image_sizes from the REST create response, generates those
        // sizes in the browser and sideloads them back. Without this filter a
        // disabled size is regenerated regardless of the setting.
        add_filter('wp_get_missing_image_subsizes', array($this, 'filter_missing_image_subsizes'), 999);

        // Admin interface
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_settings_page'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('wp_ajax_delete_thumbnails', array($this, 'handle_delete_thumbnails'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            
            // Update known sizes on admin initialization
            add_action('admin_init', array($this, 'update_known_sizes'));
        }
    }

    /**
     * Enqueue admin JavaScript
     */
    public function enqueue_admin_scripts($hook_suffix) {
        if ($hook_suffix === $this->settings_page) {
            wp_enqueue_script(
                'disable-thumbnails-admin',
                plugin_dir_url(__FILE__) . 'js/admin.js',
                array('jquery'),
                '1.3.0',
                true
            );
            
            wp_localize_script('disable-thumbnails-admin', 'disableThumbnailsL10n', array(
                'ajax_url'         => admin_url('admin-ajax.php'),
                'nonce'            => wp_create_nonce('delete_thumbnails_nonce'),
                'confirm_message'  => __('Are you sure you want to delete all thumbnails? This action cannot be undone.', 'disable-all-thumbnails'),
                'deleting_message' => __('Starting deletion...', 'disable-all-thumbnails'),
                // translators: %1$d: processed count, %2$d: total images, %3$d: deleted batch count
                'progress_message' => __('Processed %1$d of %2$d images. Deleted %3$d files so far...', 'disable-all-thumbnails'),
                // translators: %d: number of deleted files
                'success_message'  => __('Successfully completed! Total %d thumbnail files deleted.', 'disable-all-thumbnails'),
                'error_message'    => __('An error occurred during deletion.', 'disable-all-thumbnails')
            ));
        }
    }

    /**
     * Update the known list of image sizes
     */
    public function update_known_sizes() {
        $current_sizes = $this->get_current_image_sizes();
        $known_sizes = get_option($this->sizes_option_name, array());
        
        // Merge current sizes and known sizes
        $updated_sizes = array_merge($known_sizes, $current_sizes);
        
        // Save back to DB
        update_option($this->sizes_option_name, $updated_sizes);
    }

    /**
     * Get all image sizes currently registered in the system
     */
    private function get_current_image_sizes() {
        global $_wp_additional_image_sizes;
        $sizes = array();
        
        // Get registered sizes
        $registered_sizes = wp_get_registered_image_subsizes();
        
        // Builtin sizes list
        $builtin_sizes = array('thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048');
        
        // WordPress registers these two with add_image_size() on plugins_loaded
        // and never creates {$size}_size_w / _size_h options for them, so
        // reading the options returns nothing and the settings screen showed no
        // dimensions at all. They are also the only built-in sizes
        // remove_image_size() can drop from the registry - which is exactly what
        // happens once the user disables them here - so keep the fixed
        // dimensions core names them after as a fallback. Without it a disabled
        // size would lose its dimensions again, or disappear from the list
        // entirely and become impossible to re-enable.
        $registry_only_sizes = array(
            '1536x1536' => 1536,
            '2048x2048' => 2048,
        );

        // Handle builtin sizes
        foreach ($builtin_sizes as $size) {
            if (isset($registered_sizes[$size])) {
                $width  = (int) $registered_sizes[$size]['width'];
                $height = (int) $registered_sizes[$size]['height'];
                $crop   = $registered_sizes[$size]['crop'];
            } elseif (isset($registry_only_sizes[$size])) {
                $width  = $registry_only_sizes[$size];
                $height = $registry_only_sizes[$size];
                $crop   = false;
            } else {
                continue;
            }

            $sizes[$size] = array(
                'width' => $width,
                'height' => $height,
                'crop' => $crop,
                'builtin' => true
            );
        }
        
        // Handle custom sizes
        foreach ($registered_sizes as $size => $data) {
            if (!isset($sizes[$size])) {
                $sizes[$size] = array(
                    'width' => $data['width'],
                    'height' => $data['height'],
                    'crop' => $data['crop'],
                    'builtin' => false
                );
            }
        }
        
        // Handle additional registered sizes
        if (is_array($_wp_additional_image_sizes)) {
            foreach ($_wp_additional_image_sizes as $size => $data) {
                if (!isset($sizes[$size])) {
                    $sizes[$size] = array(
                        'width' => $data['width'],
                        'height' => $data['height'],
                        'crop' => $data['crop'],
                        'builtin' => false
                    );
                }
            }
        }
        
        return $this->strip_protected_sizes($sizes);
    }

    /**
     * Whether an image size must never be disabled or deleted.
     *
     * WordPress generates site_icon-32, site_icon-180, site_icon-192 and
     * site_icon-270 for the site icon. They back the favicon, the Apple touch
     * icon and the Windows tile, so removing them breaks all three. They are
     * functional icons rather than content thumbnails, so this plugin leaves
     * them alone and does not offer them on the settings screen.
     *
     * @since 1.2.0
     *
     * @param string $size Image size name.
     * @return bool True when the size must be preserved.
     */
    private function is_protected_size($size) {
        $protected = (strpos($size, 'site_icon-') === 0);

        /**
         * Filters whether an image size is protected from being disabled or deleted.
         *
         * @since 1.2.0
         *
         * @param bool   $protected Whether the size must be preserved.
         * @param string $size      Image size name.
         */
        return (bool) apply_filters('disable_all_thumbnails_is_protected_size', $protected, $size);
    }

    /**
     * Remove protected sizes from a list of image sizes.
     *
     * @since 1.2.0
     *
     * @param array $sizes Image sizes keyed by size name.
     * @return array Sizes without the protected ones.
     */
    private function strip_protected_sizes($sizes) {
        foreach (array_keys($sizes) as $size) {
            if ($this->is_protected_size($size)) {
                unset($sizes[$size]);
            }
        }

        return $sizes;
    }

    /**
     * Whether an attachment must be left untouched by the bulk delete.
     *
     * Only the attachment currently set as the site icon is excluded, because
     * its sub-sizes are referenced from the document head on every page load.
     * Attachments that were the site icon in the past are deliberately not
     * excluded: nothing references them any more, so the bulk delete should be
     * able to reclaim their files.
     *
     * @since 1.2.0
     *
     * @param int $attachment_id Attachment post ID.
     * @return bool True when the attachment must be skipped.
     */
    private function should_skip_attachment($attachment_id) {
        $attachment_id = (int) $attachment_id;
        $site_icon = (int) get_option('site_icon');

        $skip = ($site_icon && $attachment_id === $site_icon);

        /**
         * Filters whether an attachment is skipped by the bulk thumbnail delete.
         *
         * @since 1.2.0
         *
         * @param bool $skip          Whether to skip the attachment.
         * @param int  $attachment_id Attachment post ID.
         */
        return (bool) apply_filters('disable_all_thumbnails_skip_attachment', $skip, $attachment_id);
    }

    /**
     * Get all known image sizes (including deactivated ones)
     */
    private function get_all_image_sizes() {
        // Get known sizes from DB
        $known_sizes = get_option($this->sizes_option_name, array());
        
        // Get current sizes
        $current_sizes = $this->get_current_image_sizes();
        
        // Merge them
        $sizes = array_merge($known_sizes, $current_sizes);

        // Installs upgrading from before 1.2.0 may still have site icon
        // sub-sizes recorded in the known-sizes option.
        $sizes = $this->strip_protected_sizes($sizes);
        
        // Add display name for each
        foreach ($sizes as $size => &$data) {
            $data['name'] = $this->get_size_name($size);
        }
        
        // Sort sizes
        return $this->sort_sizes($sizes);
    }

    /**
     * Sort image sizes logically (built-in first, then custom)
     */
    private function sort_sizes($sizes) {
        $builtin_order = array(
            'thumbnail' => 1,
            'medium' => 2,
            'medium_large' => 3,
            'large' => 4,
            '1536x1536' => 5,
            '2048x2048' => 6
        );
        
        $sorted = array();
        $custom = array();
        
        foreach ($sizes as $size => $data) {
            if (isset($builtin_order[$size])) {
                $sorted[$builtin_order[$size]] = array($size => $data);
            } else {
                $custom[$size] = $data;
            }
        }
        
        ksort($sorted);
        ksort($custom);
        
        $result = array();
        foreach ($sorted as $items) {
            $result = array_merge($result, $items);
        }
        
        return array_merge($result, $custom);
    }

    /**
     * Get descriptive name of image size
     */
    private function get_size_name($size) {
        $names = array(
            'thumbnail' => __('Thumbnail', 'disable-all-thumbnails'),
            'medium' => __('Medium', 'disable-all-thumbnails'),
            'medium_large' => __('Medium Large', 'disable-all-thumbnails'),
            'large' => __('Large', 'disable-all-thumbnails'),
            '1536x1536' => __('1536x1536 Large', 'disable-all-thumbnails'),
            '2048x2048' => __('2048x2048 Extra Large', 'disable-all-thumbnails')
        );

        if (isset($names[$size])) {
            return $names[$size];
        }

        // Custom size display name
        $size_name = str_replace(array('-', '_'), ' ', $size);
        $size_name = ucwords($size_name);
        
        /* translators: %s: custom image size name */
        return sprintf(__('Custom Size: %s', 'disable-all-thumbnails'), $size_name);
    }

    /**
     * Disable selected image sizes
     */
    public function disable_existing_image_sizes() {
        $settings = get_option($this->option_name, array());
        
        if (empty($settings)) {
            return;
        }
        
        foreach (array_keys($this->get_all_image_sizes()) as $size) {
            if (isset($settings[$size]) && $settings[$size]) {
                remove_image_size($size);
            }
        }
    }

    /**
     * Names of the sizes the user has disabled, protected sizes excluded.
     *
     * @since 1.3.0
     *
     * @return string[] Disabled size names.
     */
    private function get_disabled_sizes() {
        $settings = get_option($this->option_name, array());

        if (!is_array($settings)) {
            return array();
        }

        $disabled = array();
        foreach ($settings as $size => $is_disabled) {
            if ($is_disabled && !$this->is_protected_size($size)) {
                $disabled[] = $size;
            }
        }

        return $disabled;
    }

    /**
     * Keep disabled sizes out of the "missing sub-sizes" list, which drives
     * both the post-upload recovery regeneration and, since WordPress 7.1,
     * the sizes the browser generates and sideloads back.
     *
     * @since 1.3.0
     *
     * @param array $missing_sizes Sizes WordPress still intends to generate.
     * @return array Filtered sizes.
     */
    public function filter_missing_image_subsizes($missing_sizes) {
        if (!is_array($missing_sizes)) {
            return $missing_sizes;
        }

        foreach ($this->get_disabled_sizes() as $size) {
            unset($missing_sizes[$size]);
        }

        return $missing_sizes;
    }

    /**
     * Hook to prevent image sizes generation
     */
    public function disable_image_sizes($sizes) {
        $settings = get_option($this->option_name, array());
        
        if (empty($settings)) {
            return $sizes;
        }
        
        foreach ($settings as $size => $disabled) {
            if ($disabled && isset($sizes[$size]) && !$this->is_protected_size($size)) {
                unset($sizes[$size]);
            }
        }
        
        return $sizes;
    }

    /**
     * Add settings page menu item
     */
    public function add_settings_page() {
        $this->settings_page = add_options_page(
            esc_html__('Disable Thumbnails Settings', 'disable-all-thumbnails'),
            esc_html__('Disable Thumbnails', 'disable-all-thumbnails'),
            'manage_options',
            'disable-thumbnails-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings in DB
     */
    public function register_settings() {
        register_setting(
            'disable_thumbnails_options',
            $this->option_name,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );
    }

    /**
     * Sanitize settings array
     */
    public function sanitize_settings($input) {
        if (!is_array($input)) {
            return array();
        }
        
        $new_input = array();
        foreach (array_keys($this->get_all_image_sizes()) as $size) {
            $new_input[$size] = isset($input[$size]) ? 1 : 0;
        }
        
        return $new_input;
    }

    /**
     * Handle ajax thumbnail deletion request (Paginated Batch Processing)
     */
    public function handle_delete_thumbnails() {
        if (!check_ajax_referer('delete_thumbnails_nonce', 'nonce', false)) {
            wp_send_json_error(__('Invalid security token.', 'disable-all-thumbnails'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'disable-all-thumbnails'));
        }

        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $limit = 50;

        // Perform lightweight total count query
        $count_query = new WP_Query(array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 1,
            'post_status'    => 'inherit',
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ));
        $total_images = $count_query->found_posts;

        // Retrieve attachments for the current page
        $images_query = new WP_Query(array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => $limit,
            'paged'          => $page,
            'post_status'    => 'inherit',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));
        $images = $images_query->posts;

        if (empty($images)) {
            wp_send_json_success(array(
                'processed_count' => $total_images,
                'total_images'    => $total_images,
                'deleted_batch'   => 0,
                'next_page'       => null
            ));
        }

        $deleted_batch = 0;
        $upload_dir = wp_upload_dir();
        $base_dir = trailingslashit($upload_dir['basedir']);

        foreach ($images as $image_id) {
            if ($this->should_skip_attachment($image_id)) {
                continue;
            }

            $metadata = wp_get_attachment_metadata($image_id);
            if (empty($metadata) || empty($metadata['sizes'])) {
                continue;
            }

            if (isset($metadata['file'])) {
                $file_dir = trailingslashit(dirname($metadata['file']));
                $metadata_changed = false;

                // Files that must survive no matter which size name points at
                // them. WordPress 7.1 can register one physical file under
                // several size names, and it stores companion originals beside
                // the main file: original_image (the pre-scale upload),
                // source_image (e.g. the HEIC a JPEG was derived from), and
                // animated_video / animated_video_poster (what an animated GIF
                // is converted to). None of those are thumbnails, and deleting
                // one leaves the attachment pointing at a missing file.
                $kept_files = array();
                foreach (array('file', 'original_image', 'source_image', 'animated_video', 'animated_video_poster') as $companion_key) {
                    if (!empty($metadata[$companion_key]) && is_string($metadata[$companion_key])) {
                        $kept_files[wp_basename($metadata[$companion_key])] = true;
                    }
                }

                foreach ($metadata['sizes'] as $size => $size_info) {
                    if (empty($size_info['file'])) {
                        continue;
                    }

                    if (isset($kept_files[wp_basename($size_info['file'])])) {
                        // Drop the size entry but keep the shared file on disk.
                        unset($metadata['sizes'][$size]);
                        $metadata_changed = true;
                        continue;
                    }

                    $file_path = $base_dir . $file_dir . $size_info['file'];

                    // Delete original thumbnail file
                    if (file_exists($file_path)) {
                        wp_delete_file($file_path);
                        $deleted_batch++;
                    }

                    // Delete corresponding WebP file if exists
                    $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $file_path);
                    if (!isset($kept_files[wp_basename($webp_path)]) && file_exists($webp_path)) {
                        wp_delete_file($webp_path);
                        $deleted_batch++;
                    }

                    // Delete corresponding AVIF file if exists
                    $avif_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.avif', $file_path);
                    if (!isset($kept_files[wp_basename($avif_path)]) && file_exists($avif_path)) {
                        wp_delete_file($avif_path);
                        $deleted_batch++;
                    }

                    // Remove only this size, so protected ones survive.
                    unset($metadata['sizes'][$size]);
                    $metadata_changed = true;
                }
                
                if ($metadata_changed) {
                    wp_update_attachment_metadata($image_id, $metadata);
                }
            }
        }

        $processed_count = $page * $limit;
        $next_page = ($processed_count < $total_images) ? $page + 1 : null;

        wp_send_json_success(array(
            'processed_count' => $processed_count,
            'total_images'    => $total_images,
            'deleted_batch'   => $deleted_batch,
            'next_page'       => $next_page
        ));
    }

    /**
     * Format a size's dimensions for display.
     *
     * An axis stored as 0 is not "zero pixels", it is unconstrained - Medium
     * Large is 768px wide at whatever height preserves the aspect ratio - so
     * render it as "auto" rather than a broken-looking "0px".
     *
     * @since 1.3.0
     *
     * @param int|string $width  Width in pixels, 0 when unconstrained.
     * @param int|string $height Height in pixels, 0 when unconstrained.
     * @return string Human-readable dimensions.
     */
    private function format_size_dimensions($width, $height) {
        $width  = (int) $width;
        $height = (int) $height;

        // translators: shown in place of an image dimension that is unconstrained, e.g. "768px x auto"
        $unconstrained = __('auto', 'disable-all-thumbnails');

        return ($width > 0 ? $width . 'px' : $unconstrained)
            . ' × '
            . ($height > 0 ? $height . 'px' : $unconstrained);
    }

    /**
     * Render settings page UI
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = get_option($this->option_name, array());
        $image_sizes = $this->get_all_image_sizes();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Disable Thumbnails Settings', 'disable-all-thumbnails'); ?></h1>
            
            <form method="post" action="options.php" style="max-width: 800px; margin-top: 20px;">
                <?php settings_fields('disable_thumbnails_options'); ?>
                
                <table class="wp-list-table widefat fixed striped table-view-list" style="margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th scope="col" style="padding: 10px; font-weight: bold;"><?php esc_html_e('Image Size Format', 'disable-all-thumbnails'); ?></th>
                            <th scope="col" style="padding: 10px; font-weight: bold;"><?php esc_html_e('Dimensions', 'disable-all-thumbnails'); ?></th>
                            <th scope="col" style="padding: 10px; font-weight: bold;"><?php esc_html_e('Action', 'disable-all-thumbnails'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($image_sizes as $size => $data): ?>
                            <tr>
                                <td style="padding: 10px; font-weight: bold; vertical-align: middle;">
                                    <?php echo esc_html($data['name']); ?>
                                    <code style="font-weight: normal; margin-left: 5px;">(<?php echo esc_html($size); ?>)</code>
                                </td>
                                <td style="padding: 10px; vertical-align: middle;">
                                    <?php if (isset($data['width']) && isset($data['height'])): ?>
                                        <?php echo esc_html($this->format_size_dimensions($data['width'], $data['height'])); ?>
                                        <?php if (!empty($data['crop'])): ?>
                                            <span class="description" style="color: #c9302c;">(<?php esc_html_e('cropped', 'disable-all-thumbnails'); ?>)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="description">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 10px; vertical-align: middle;">
                                    <label>
                                        <input type="checkbox" 
                                               name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($size); ?>]" 
                                               value="1" 
                                               <?php checked(isset($settings[$size]) && $settings[$size]); ?>>
                                        <?php esc_html_e('Disable this size', 'disable-all-thumbnails'); ?>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </form>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php esc_html_e('Delete Existing Thumbnails', 'disable-all-thumbnails'); ?></h2>
                <p><?php esc_html_e('Click the button below to delete all previously generated thumbnail files for existing images. This action is irreversible!', 'disable-all-thumbnails'); ?></p>
                <p>
                    <button type="button" 
                            id="delete-thumbnails"
                            class="button button-secondary">
                        <?php esc_html_e('Delete All Thumbnails', 'disable-all-thumbnails'); ?>
                    </button>
                </p>
                
                <div id="delete-progress-wrapper" style="display:none; margin-top: 15px;">
                    <div class="progress-bar-container" style="background:#eee; border-radius:4px; height:20px; width:100%; overflow:hidden; border: 1px solid #ccc;">
                        <div id="delete-progress-bar" style="background:#2271b1; height:100%; width:0%; transition: width 0.3s;"></div>
                    </div>
                    <p id="delete-status" style="margin-top:8px; font-weight:bold;"></p>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin.
new DisableAllThumbnails();