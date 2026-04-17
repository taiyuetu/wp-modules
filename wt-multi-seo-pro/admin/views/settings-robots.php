<?php
defined( 'ABSPATH' ) || exit;
include PLSEO_DIR . 'admin/views/header.php';
?>
<form method="post">
    <?php wp_nonce_field( 'plseo_settings' ); ?>
    <div class="plseo-card">
        <h2><?php esc_html_e( 'Indexing Rules', 'polylang-seo' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Noindex Search Pages', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_noindex_search" <?php checked( PLSEO_Helpers::get_option( 'noindex_search', true ) ); ?> /> <?php esc_html_e( 'Prevent search result pages from being indexed', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><?php esc_html_e( 'Noindex 404 Pages', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_noindex_404" <?php checked( PLSEO_Helpers::get_option( 'noindex_404', true ) ); ?> /> <?php esc_html_e( 'Prevent 404 pages from being indexed', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><?php esc_html_e( 'Noindex Author Archives', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_noindex_author" <?php checked( PLSEO_Helpers::get_option( 'noindex_author', false ) ); ?> /> <?php esc_html_e( 'Prevent author archive indexing', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><?php esc_html_e( 'Noindex Date Archives', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_noindex_date" <?php checked( PLSEO_Helpers::get_option( 'noindex_date', true ) ); ?> /> <?php esc_html_e( 'Prevent date archive indexing', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><?php esc_html_e( 'Noindex Attachments', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_noindex_attachment" <?php checked( PLSEO_Helpers::get_option( 'noindex_attachment', true ) ); ?> /> <?php esc_html_e( 'Prevent attachment pages from being indexed', 'polylang-seo' ); ?></label></td></tr>
        </table>
    </div>

    <div class="plseo-card">
        <h2><?php esc_html_e( 'Canonical & Redirects', 'polylang-seo' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Enable Canonical URLs', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_canonical_enabled" <?php checked( PLSEO_Helpers::get_option( 'canonical_enabled', true ) ); ?> /> <?php esc_html_e( 'Output canonical tags', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><?php esc_html_e( 'Force HTTPS', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_canonical_force_https" <?php checked( PLSEO_Helpers::get_option( 'canonical_force_https', true ) ); ?> /> <?php esc_html_e( 'Normalize canonicals to HTTPS', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><?php esc_html_e( 'Trailing Slash', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_canonical_trailing_slash" <?php checked( PLSEO_Helpers::get_option( 'canonical_trailing_slash', true ) ); ?> /> <?php esc_html_e( 'Normalize canonicals with a trailing slash', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><?php esc_html_e( 'Strip Query Parameters', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_canonical_strip_params" <?php checked( PLSEO_Helpers::get_option( 'canonical_strip_params', true ) ); ?> /> <?php esc_html_e( 'Remove tracking parameters from canonicals', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><?php esc_html_e( 'Redirect 404 to Home', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_redirect_404_home" <?php checked( PLSEO_Helpers::get_option( 'redirect_404_home', false ) ); ?> /> <?php esc_html_e( '301 redirect all 404 pages to homepage', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><?php esc_html_e( 'Keep Old Slug Redirects', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_redirect_old_slugs" <?php checked( PLSEO_Helpers::get_option( 'redirect_old_slugs', true ) ); ?> /> <?php esc_html_e( 'Use WordPress old-slug redirects', 'polylang-seo' ); ?></label></td></tr>
        </table>
    </div>

    <div class="plseo-card">
        <h2><?php esc_html_e( 'robots.txt', 'polylang-seo' ); ?></h2>
        <textarea class="large-text" rows="8" name="plseo_robots_txt_additions"><?php echo esc_textarea( (string) PLSEO_Helpers::get_option( 'robots_txt_additions', '' ) ); ?></textarea>
    </div>
    <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'polylang-seo' ); ?></button></p>
</form>
</div>
