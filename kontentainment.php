<?php
/**
 * Plugin Name: Kontentainment
 * Plugin URI:  https://kollectiv.net
 * Description: A premium movie and cinema discovery platform.
 * Version:     1.6.20
 * Author:      Kollectiv
 * Author URI:  https://kollectiv.net
 * License:     GPL2
 * Text Domain: kontentainment
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KTN_PLUGIN_VERSION', '1.6.20');
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

add_action('plugins_loaded', 'ktn_init_github_updater');
function ktn_init_github_updater()
{
    $github_repo_url = 'https://github.com/kollectivco/kontentainment';
    
    $ktnUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        $github_repo_url,
        __FILE__,
        'kontentainment'
    );

    $github_token = get_option('ktn_github_token');
    if (!empty($github_token)) {
        $ktnUpdateChecker->setAuthentication($github_token);
    }

    $ktnUpdateChecker->setBranch('main');
}

/**
 * Set a custom User-Agent for GitHub API requests to avoid 403 Forbidden errors.
 * GitHub often rejects the default WordPress User-Agent on some servers.
 */
add_filter('puc_request_info_options-kontentainment', 'ktn_puc_custom_user_agent');
function ktn_puc_custom_user_agent($options) {
    if (!isset($options['headers'])) {
        $options['headers'] = array();
    }
    $options['headers']['User-Agent'] = 'KontentainmentUpdater/1.6.20; ' . get_bloginfo('url');
    return $options;
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

add_action('admin_init', 'ktn_handle_activation_redirect');
/**
 * Handle one-time redirect after plugin activation.
 */
function ktn_handle_activation_redirect() {
    // Only proceed if our special flag is set
    if (get_option('ktn_activation_redirect')) {
        delete_option('ktn_activation_redirect');

        // Safety Checks
        if (
            !is_admin() || 
            !current_user_can('manage_options') ||
            wp_doing_ajax() ||
            (defined('DOING_CRON') && DOING_CRON) ||
            (defined('REST_REQUEST') && REST_REQUEST) ||
            isset($_GET['activate-multi']) || // Skip bulk
            (isset($_GET['page']) && $_GET['page'] === 'kontentainment-settings')
        ) {
            return;
        }

        wp_safe_redirect(admin_url('edit.php?post_type=movie&page=kontentainment-settings'));
        exit;
    }
}

register_activation_hook(__FILE__, 'ktn_activate_plugin');
function ktn_activate_plugin()
{
    ktn_create_database_tables(); // Ensure DB is updated
    ktn_register_post_types();
    ktn_register_taxonomies();
    
    // Set flag for one-time redirect
    add_option('ktn_activation_redirect', true);

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