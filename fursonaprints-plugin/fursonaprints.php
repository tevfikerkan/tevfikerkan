<?php
/**
 * Plugin Name: FursonaPrints
 * Description: AI Pet Portrait Generator with Gelato Print-on-Demand
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: fursonaprints
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FURSONAPRINTS_VERSION', '1.0.0');
define('FURSONAPRINTS_PATH', plugin_dir_path(__FILE__));
define('FURSONAPRINTS_URL', plugin_dir_url(__FILE__));

// Include required files
require_once FURSONAPRINTS_PATH . 'includes/class-cpt.php';
require_once FURSONAPRINTS_PATH . 'includes/class-rest-api.php';
require_once FURSONAPRINTS_PATH . 'includes/class-form.php';
require_once FURSONAPRINTS_PATH . 'includes/class-result-page.php';

// Initialize plugin
function fursonaprints_init() {
    // Initialize CPT
    new FursonaPrints_CPT();
    
    // Initialize REST API
    new FursonaPrints_REST_API();
    
    // Initialize Form
    new FursonaPrints_Form();
    
    // Initialize Result Page
    new FursonaPrints_Result_Page();
}
add_action('plugins_loaded', 'fursonaprints_init');

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