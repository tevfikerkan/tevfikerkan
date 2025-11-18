<?php
/**
 * Plugin Name: FursonaPrints
 * Description: AI Pet Portrait Generator with Gelato Print-on-Demand
 * Version: 1.6.0
 * Author: The WP Clan
 * Text Domain: fursonaprints
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FURSONAPRINTS_VERSION', '1.6.0');
define('FURSONAPRINTS_PATH', plugin_dir_path(__FILE__));
define('FURSONAPRINTS_URL', plugin_dir_url(__FILE__));

// Include required files
require_once FURSONAPRINTS_PATH . 'includes/class-admin-settings.php';
require_once FURSONAPRINTS_PATH . 'includes/class-cpt.php';
require_once FURSONAPRINTS_PATH . 'includes/class-gelato-api.php';
require_once FURSONAPRINTS_PATH . 'includes/class-gelato-webhook.php';
require_once FURSONAPRINTS_PATH . 'includes/class-mockup-generator.php';
require_once FURSONAPRINTS_PATH . 'includes/class-save-result.php';
require_once FURSONAPRINTS_PATH . 'includes/class-check-result.php';
require_once FURSONAPRINTS_PATH . 'includes/class-get-mockups.php';
require_once FURSONAPRINTS_PATH . 'includes/class-form.php';
require_once FURSONAPRINTS_PATH . 'includes/class-result-page.php';

// Activation hook
register_activation_hook(__FILE__, 'fursonaprints_activate');
function fursonaprints_activate() {
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'fursonaprints_deactivate');
function fursonaprints_deactivate() {
    flush_rewrite_rules();
}