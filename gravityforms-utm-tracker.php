<?php
/**
 * Plugin Name: Gravity Forms UTM Tracker
 * Plugin URI: https://fsm.agency
 * Description: Automatically adds UTM tracking fields to all Gravity Forms
 * Version: 1.2
 * Author: Full Spectrum Marketing - RHT
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gf-utm-tracker
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('GF_UTM_TRACKER_VERSION', '1.2');
define('GF_UTM_TRACKER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GF_UTM_TRACKER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load main plugin class
require_once GF_UTM_TRACKER_PLUGIN_DIR . 'includes/class-gf-utm-tracker.php';

// Initialize updater
require_once GF_UTM_TRACKER_PLUGIN_DIR . 'includes/class-plugin-updater.php';

if (is_admin()) {
    new GF_UTM_Tracker_Updater(__FILE__);
}

// Initialize plugin
function gf_utm_tracker_init() {
    if (class_exists('GFForms')) {
        return GF_UTM_Tracker::get_instance();
    }
}
add_action('plugins_loaded', 'gf_utm_tracker_init');