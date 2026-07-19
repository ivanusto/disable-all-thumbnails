<?php
/**
 * Plugin Name: Disable All Thumbnails
 * Plugin URI: https://github.com/ivanusto/disable-all-thumbnails
 * Description: Prevent the generation of specific thumbnail formats to save disk space and improve performance. / 停用 WordPress 所有縮圖格式生成功能，優化網站空間使用並提升效能。
 * Version: 1.1.0
 * Author: Ivan Lin
 * Author URI: https://yblog.org
 * License: Apache-2.0
 * License URI: http://www.apache.org/licenses/LICENSE-2.0
 * Text Domain: disable-all-thumbnails
 * Domain Path: /languages
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class DisableAllThumbnails {
    
    private $option_name = 'disable_thumbnails_settings';
    private $sizes_option_name = 'disable_thumbnails_known_sizes';
    private $cache_group = 'disable_thumbnails';
    
    public function __construct() {
        // Load text domain
        add_action('init', array($this, 'load_textdomain'));

        // Basic functionality
        add_action('init', array($this, 'disable_existing_image_sizes'), 999);
        add_filter('intermediate_image_sizes_advanced', array($this, 'disable_image_sizes'), 999);
        
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
     * Load translation files
     */
    public function load_textdomain() {
        load_plugin_textdomain('disable-all-thumbnails', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Enqueue admin JavaScript
     */
    public function enqueue_admin_scripts($hook_suffix) {
        if (isset($_GET['page']) && $_GET['page'] === 'disable-thumbnails-settings') {
            wp_enqueue_script(
                'disable-thumbnails-admin',
                plugin_dir_url(__FILE__) . 'js/admin.js',
                array('jquery'),
                '1.1.0',
                true
            );
            
            wp_localize_script('disable-thumbnails-admin', 'disableThumbnailsL10n', array(
                'ajax_url'         => admin_url('admin-ajax.php'),
                'nonce'            => wp_create_nonce('delete_thumbnails_nonce'),
                'confirm_message'  => __('Are you sure you want to delete all thumbnails? This action cannot be undone.', 'disable-all-thumbnails'),
                'deleting_message' => __('Starting deletion...', 'disable-all-thumbnails'),
                'progress_message' => __('Processed %1$d of %2$d images. Deleted %3$d files so far...', 'disable-all-thumbnails'),
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
        
        // Handle builtin sizes
        foreach ($builtin_sizes as $size) {
            if (isset($registered_sizes[$size]) || in_array($size, array('1536x1536', '2048x2048'))) {
                $width = get_option("{$size}_size_w");
                $height = get_option("{$size}_size_h");
                $crop = get_option("{$size}_crop");
                
                $sizes[$size] = array(
                    'width' => $width,
                    'height' => $height,
                    'crop' => $crop,
                    'builtin' => true
                );
            }
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
        
        return $sizes;
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
                
                // Update width/height option for built-in sizes
                if (in_array($size, array('thumbnail', 'medium', 'large'), true)) {
                    update_option("{$size}_size_w", 0);
                    update_option("{$size}_size_h", 0);
                }
            }
        }
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
            if ($disabled && isset($sizes[$size])) {
                unset($sizes[$size]);
            }
        }
        
        return $sizes;
    }

    /**
     * Add settings page menu item
     */
    public function add_settings_page() {
        add_options_page(
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
            $metadata = wp_get_attachment_metadata($image_id);
            if (empty($metadata) || empty($metadata['sizes'])) {
                continue;
            }

            if (isset($metadata['file'])) {
                $file_dir = trailingslashit(dirname($metadata['file']));
                
                foreach ($metadata['sizes'] as $size => $size_info) {
                    if (empty($size_info['file'])) {
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
                    if (file_exists($webp_path)) {
                        wp_delete_file($webp_path);
                        $deleted_batch++;
                    }
                    
                    // Delete corresponding AVIF file if exists
                    $avif_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.avif', $file_path);
                    if (file_exists($avif_path)) {
                        wp_delete_file($avif_path);
                        $deleted_batch++;
                    }
                }
                
                // Clear thumbnail sizes from attachment metadata
                $metadata['sizes'] = array();
                wp_update_attachment_metadata($image_id, $metadata);
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
                                        <?php printf(esc_html__('%1$spx &times; %2$spx', 'disable-all-thumbnails'), esc_html($data['width']), esc_html($data['height'])); ?>
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
$disable_all_thumbnails = new DisableAllThumbnails();