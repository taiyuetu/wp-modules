<?php
defined( 'ABSPATH' ) || exit;
include PLSEO_DIR . 'admin/views/header.php';
?>
<form method="post">
    <?php wp_nonce_field( 'plseo_settings' ); ?>
    <div class="plseo-card">
        <h2><?php esc_html_e( 'Head Cleanup', 'polylang-seo' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Remove RSD Link', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_remove_rsd_link" <?php checked( PLSEO_Helpers::get_option( 'remove_rsd_link', true ) ); ?> /> <?php esc_html_e( 'Remove the EditURI/RSD header link', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><?php esc_html_e( 'Remove Shortlink', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_remove_shortlink" <?php checked( PLSEO_Helpers::get_option( 'remove_shortlink', true ) ); ?> /> <?php esc_html_e( 'Remove wp_shortlink meta output', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><?php esc_html_e( 'Remove oEmbed Discovery', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_remove_oembed" <?php checked( PLSEO_Helpers::get_option( 'remove_oembed', false ) ); ?> /> <?php esc_html_e( 'Remove oEmbed discovery tags and host JS', 'polylang-seo' ); ?></label></td></tr>
        </table>
    </div>

    <div class="plseo-card">
        <h2><?php esc_html_e( 'Resource Hints', 'polylang-seo' ); ?></h2>
        <p class="description"><?php esc_html_e( 'One absolute URL per line.', 'polylang-seo' ); ?></p>
        <table class="form-table">
            <tr><th><label for="plseo_preconnect_domains"><?php esc_html_e( 'Preconnect Domains', 'polylang-seo' ); ?></label></th><td><textarea class="large-text" rows="5" id="plseo_preconnect_domains" name="plseo_preconnect_domains"><?php echo esc_textarea( (string) PLSEO_Helpers::get_option( 'preconnect_domains', '' ) ); ?></textarea></td></tr>
            <tr><th><label for="plseo_dns_prefetch"><?php esc_html_e( 'DNS Prefetch Domains', 'polylang-seo' ); ?></label></th><td><textarea class="large-text" rows="5" id="plseo_dns_prefetch" name="plseo_dns_prefetch"><?php echo esc_textarea( (string) PLSEO_Helpers::get_option( 'dns_prefetch', '' ) ); ?></textarea></td></tr>
        </table>
    </div>
    <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'polylang-seo' ); ?></button></p>
</form>
</div>
