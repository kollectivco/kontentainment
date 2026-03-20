<?php
/**
 * Plugin Name: Kontentainment
 * Plugin URI:  https://kollectiv.net
 * Description: A premium movie and cinema discovery platform.
 * Version:     1.6.12
 * Author:      Kollectiv
 * Author URI:  https://kollectiv.net
 * License:     GPL2
 * Text Domain: kontentainment
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KTN_PLUGIN_VERSION', '1.6.12');
define('KTN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KTN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KTN_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Core includes
require_once KTN_PLUGIN_DIR . 'includes/helpers.php';
require_once KTN_PLUGIN_DIR . 'includes/post-type.php';
require_once KTN_PLUGIN_DIR . 'includes/taxonomy.php';
require_once KTN_PLUGIN_DIR . 'includes/settings.php';
require_once KTN_PLUGIN_DIR . 'includes/metabox-import.php';
require_once KTN_PLUGIN_DIR . 'includes/metabox-movie.php';
require_once KTN_PLUGIN_DIR . 'includes/importer-tmdb.php';
require_once KTN_PLUGIN_DIR . 'includes/save-meta.php';
require_once KTN_PLUGIN_DIR . 'includes/admin-notices.php';
require_once KTN_PLUGIN_DIR . 'includes/database.php';
require_once KTN_PLUGIN_DIR . 'includes/scraper.php';
require_once KTN_PLUGIN_DIR . 'includes/importer-cinema.php';
require_once KTN_PLUGIN_DIR . 'includes/metabox-cinema.php';
require_once KTN_PLUGIN_DIR . 'includes/admin-showtimes.php';
require_once KTN_PLUGIN_DIR . 'includes/admin-bulk-import.php';
require_once KTN_PLUGIN_DIR . 'includes/cinema-guides.php';
require_once KTN_PLUGIN_DIR . 'includes/card-system.php';

// Elementor Integration
require_once KTN_PLUGIN_DIR . 'elementor/manager.php';

// Frontend Assets
add_action('wp_enqueue_scripts', 'ktn_enqueue_frontend_assets');
function ktn_enqueue_frontend_assets()
{
    wp_enqueue_style('ktn-card-system', KTN_PLUGIN_URL . 'assets/css/card-system.css', array(), KTN_PLUGIN_VERSION);
    wp_enqueue_style('dashicons');
}

// Plugin Update Checker setup
require_once KTN_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

add_action('plugins_loaded', 'ktn_init_github_updater');
function ktn_init_github_updater()
{
    $github_repo = 'https://github.com/kollectivco/kontentainment';
    
    $ktnUpdateChecker = PucFactory::buildUpdateChecker(
        $github_repo,
        __FILE__,
        'kontentainment'
    );

    $github_token = get_option('ktn_github_token');
    if (!empty($github_token)) {
        $ktnUpdateChecker->setAuthentication($github_token);
    }

    $ktnUpdateChecker->setBranch('main');
}

// Custom Cron Schedule
add_filter('cron_schedules', 'ktn_add_cron_schedules');
function ktn_add_cron_schedules($schedules) {
    if (!isset($schedules['two_hours'])) {
        $schedules['two_hours'] = array(
            'interval' => 2 * HOUR_IN_SECONDS,
            'display'  => __('Every 2 Hours', 'kontentainment'),
        );
    }
    if (!isset($schedules['twelve_hours'])) {
        $schedules['twelve_hours'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __('Every 12 Hours', 'kontentainment'),
        );
    }
    return $schedules;
}

/**
 * 2-Hour Auto Sync Job
 */
add_action('ktn_cinema_auto_sync_job', 'ktn_execute_2h_auto_sync');
function ktn_execute_2h_auto_sync() {
    if (class_exists('Ktn_Cinema_Importer')) {
        // Only sync cinemas that have "Enabled Auto Sync" checked
        Ktn_Cinema_Importer::syncAllCinemas(true); 
    }
}

/**
 * Legacy 12-Hour Sync Job (kept for manual/all active)
 */
add_action('ktn_sync_all_cinemas_cron', 'ktn_execute_legacy_sync');
function ktn_execute_legacy_sync() {
    if (class_exists('Ktn_Cinema_Importer')) {
        Ktn_Cinema_Importer::syncAllCinemas(false); // Sync all active regardless of 2h toggle
    }
}

register_activation_hook(__FILE__, 'ktn_activate_plugin');
function ktn_activate_plugin()
{
    ktn_init_database(); // Ensure DB is updated
    ktn_register_post_types();
    ktn_register_taxonomies();
    
    // Schedule 2-hour job
    if (!wp_next_scheduled('ktn_cinema_auto_sync_job')) {
        wp_schedule_event(time(), 'two_hours', 'ktn_cinema_auto_sync_job');
    }

    // Schedule 12-hour job (legacy)
    if (!wp_next_scheduled('ktn_sync_all_cinemas_cron')) {
        wp_schedule_event(time(), 'twelve_hours', 'ktn_sync_all_cinemas_cron');
    }
    
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'ktn_deactivate_plugin');
function ktn_deactivate_plugin()
{
    wp_clear_scheduled_hook('ktn_cinema_auto_sync_job');
    wp_clear_scheduled_hook('ktn_sync_all_cinemas_cron');
    flush_rewrite_rules();
}