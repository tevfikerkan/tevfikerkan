<?php
/**
 * Admin Settings Page for FursonaPrints
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FursonaPrints_Admin_Settings {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add settings page to admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'fursonaprints',
            __('Settings', 'fursonaprints'),
            __('Settings', 'fursonaprints'),
            'manage_options',
            'fursonaprints-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register all settings
        register_setting('fursonaprints_settings', 'fursonaprints_gelato_api_key');
        register_setting('fursonaprints_settings', 'fursonaprints_gelato_store_id');
        register_setting('fursonaprints_settings', 'fursonaprints_gelato_template_id');
        register_setting('fursonaprints_settings', 'fursonaprints_gelato_layer_name');
        register_setting('fursonaprints_settings', 'fursonaprints_gelato_variant_a4');
        register_setting('fursonaprints_settings', 'fursonaprints_gelato_variant_a3');

        // API Credentials Section
        add_settings_section(
            'fursonaprints_gelato_section',
            'Gelato API Configuration',
            [$this, 'render_gelato_section'],
            'fursonaprints-settings'
        );

        add_settings_field(
            'fursonaprints_gelato_api_key',
            'Gelato API Key',
            [$this, 'render_api_key_field'],
            'fursonaprints-settings',
            'fursonaprints_gelato_section'
        );

        add_settings_field(
            'fursonaprints_gelato_store_id',
            'Gelato Store ID',
            [$this, 'render_store_id_field'],
            'fursonaprints-settings',
            'fursonaprints_gelato_section'
        );

        // Template Configuration Section
        add_settings_section(
            'fursonaprints_template_section',
            'Gelato Template Configuration',
            [$this, 'render_template_section'],
            'fursonaprints-settings'
        );

        add_settings_field(
            'fursonaprints_gelato_template_id',
            'Template ID',
            [$this, 'render_template_id_field'],
            'fursonaprints-settings',
            'fursonaprints_template_section'
        );

        add_settings_field(
            'fursonaprints_gelato_layer_name',
            'Image Layer Name',
            [$this, 'render_layer_name_field'],
            'fursonaprints-settings',
            'fursonaprints_template_section'
        );

        // Product Variants Section
        add_settings_section(
            'fursonaprints_variants_section',
            'Product Variants',
            [$this, 'render_variants_section'],
            'fursonaprints-settings'
        );

        add_settings_field(
            'fursonaprints_gelato_variant_a4',
            'A4 Product UID',
            [$this, 'render_variant_a4_field'],
            'fursonaprints-settings',
            'fursonaprints_variants_section'
        );

        add_settings_field(
            'fursonaprints_gelato_variant_a3',
            'A3 Product UID',
            [$this, 'render_variant_a3_field'],
            'fursonaprints-settings',
            'fursonaprints_variants_section'
        );
    }

    /**
     * Render Gelato section description
     */
    public function render_gelato_section() {
        echo '<p>Configure your Gelato API credentials for print-on-demand integration.</p>';
    }

    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $value = get_option('fursonaprints_gelato_api_key', '');
        echo '<input type="text" name="fursonaprints_gelato_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Gelato API key (format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx-xxxx:xxxx)</p>';
    }

    /**
     * Render Store ID field
     */
    public function render_store_id_field() {
        $value = get_option('fursonaprints_gelato_store_id', '');
        echo '<input type="text" name="fursonaprints_gelato_store_id" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Gelato Store ID (UUID format)</p>';
    }

    /**
     * Render Template section description
     */
    public function render_template_section() {
        echo '<p>Configure the Gelato template settings for your product creation.</p>';
    }

    /**
     * Render Template ID field
     */
    public function render_template_id_field() {
        $value = get_option('fursonaprints_gelato_template_id', '85d917c6-b1c1-4637-a724-f4f267a37c27');
        echo '<input type="text" name="fursonaprints_gelato_template_id" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">The Gelato template ID for your product (UUID format)</p>';
    }

    /**
     * Render Layer Name field
     */
    public function render_layer_name_field() {
        $value = get_option('fursonaprints_gelato_layer_name', 'pet_portrait');
        echo '<input type="text" name="fursonaprints_gelato_layer_name" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">The image layer name in your Gelato template (e.g., "pet_portrait")</p>';
    }

    /**
     * Render Variants section description
     */
    public function render_variants_section() {
        echo '<p>Configure the product variants (sizes) to create for each AI-generated portrait.</p>';
    }

    /**
     * Render A4 Variant field
     */
    public function render_variant_a4_field() {
        $value = get_option('fursonaprints_gelato_variant_a4', 'flat_a4-8x12-inch_200-gsm-80lb-uncoated_4-0_ver');
        echo '<input type="text" name="fursonaprints_gelato_variant_a4" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Product UID for A4 size (8x12 inch)</p>';
    }

    /**
     * Render A3 Variant field
     */
    public function render_variant_a3_field() {
        $value = get_option('fursonaprints_gelato_variant_a3', 'flat_a3_200-gsm-80lb-uncoated_4-0_ver');
        echo '<input type="text" name="fursonaprints_gelato_variant_a3" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Product UID for A3 size (12x16 inch)</p>';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show success message if settings were saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'fursonaprints_messages',
                'fursonaprints_message',
                'Settings saved successfully',
                'updated'
            );
        }

        settings_errors('fursonaprints_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('fursonaprints_settings');
                do_settings_sections('fursonaprints-settings');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }
}

// Initialize admin settings only in admin context
if (is_admin()) {
    new FursonaPrints_Admin_Settings();
}
