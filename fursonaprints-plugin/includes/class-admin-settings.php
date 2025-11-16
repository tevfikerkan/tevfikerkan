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
        add_options_page(
            'FursonaPrints Settings',
            'FursonaPrints',
            'manage_options',
            'fursonaprints-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('fursonaprints_settings', 'fursonaprints_gelato_api_key');
        register_setting('fursonaprints_settings', 'fursonaprints_gelato_store_id');

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
