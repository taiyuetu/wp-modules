<?php
defined( 'ABSPATH' ) || exit;
include PLSEO_DIR . 'admin/views/header.php';
?>
<form method="post">
    <?php wp_nonce_field( 'plseo_settings' ); ?>
    <div class="plseo-card">
        <h2><?php esc_html_e( 'Structured Data', 'polylang-seo' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Enable Schema', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_schema_enabled" <?php checked( PLSEO_Helpers::get_option( 'schema_enabled', true ) ); ?> /> <?php esc_html_e( 'Output JSON-LD schema data', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><label for="plseo_schema_org_type"><?php esc_html_e( 'Organization Type', 'polylang-seo' ); ?></label></th><td><input type="text" class="regular-text" id="plseo_schema_org_type" name="plseo_schema_org_type" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'schema_org_type', 'Organization' ) ); ?>" /></td></tr>
            <tr><th><label for="plseo_schema_org_name"><?php esc_html_e( 'Organization Name', 'polylang-seo' ); ?></label></th><td><input type="text" class="regular-text" id="plseo_schema_org_name" name="plseo_schema_org_name" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'schema_org_name', '' ) ); ?>" /></td></tr>
            <tr><th><label for="plseo_schema_org_url"><?php esc_html_e( 'Organization URL', 'polylang-seo' ); ?></label></th><td><input type="url" class="large-text" id="plseo_schema_org_url" name="plseo_schema_org_url" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'schema_org_url', '' ) ); ?>" /></td></tr>
            <tr><th><label for="plseo_schema_org_logo"><?php esc_html_e( 'Logo URL', 'polylang-seo' ); ?></label></th><td><input type="url" class="large-text" id="plseo_schema_org_logo" name="plseo_schema_org_logo" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'schema_org_logo', '' ) ); ?>" /></td></tr>
            <tr><th><?php esc_html_e( 'Breadcrumb Schema', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_schema_breadcrumb" <?php checked( PLSEO_Helpers::get_option( 'schema_breadcrumb', true ) ); ?> /> <?php esc_html_e( 'Output BreadcrumbList schema', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><?php esc_html_e( 'Article Schema', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_schema_article" <?php checked( PLSEO_Helpers::get_option( 'schema_article', true ) ); ?> /> <?php esc_html_e( 'Output Article schema on posts', 'polylang-seo' ); ?></label></td></tr>
        </table>
    </div>

    <div class="plseo-card">
        <h2><?php esc_html_e( 'Local Business', 'polylang-seo' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Enable Local Business', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_schema_local_business" <?php checked( PLSEO_Helpers::get_option( 'schema_local_business', false ) ); ?> /> <?php esc_html_e( 'Output LocalBusiness schema', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><label for="plseo_schema_lb_type"><?php esc_html_e( 'Business Type', 'polylang-seo' ); ?></label></th><td><input type="text" class="regular-text" id="plseo_schema_lb_type" name="plseo_schema_lb_type" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'schema_lb_type', 'LocalBusiness' ) ); ?>" /></td></tr>
            <tr><th><label for="plseo_schema_lb_address"><?php esc_html_e( 'Address', 'polylang-seo' ); ?></label></th><td><textarea class="large-text" rows="3" id="plseo_schema_lb_address" name="plseo_schema_lb_address"><?php echo esc_textarea( (string) PLSEO_Helpers::get_option( 'schema_lb_address', '' ) ); ?></textarea></td></tr>
            <tr><th><label for="plseo_schema_lb_phone"><?php esc_html_e( 'Phone', 'polylang-seo' ); ?></label></th><td><input type="text" class="regular-text" id="plseo_schema_lb_phone" name="plseo_schema_lb_phone" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'schema_lb_phone', '' ) ); ?>" /></td></tr>
            <tr><th><label for="plseo_schema_lb_hours"><?php esc_html_e( 'Opening Hours', 'polylang-seo' ); ?></label></th><td><input type="text" class="regular-text" id="plseo_schema_lb_hours" name="plseo_schema_lb_hours" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'schema_lb_hours', '' ) ); ?>" /></td></tr>
        </table>
    </div>
    <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'polylang-seo' ); ?></button></p>
</form>
</div>
