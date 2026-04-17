<?php
/**
 * Plugin Name:       WT multi SEO Pro
 * Plugin URI:        https://yoursite.com/polylang-seo-pro
 * Description:       Advanced SEO plugin with full Polylang multilingual support. Includes XML sitemaps with hreflang, per-language meta fields, Open Graph, structured data, canonical URLs, and more.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://yoursite.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       polylang-seo
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'PLSEO_VERSION',     '1.0.0' );
define( 'PLSEO_FILE',        __FILE__ );
define( 'PLSEO_DIR',         plugin_dir_path( __FILE__ ) );
define( 'PLSEO_URL',         plugin_dir_url( __FILE__ ) );
define( 'PLSEO_SLUG',        'polylang-seo' );
define( 'PLSEO_DB_VERSION',  '1' );

/**
 * Check dependencies before loading.
 */
function plseo_check_dependencies(): bool {
    if ( ! function_exists( 'pll_languages_list' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'Polylang SEO Pro requires the Polylang plugin to be active.', 'polylang-seo' )
                . '</p></div>';
        } );
        return false;
    }
    return true;
}

/**
 * Bootstrap the plugin.
 */
function plseo_init(): void {
    if ( ! plseo_check_dependencies() ) {
        return;
    }

    // Load text domain
    load_plugin_textdomain( 'polylang-seo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // Load core files
    $includes = [
        'includes/class-plseo-helpers.php',
        'includes/class-plseo-options.php',
        'includes/class-plseo-meta.php',
        'includes/class-plseo-sitemap.php',
        'includes/class-plseo-hreflang.php',
        'includes/class-plseo-canonical.php',
        'includes/class-plseo-opengraph.php',
        'includes/class-plseo-twitter-card.php',
        'includes/class-plseo-schema.php',
        'includes/class-plseo-breadcrumbs.php',
        'includes/class-plseo-robots.php',
        'includes/class-plseo-redirects.php',
        'admin/class-plseo-admin.php',
        'admin/class-plseo-metabox.php',
        'admin/class-plseo-taxonomy-meta.php',
        'admin/class-plseo-columns.php',
        'public/class-plseo-frontend.php',
    ];

    foreach ( $includes as $file ) {
        $path = PLSEO_DIR . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }

    // Instantiate main classes
    PLSEO_Options::get_instance();
    PLSEO_Meta::get_instance();
    PLSEO_Sitemap::get_instance();
    PLSEO_Hreflang::get_instance();
    PLSEO_Canonical::get_instance();
    PLSEO_OpenGraph::get_instance();
    PLSEO_Twitter_Card::get_instance();
    PLSEO_Schema::get_instance();
    PLSEO_Breadcrumbs::get_instance();
    PLSEO_Robots::get_instance();
    PLSEO_Redirects::get_instance();
    PLSEO_Frontend::get_instance();

    if ( is_admin() ) {
        PLSEO_Admin::get_instance();
        PLSEO_Metabox::get_instance();
        PLSEO_Taxonomy_Meta::get_instance();
        PLSEO_Columns::get_instance();
    }
}
add_action( 'plugins_loaded', 'plseo_init', 20 );

/**
 * Activation hook.
 */
function plseo_activate(): void {
    require_once PLSEO_DIR . 'includes/class-plseo-options.php';
    require_once PLSEO_DIR . 'includes/class-plseo-activator.php';
    PLSEO_Activator::activate();
}
register_activation_hook( __FILE__, 'plseo_activate' );

/**
 * Deactivation hook.
 */
function plseo_deactivate(): void {
    wp_clear_scheduled_hook( 'plseo_regenerate_sitemap' );
    // Flush rewrite rules to remove sitemap endpoints
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'plseo_deactivate' );
