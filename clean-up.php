<?php
/* Theme Optimization Suite - Clean-ups for WP-starter theme.
 Description: Complete disable Google Fonts, Image Size cleanup, Security hardening, and Bloat removal.
 Version: 3.1 */

// =========================================================================
// 1. GOOGLE FONTS KILL SWITCH (Aggressive)
// =========================================================================

// Method 1: Scan all registered styles and dequeue anything from Google
function pro_optim_remove_google_fonts_dequeue()
{
    global $wp_styles;
    if (isset($wp_styles->registered)) {
        foreach ($wp_styles->registered as $handle => $data) {
            // Check for google fonts domains
            if (strpos($data->src, 'fonts.googleapis.com') !== false || strpos($data->src, 'fonts.gstatic.com') !== false) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }
}
// Run late to catch themes/plugins
add_action('wp_enqueue_scripts', 'pro_optim_remove_google_fonts_dequeue', 100);

// Only dequeue Google Fonts in admin pages that are NOT the block editor,
// to avoid breaking editor UI components (e.g. featured image panel).
add_action('admin_enqueue_scripts', function () {
    // get_current_screen() may not be available yet at early hooks; it is fine here.
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    // Skip on block editor screens (post/page/CPT using Gutenberg)
    if ($screen && $screen->is_block_editor()) {
        return;
    }
    pro_optim_remove_google_fonts_dequeue();
}, 100);

// Method 2: Filter the generated HTML link tags just in case
function pro_optim_disable_google_fonts_src($src, $handle)
{
    if (strpos($src, 'fonts.googleapis.com') !== false || strpos($src, 'fonts.gstatic.com') !== false) {
        return '';
    }
    return $src;
}
add_filter('style_loader_src', 'pro_optim_disable_google_fonts_src', 999, 2);

// Method 3: Remove DNS Prefetch for Fonts
function pro_optim_remove_font_hints($hints, $relation_type)
{
    if ('dns-prefetch' === $relation_type || 'preconnect' === $relation_type) {
        foreach ($hints as $key => $hint) {
            if (strpos($hint, 'fonts.googleapis.com') !== false || strpos($hint, 'fonts.gstatic.com') !== false) {
                unset($hints[$key]);
            }
        }
    }
    return $hints;
}
add_filter('wp_resource_hints', 'pro_optim_remove_font_hints', 10, 2);

// =========================================================================
// 2. IMAGE OPTIMIZATION (Aggressive but Safe)
// =========================================================================

function pro_optim_disable_image_sizes($sizes)
{
    // We KEEP 'thumbnail' because removing it breaks the WP Admin Media Library Grid
    // We remove everything else to save disk space

    // Default WP sizes
    unset($sizes['medium']);
    unset($sizes['medium_large']);
    unset($sizes['large']);
    unset($sizes['1536x1536']);
    unset($sizes['2048x2048']);

    // If you want to be extremely aggressive and kill 'thumbnail' too (NOT RECOMMENDED), uncomment below:
    // unset( $sizes['thumbnail'] ); 

    return $sizes;
}
add_filter('intermediate_image_sizes_advanced', 'pro_optim_disable_image_sizes');

// NOTE: srcset is intentionally LEFT ENABLED.
// srcset lets browsers pick the right image size per viewport, which is critical
// for mobile performance and Core Web Vitals (LCP). Disabling it forces mobile
// users to download full-size desktop images.

// NOTE: big_image_size_threshold is intentionally LEFT ENABLED (default 2560px).
// This protects against content editors uploading raw camera photos (8000px+)
// that would bloat pages and hurt load times.

// NOTE: Manual eager/lazy loading overrides are intentionally REMOVED.
// WordPress 6.3+ automatically detects the LCP image and applies fetchpriority="high"
// and loading="eager" only where appropriate. Manual overrides conflict with this.

// =========================================================================
// 3. HEAD & BLOAT CLEANUP
// =========================================================================

function pro_optim_head_cleanup()
{
    // Emojis
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

    // OEmbeds (Embeds from other sites)
    

    

    // Meta Tags
    remove_action('wp_head', 'wp_generator'); // Version number
    remove_action('wp_head', 'wlwmanifest_link'); // Windows Live Writer
    remove_action('wp_head', 'rsd_link'); // Really Simple Discovery
    remove_action('wp_head', 'wp_shortlink_wp_head'); // Shortlink

    // REST API link in head
    remove_action('wp_head', 'rest_output_link_wp_head', 10);

    // RSS Feeds (Comment out below if you run a blog and need feeds)
    remove_action('wp_head', 'feed_links', 2);
    remove_action('wp_head', 'feed_links_extra', 3);
}
add_action('init', 'pro_optim_head_cleanup');

// Remove emoji TinyMCE plugin from the editor
add_filter('tiny_mce_plugins', function ($plugins) {
    return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : [];
});

// Remove recent comments widget inline styles
add_action('widgets_init', function () {
    global $wp_widget_factory;
    if (isset($wp_widget_factory->widgets['WP_Widget_Recent_Comments'])) {
        remove_action('wp_head', [$wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style']);
    }
});

// NOTE: ?ver= query string stripping has been intentionally REMOVED.
// The version parameter exists to bust browser caches when files change.
// Removing it has zero measurable speed benefit and makes cache invalidation
// unreliable after plugin/theme updates.

// =========================================================================
// 4. SCRIPT & STYLE MANAGEMENT (Gutenberg & jQuery)
// =========================================================================

function pro_optim_asset_management()
{
    if (!is_admin()) {
        // Remove jQuery Migrate
        wp_deregister_script('jquery-migrate');

        // Remove WP Embed script
        wp_deregister_script('wp-embed');

        // Remove Global Styles (The huge inline SVG/CSS block)
        wp_dequeue_style('global-styles');

        // Remove Classic Theme Styles
        wp_dequeue_style('classic-theme-styles');

        // Remove Dashicons for logged-out users (~46KB savings)
        if (!is_user_logged_in()) {
            wp_dequeue_style('dashicons');
            wp_deregister_style('dashicons');
        }

    // --- GUTENBERG MANAGEMENT ---
    // Uncomment the lines below ONLY if you DO NOT use Gutenberg at all.
    // wp_dequeue_style( 'wp-block-library' );
    // wp_dequeue_style( 'wp-block-library-theme' );
    // wp_dequeue_style( 'wc-blocks-style' ); // If WooCommerce
    }
}
add_action('wp_enqueue_scripts', 'pro_optim_asset_management', 100);

// Remove Global Inline Styles (SVG Filters)
add_action('after_setup_theme', function () {
    remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
    remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
});

// NOTE: 'should_load_separate_core_block_assets' has been disabled.
// Enabling it caused the featured image panel to disappear in the block editor
// because block assets (including editor UI components) were not loaded correctly.

// =========================================================================
// 5. SECURITY HARDENING
// =========================================================================

// Disable XML-RPC (Major security risk)
add_filter('xmlrpc_enabled', '__return_false');

// Remove X-Pingback Header and add Security Headers
add_filter('wp_headers', function ($headers) {
    unset($headers['X-Pingback']);
    $headers['X-Content-Type-Options'] = 'nosniff';
    $headers['X-Frame-Options'] = 'SAMEORIGIN';
    $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
    $headers['Permissions-Policy'] = 'geolocation=(), microphone=(), camera=()';
    return $headers;
});

// Obfuscate login error messages (Prevents username discovery)
add_filter('login_errors', function () {
    return 'Invalid credentials. Please try again.';
});

// Disable file editing from the WP dashboard (Appearance > Editor)
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

// Block User Enumeration (Scans for /?author=1)
function pro_optim_block_user_enumeration()
{
    $author = isset($_REQUEST['author']) ? sanitize_text_field($_REQUEST['author']) : '';
    if (!is_admin() && preg_match('/\d/', $author)) {
        wp_redirect(home_url(), 301);
        exit;
    }
}
add_action('template_redirect', 'pro_optim_block_user_enumeration');

// Disable REST API for Users Endpoint ONLY (Prevents listing users via JSON)
add_filter('rest_endpoints', function ($endpoints) {
    if (isset($endpoints['/wp/v2/users'])) {
        unset($endpoints['/wp/v2/users']);
    }
    if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) {
        unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    }
    return $endpoints;
});

// =========================================================================
// 6. ADMIN & PERFORMANCE TWEAKS
// =========================================================================

// Limit Heartbeat API to 60 seconds (saves CPU)
add_filter('heartbeat_settings', function ($settings) {
    $settings['interval'] = 60;
    return $settings;
});

// Disable Self Pingbacks
add_action('pre_ping', function (&$links) {
    $home = get_option('home');
    foreach ($links as $l => $link) {
        if (0 === strpos($link, $home)) {
            unset($links[$l]);
        }
    }
});

// Disable Auto-Update Emails (Keep dashboard clean)
add_filter('auto_core_update_send_email', '__return_false');
add_filter('auto_plugin_update_send_email', '__return_false');
add_filter('auto_theme_update_send_email', '__return_false');

// =========================================================================
// 7. COMMENTS CLEANUP (Uncomment if your site does NOT use comments)
// =========================================================================

// Remove comment-reply script from frontend
// add_action('wp_enqueue_scripts', function () {
//     wp_dequeue_script('comment-reply');
// });

// Disable comments entirely
// add_filter('comments_open', '__return_false', 20, 2);
// add_filter('pings_open', '__return_false', 20, 2);
// add_filter('comments_array', '__return_empty_array', 10, 2);

// Remove comments from admin menu
// add_action('admin_menu', function () {
//     remove_menu_page('edit-comments.php');
// });

// Remove comments from admin bar
// add_action('wp_before_admin_bar_render', function () {
//     global $wp_admin_bar;
//     $wp_admin_bar->remove_menu('comments');
// });
