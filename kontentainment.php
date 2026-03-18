<?php
/**
 * Plugin Name: Kontentainment
 * Plugin URI:  #
 * Description: A custom movie and TV show system that imports media data from TMDB using an IMDb ID.
 * Version:     1.3.0
 * Author:      Antigravity
 * License:     GPL2
 * Text Domain: kontentainment
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KTN_PLUGIN_VERSION', '1.3.0');
define('KTN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KTN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KTN_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once KTN_PLUGIN_DIR . 'includes/helpers.php';
require_once KTN_PLUGIN_DIR . 'includes/post-type.php';
require_once KTN_PLUGIN_DIR . 'includes/taxonomy.php';
require_once KTN_PLUGIN_DIR . 'includes/settings.php';
require_once KTN_PLUGIN_DIR . 'includes/metabox-import.php';
require_once KTN_PLUGIN_DIR . 'includes/metabox-movie.php';
require_once KTN_PLUGIN_DIR . 'includes/importer-tmdb.php';
require_once KTN_PLUGIN_DIR . 'includes/save-meta.php';
require_once KTN_PLUGIN_DIR . 'includes/watch-providers.php';
require_once KTN_PLUGIN_DIR . 'includes/admin-notices.php';
require_once KTN_PLUGIN_DIR . 'includes/database.php';
require_once KTN_PLUGIN_DIR . 'includes/scraper.php';
require_once KTN_PLUGIN_DIR . 'includes/importer-cinema.php';
require_once KTN_PLUGIN_DIR . 'includes/metabox-cinema.php';
require_once KTN_PLUGIN_DIR . 'includes/admin-showtimes.php';

// Elementor Integration
require_once KTN_PLUGIN_DIR . 'elementor/manager.php';

// Plugin Update Checker setup
require_once KTN_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

add_action('plugins_loaded', 'ktn_init_github_updater');
function ktn_init_github_updater()
{
    // Hardcoded repo URL to ensure updates always work
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

register_activation_hook(__FILE__, 'ktn_activate_plugin');
function ktn_activate_plugin()
{
    ktn_register_post_types();
    ktn_register_taxonomies();
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'ktn_deactivate_plugin');
function ktn_deactivate_plugin()
{
    flush_rewrite_rules();
}