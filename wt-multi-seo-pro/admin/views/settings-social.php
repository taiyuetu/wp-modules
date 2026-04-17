<?php
defined( 'ABSPATH' ) || exit;
include PLSEO_DIR . 'admin/views/header.php';
?>
<form method="post">
    <?php wp_nonce_field( 'plseo_settings' ); ?>
    <div class="plseo-card">
        <h2><?php esc_html_e( 'Open Graph', 'polylang-seo' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Enable Open Graph', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_og_enabled" <?php checked( PLSEO_Helpers::get_option( 'og_enabled', true ) ); ?> /> <?php esc_html_e( 'Output Open Graph meta tags', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><label for="plseo_og_default_image"><?php esc_html_e( 'Default OG Image', 'polylang-seo' ); ?></label></th><td><input type="url" class="large-text" id="plseo_og_default_image" name="plseo_og_default_image" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'og_default_image', '' ) ); ?>" /></td></tr>
            <tr><th><label for="plseo_fb_app_id"><?php esc_html_e( 'Facebook App ID', 'polylang-seo' ); ?></label></th><td><input type="text" class="regular-text" id="plseo_fb_app_id" name="plseo_fb_app_id" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'fb_app_id', '' ) ); ?>" /></td></tr>
            <tr><th><label for="plseo_fb_admins"><?php esc_html_e( 'Facebook Admin IDs', 'polylang-seo' ); ?></label></th><td><input type="text" class="regular-text" id="plseo_fb_admins" name="plseo_fb_admins" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'fb_admins', '' ) ); ?>" /></td></tr>
        </table>
    </div>

    <div class="plseo-card">
        <h2><?php esc_html_e( 'Twitter / X Cards', 'polylang-seo' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Enable Twitter Cards', 'polylang-seo' ); ?></th><td><label><input type="checkbox" name="plseo_twitter_enabled" <?php checked( PLSEO_Helpers::get_option( 'twitter_enabled', true ) ); ?> /> <?php esc_html_e( 'Output Twitter card tags', 'polylang-seo' ); ?></label></td></tr>
            <tr><th><label for="plseo_twitter_card_type"><?php esc_html_e( 'Card Type', 'polylang-seo' ); ?></label></th><td><select id="plseo_twitter_card_type" name="plseo_twitter_card_type"><option value="summary" <?php selected( PLSEO_Helpers::get_option( 'twitter_card_type', 'summary_large_image' ), 'summary' ); ?>>summary</option><option value="summary_large_image" <?php selected( PLSEO_Helpers::get_option( 'twitter_card_type', 'summary_large_image' ), 'summary_large_image' ); ?>>summary_large_image</option></select></td></tr>
            <tr><th><label for="plseo_twitter_site"><?php esc_html_e( 'Site Account', 'polylang-seo' ); ?></label></th><td><input type="text" class="regular-text" id="plseo_twitter_site" name="plseo_twitter_site" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'twitter_site', '' ) ); ?>" /></td></tr>
            <tr><th><label for="plseo_twitter_creator"><?php esc_html_e( 'Creator Account', 'polylang-seo' ); ?></label></th><td><input type="text" class="regular-text" id="plseo_twitter_creator" name="plseo_twitter_creator" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'twitter_creator', '' ) ); ?>" /></td></tr>
        </table>
    </div>
    <div class="plseo-card">
        <h2><?php esc_html_e( 'Social Profiles (Schema sameAs)', 'polylang-seo' ); ?></h2>
        <table class="form-table">
            <tr><th><label for="plseo_social_facebook"><?php esc_html_e( 'Facebook URL', 'polylang-seo' ); ?></label></th><td><input type="url" class="large-text" id="plseo_social_facebook" name="plseo_social_facebook" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'social_facebook', '' ) ); ?>" /></td></tr>
            <tr><th><label for="plseo_social_twitter"><?php esc_html_e( 'Twitter / X URL', 'polylang-seo' ); ?></label></th><td><input type="url" class="large-text" id="plseo_social_twitter" name="plseo_social_twitter" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'social_twitter', '' ) ); ?>" /></td></tr>
            <tr><th><label for="plseo_social_linkedin"><?php esc_html_e( 'LinkedIn URL', 'polylang-seo' ); ?></label></th><td><input type="url" class="large-text" id="plseo_social_linkedin" name="plseo_social_linkedin" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'social_linkedin', '' ) ); ?>" /></td></tr>
        </table>
    </div>
    <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'polylang-seo' ); ?></button></p>
</form>
</div>
