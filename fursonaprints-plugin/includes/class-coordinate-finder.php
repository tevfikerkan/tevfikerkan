<?php
/**
 * Coordinate Finder Admin Page for FursonaPrints
 * Allows admins to upload mockup images and define perspective transformation coordinates
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FursonaPrints_Coordinate_Finder {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_save_mockup_coordinates', [$this, 'ajax_save_coordinates']);
        add_action('wp_ajax_delete_mockup', [$this, 'ajax_delete_mockup']);
        add_action('wp_ajax_get_mockup_coordinates', [$this, 'ajax_get_coordinates']);
    }

    /**
     * Add Coordinate Finder to admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Mockup Coordinates', 'fursonaprints'),
            __('Mockup Coordinates', 'fursonaprints'),
            'manage_options',
            'fursonaprints-coordinates',
            [$this, 'render_page'],
            'dashicons-images-alt2',
            30
        );
    }

    /**
     * Enqueue scripts and styles for coordinate finder
     */
    public function enqueue_scripts($hook) {
        // Only load on our admin page
        if ($hook !== 'toplevel_page_fursonaprints-coordinates') {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'fursonaprints-coordinate-finder',
            FURSONAPRINTS_URL . 'assets/css/coordinate-finder.css',
            [],
            FURSONAPRINTS_VERSION
        );

        wp_enqueue_script(
            'fursonaprints-coordinate-finder',
            FURSONAPRINTS_URL . 'assets/js/coordinate-finder.js',
            ['jquery'],
            FURSONAPRINTS_VERSION,
            true
        );

        wp_localize_script('fursonaprints-coordinate-finder', 'fursonaPrintsCoords', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fursonaprints_coordinates'),
        ]);
    }

    /**
     * Save mockup coordinates via AJAX
     */
    public function ajax_save_coordinates() {
        check_ajax_referer('fursonaprints_coordinates', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'fursonaprints')]);
        }

        $mockup_name = sanitize_text_field($_POST['mockup_name'] ?? '');
        $image_url = esc_url_raw($_POST['image_url'] ?? '');
        $coordinates = json_decode(stripslashes($_POST['coordinates'] ?? '[]'), true);

        if (empty($mockup_name) || empty($image_url) || empty($coordinates)) {
            wp_send_json_error(['message' => __('Missing required fields', 'fursonaprints')]);
        }

        // Validate coordinates format
        if (count($coordinates) !== 4) {
            wp_send_json_error(['message' => __('Invalid coordinates format', 'fursonaprints')]);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fursonaprints_mockups';

        $result = $wpdb->replace(
            $table_name,
            [
                'mockup_name' => $mockup_name,
                'image_url' => $image_url,
                'coordinates' => json_encode($coordinates),
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            wp_send_json_error(['message' => __('Failed to save coordinates', 'fursonaprints')]);
        }

        wp_send_json_success([
            'message' => __('Coordinates saved successfully', 'fursonaprints'),
            'mockup_id' => $wpdb->insert_id
        ]);
    }

    /**
     * Delete mockup via AJAX
     */
    public function ajax_delete_mockup() {
        check_ajax_referer('fursonaprints_coordinates', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'fursonaprints')]);
        }

        $mockup_id = intval($_POST['mockup_id'] ?? 0);

        if (!$mockup_id) {
            wp_send_json_error(['message' => __('Invalid mockup ID', 'fursonaprints')]);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fursonaprints_mockups';

        $result = $wpdb->delete($table_name, ['id' => $mockup_id], ['%d']);

        if ($result === false) {
            wp_send_json_error(['message' => __('Failed to delete mockup', 'fursonaprints')]);
        }

        wp_send_json_success(['message' => __('Mockup deleted successfully', 'fursonaprints')]);
    }

    /**
     * Get mockup coordinates via AJAX
     */
    public function ajax_get_coordinates() {
        check_ajax_referer('fursonaprints_coordinates', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'fursonaprints')]);
        }

        $mockup_id = intval($_POST['mockup_id'] ?? 0);

        if (!$mockup_id) {
            wp_send_json_error(['message' => __('Invalid mockup ID', 'fursonaprints')]);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fursonaprints_mockups';

        $mockup = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $mockup_id
        ));

        if (!$mockup) {
            wp_send_json_error(['message' => __('Mockup not found', 'fursonaprints')]);
        }

        wp_send_json_success([
            'mockup_name' => $mockup->mockup_name,
            'image_url' => $mockup->image_url,
            'coordinates' => json_decode($mockup->coordinates, true)
        ]);
    }

    /**
     * Render the coordinate finder page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get existing mockups
        global $wpdb;
        $table_name = $wpdb->prefix . 'fursonaprints_mockups';
        $mockups = $wpdb->get_results("SELECT * FROM $table_name ORDER BY updated_at DESC");

        ?>
        <div class="wrap fursonaprints-coordinates-page">
            <h1><?php echo esc_html__('Mockup Coordinate Finder', 'fursonaprints'); ?></h1>

            <div class="fursonaprints-grid">
                <!-- Left Panel: Coordinate Finder -->
                <div class="fursonaprints-panel fursonaprints-finder-panel">
                    <h2><?php echo esc_html__('Define Coordinates', 'fursonaprints'); ?></h2>

                    <div class="fursonaprints-form-group">
                        <label for="mockup-name">
                            <?php echo esc_html__('Mockup Name:', 'fursonaprints'); ?>
                        </label>
                        <input type="text" id="mockup-name" class="regular-text"
                               placeholder="<?php echo esc_attr__('e.g., Portrait Frame A4', 'fursonaprints'); ?>">
                    </div>

                    <div class="fursonaprints-form-group">
                        <button type="button" id="upload-mockup-btn" class="button button-secondary">
                            <?php echo esc_html__('Upload Mockup Image', 'fursonaprints'); ?>
                        </button>
                        <p class="description">
                            <?php echo esc_html__('Upload a mockup image, then click to define the four corner points.', 'fursonaprints'); ?>
                        </p>
                    </div>

                    <div id="canvas-container" class="fursonaprints-canvas-container" style="display:none;">
                        <canvas id="mockup-canvas"></canvas>
                        <div class="fursonaprints-instructions">
                            <p><?php echo esc_html__('Click on the image to place the 4 corner points. Hold CMD/Ctrl to drag and adjust.', 'fursonaprints'); ?></p>
                            <div class="fursonaprints-point-status">
                                <span id="point-count">0</span> / 4 <?php echo esc_html__('points defined', 'fursonaprints'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="fursonaprints-actions">
                        <button type="button" id="reset-points-btn" class="button" disabled>
                            <?php echo esc_html__('Reset Points', 'fursonaprints'); ?>
                        </button>
                        <button type="button" id="save-coordinates-btn" class="button button-primary" disabled>
                            <?php echo esc_html__('Save Coordinates', 'fursonaprints'); ?>
                        </button>
                    </div>

                    <div id="coordinate-message" class="notice" style="display:none;"></div>
                </div>

                <!-- Right Panel: Saved Mockups -->
                <div class="fursonaprints-panel fursonaprints-list-panel">
                    <h2><?php echo esc_html__('Saved Mockups', 'fursonaprints'); ?></h2>

                    <div id="mockups-list" class="fursonaprints-mockups-list">
                        <?php if (empty($mockups)): ?>
                            <p class="no-mockups">
                                <?php echo esc_html__('No mockups saved yet. Upload and define coordinates to get started.', 'fursonaprints'); ?>
                            </p>
                        <?php else: ?>
                            <?php foreach ($mockups as $mockup): ?>
                                <div class="mockup-item" data-mockup-id="<?php echo esc_attr($mockup->id); ?>">
                                    <img src="<?php echo esc_url($mockup->image_url); ?>" alt="<?php echo esc_attr($mockup->mockup_name); ?>">
                                    <div class="mockup-info">
                                        <h3><?php echo esc_html($mockup->mockup_name); ?></h3>
                                        <p class="mockup-date">
                                            <?php echo esc_html(sprintf(
                                                __('Updated: %s', 'fursonaprints'),
                                                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($mockup->updated_at))
                                            )); ?>
                                        </p>
                                        <div class="mockup-actions">
                                            <button class="button button-small edit-mockup" data-mockup-id="<?php echo esc_attr($mockup->id); ?>">
                                                <?php echo esc_html__('Edit', 'fursonaprints'); ?>
                                            </button>
                                            <button class="button button-small button-link-delete delete-mockup" data-mockup-id="<?php echo esc_attr($mockup->id); ?>">
                                                <?php echo esc_html__('Delete', 'fursonaprints'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Create database table for mockup coordinates
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fursonaprints_mockups';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            mockup_name varchar(255) NOT NULL,
            image_url text NOT NULL,
            coordinates text NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY mockup_name (mockup_name)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize coordinate finder only in admin context
if (is_admin()) {
    new FursonaPrints_Coordinate_Finder();
}
