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
        add_action('admin_init', [$this, 'ensure_table_exists']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_save_mockup_coordinates', [$this, 'ajax_save_coordinates']);
        add_action('wp_ajax_delete_mockup', [$this, 'ajax_delete_mockup']);
        add_action('wp_ajax_get_mockup_coordinates', [$this, 'ajax_get_coordinates']);
    }

    /**
     * Ensure database table exists
     */
    public function ensure_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fursonaprints_mockups';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            self::create_table();
        }
    }

    /**
     * Add Coordinate Finder to admin menu
     */
    public function add_admin_menu() {
        // Add main parent menu
        add_menu_page(
            __('Fursona Prints', 'fursonaprints'),
            __('Fursona Prints', 'fursonaprints'),
            'manage_options',
            'fursonaprints',
            [$this, 'render_page'],
            'dashicons-images-alt2',
            30
        );

        // Add Mockup Coordinates as first submenu (same as parent)
        add_submenu_page(
            'fursonaprints',
            __('Mockup Coordinates', 'fursonaprints'),
            __('Mockup Coordinates', 'fursonaprints'),
            'manage_options',
            'fursonaprints',
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue scripts and styles for coordinate finder
     */
    public function enqueue_scripts($hook) {
        // Only load on our admin page
        if ($hook !== 'toplevel_page_fursonaprints') {
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
                               value="<?php echo esc_attr__('New Mockup Name', 'fursonaprints'); ?>">
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

                    <div class="fursonaprints-naming-notice notice notice-info inline">
                        <p><?php echo esc_html__('Please give a name to your mockup image before saving the coordinates.', 'fursonaprints'); ?></p>
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

        // Add default mockups if table is empty
        self::add_default_mockups();
    }

    /**
     * Add 3 default mockups to help users get started
     */
    public static function add_default_mockups() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fursonaprints_mockups';

        // Check if we already have mockups
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($count > 0) {
            error_log("Default mockups already exist, skipping creation");
            return;
        }

        error_log("Creating default mockup templates...");

        // Create upload directory
        $upload = wp_upload_dir();
        $mockup_dir = $upload['basedir'] . '/default-mockups/';
        if (!file_exists($mockup_dir)) {
            wp_mkdir_p($mockup_dir);
        }

        $default_mockups = [
            [
                'name' => 'A4 Frame on Wall',
                'width' => 800,
                'height' => 1000,
                'frame_color' => [139, 105, 20], // Gold
                'insert_area' => ['x' => 100, 'y' => 150, 'width' => 600, 'height' => 700]
            ],
            [
                'name' => 'A3 Portrait Frame',
                'width' => 1000,
                'height' => 1200,
                'frame_color' => [101, 67, 33], // Dark wood
                'insert_area' => ['x' => 120, 'y' => 180, 'width' => 760, 'height' => 840]
            ],
            [
                'name' => 'Square Canvas Frame',
                'width' => 900,
                'height' => 900,
                'frame_color' => [64, 64, 64], // Gray
                'insert_area' => ['x' => 150, 'y' => 150, 'width' => 600, 'height' => 600]
            ]
        ];

        foreach ($default_mockups as $mockup_data) {
            $image_path = self::create_default_mockup_image($mockup_data, $mockup_dir);

            if ($image_path) {
                $image_url = $upload['baseurl'] . '/default-mockups/' . basename($image_path);

                // Define coordinates for the insert area
                $coords = $mockup_data['insert_area'];
                $coordinates = [
                    ['x' => $coords['x'], 'y' => $coords['y']], // Top-left
                    ['x' => $coords['x'] + $coords['width'], 'y' => $coords['y']], // Top-right
                    ['x' => $coords['x'] + $coords['width'], 'y' => $coords['y'] + $coords['height']], // Bottom-right
                    ['x' => $coords['x'], 'y' => $coords['y'] + $coords['height']] // Bottom-left
                ];

                $wpdb->insert(
                    $table_name,
                    [
                        'mockup_name' => $mockup_data['name'],
                        'image_url' => $image_url,
                        'coordinates' => json_encode($coordinates),
                        'updated_at' => current_time('mysql')
                    ],
                    ['%s', '%s', '%s', '%s']
                );

                error_log("Created default mockup: {$mockup_data['name']}");
            }
        }
    }

    /**
     * Create a default mockup template image
     */
    private static function create_default_mockup_image($mockup_data, $output_dir) {
        try {
            $width = $mockup_data['width'];
            $height = $mockup_data['height'];

            // Create image
            $image = imagecreatetruecolor($width, $height);

            // Background (wall texture - light beige)
            $bg_color = imagecolorallocate($image, 240, 235, 220);
            imagefill($image, 0, 0, $bg_color);

            // Add subtle texture
            for ($i = 0; $i < 5000; $i++) {
                $x = rand(0, $width - 1);
                $y = rand(0, $height - 1);
                $noise_color = imagecolorallocate($image, rand(235, 245), rand(230, 240), rand(215, 225));
                imagesetpixel($image, $x, $y, $noise_color);
            }

            // Draw frame
            $coords = $mockup_data['insert_area'];
            $frame_color = imagecolorallocate($image, $mockup_data['frame_color'][0], $mockup_data['frame_color'][1], $mockup_data['frame_color'][2]);

            // Outer frame
            $frame_width = 20;
            imagefilledrectangle(
                $image,
                $coords['x'] - $frame_width,
                $coords['y'] - $frame_width,
                $coords['x'] + $coords['width'] + $frame_width,
                $coords['y'] + $coords['height'] + $frame_width,
                $frame_color
            );

            // Inner white mat
            $mat_color = imagecolorallocate($image, 255, 255, 255);
            imagefilledrectangle(
                $image,
                $coords['x'],
                $coords['y'],
                $coords['x'] + $coords['width'],
                $coords['y'] + $coords['height'],
                $mat_color
            );

            // Add placeholder text in center
            $text_color = imagecolorallocate($image, 200, 200, 200);
            $text = 'Your Portrait Here';
            $font_size = 3;
            $text_width = imagefontwidth($font_size) * strlen($text);
            $text_height = imagefontheight($font_size);
            $text_x = $coords['x'] + ($coords['width'] - $text_width) / 2;
            $text_y = $coords['y'] + ($coords['height'] - $text_height) / 2;
            imagestring($image, $font_size, $text_x, $text_y, $text, $text_color);

            // Save image
            $filename = sanitize_file_name($mockup_data['name']) . '.jpg';
            $filepath = $output_dir . $filename;
            imagejpeg($image, $filepath, 90);

            imagedestroy($image);

            return $filepath;

        } catch (Exception $e) {
            error_log("Error creating default mockup: " . $e->getMessage());
            return false;
        }
    }
}

// Initialize coordinate finder only in admin context
if (is_admin()) {
    new FursonaPrints_Coordinate_Finder();
}
