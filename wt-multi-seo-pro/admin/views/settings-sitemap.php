<?php
defined( 'ABSPATH' ) || exit;
include PLSEO_DIR . 'admin/views/header.php';
?>
<form method="post">
    <?php wp_nonce_field( 'plseo_settings' ); ?>

    <div class="plseo-card">
        <h2><?php esc_html_e( 'XML Sitemap', 'polylang-seo' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Enable Sitemap', 'polylang-seo' ); ?></th>
                <td><label><input type="checkbox" name="plseo_sitemap_enabled" <?php checked( PLSEO_Helpers::get_option( 'sitemap_enabled', true ) ); ?> /> <?php esc_html_e( 'Serve sitemap index at /sitemap.xml', 'polylang-seo' ); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Include Posts', 'polylang-seo' ); ?></th>
                <td><label><input type="checkbox" name="plseo_sitemap_include_posts" <?php checked( PLSEO_Helpers::get_option( 'sitemap_include_posts', true ) ); ?> /> <?php esc_html_e( 'Include blog posts', 'polylang-seo' ); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Include Pages', 'polylang-seo' ); ?></th>
                <td><label><input type="checkbox" name="plseo_sitemap_include_pages" <?php checked( PLSEO_Helpers::get_option( 'sitemap_include_pages', true ) ); ?> /> <?php esc_html_e( 'Include pages', 'polylang-seo' ); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Include Taxonomies', 'polylang-seo' ); ?></th>
                <td><label><input type="checkbox" name="plseo_sitemap_include_taxonomies" <?php checked( PLSEO_Helpers::get_option( 'sitemap_include_taxonomies', true ) ); ?> /> <?php esc_html_e( 'Include taxonomy archives', 'polylang-seo' ); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Include Images', 'polylang-seo' ); ?></th>
                <td><label><input type="checkbox" name="plseo_sitemap_images" <?php checked( PLSEO_Helpers::get_option( 'sitemap_images', true ) ); ?> /> <?php esc_html_e( 'Add image sitemap entries for featured and content images', 'polylang-seo' ); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Custom Post Types', 'polylang-seo' ); ?></th>
                <td>
                    <?php $enabled_cpts = (array) PLSEO_Helpers::get_option( 'sitemap_include_cpt', [] ); ?>
                    <?php foreach ( get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' ) as $post_type ) : ?>
                        <label class="plseo-check">
                            <input type="checkbox" name="plseo_sitemap_include_cpt[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $enabled_cpts, true ) ); ?> />
                            <span><?php echo esc_html( $post_type->label ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="plseo-card">
        <h2><?php esc_html_e( 'Multilingual SEO', 'polylang-seo' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'hreflang Links', 'polylang-seo' ); ?></th>
                <td><label><input type="checkbox" name="plseo_sitemap_hreflang" <?php checked( PLSEO_Helpers::get_option( 'sitemap_hreflang', true ) ); ?> /> <?php esc_html_e( 'Add xhtml:link hreflang annotations in sitemap entries', 'polylang-seo' ); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'x-default', 'polylang-seo' ); ?></th>
                <td><label><input type="checkbox" name="plseo_sitemap_x_default" <?php checked( PLSEO_Helpers::get_option( 'sitemap_x_default', true ) ); ?> /> <?php esc_html_e( 'Add x-default for the default Polylang language', 'polylang-seo' ); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Detected Languages', 'polylang-seo' ); ?></th>
                <td>
                    <?php foreach ( PLSEO_Helpers::get_languages() as $slug => $language ) : ?>
                        <div><code><?php echo esc_html( $slug ); ?></code> <?php echo esc_html( $language['locale'] ); ?> -> <code><?php echo esc_html( PLSEO_Helpers::locale_to_hreflang( $language['locale'] ) ); ?></code></div>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="plseo-card">
        <h2><?php esc_html_e( 'Search Engine Notifications', 'polylang-seo' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Ping Google', 'polylang-seo' ); ?></th>
                <td><label><input type="checkbox" name="plseo_sitemap_ping_google" <?php checked( PLSEO_Helpers::get_option( 'sitemap_ping_google', true ) ); ?> /> <?php esc_html_e( 'Notify Google after publishing content', 'polylang-seo' ); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Ping Bing', 'polylang-seo' ); ?></th>
                <td><label><input type="checkbox" name="plseo_sitemap_ping_bing" <?php checked( PLSEO_Helpers::get_option( 'sitemap_ping_bing', true ) ); ?> /> <?php esc_html_e( 'Notify Bing after publishing content', 'polylang-seo' ); ?></label></td>
            </tr>
            <tr>
                <th><label for="plseo_sitemap_posts_per_type"><?php esc_html_e( 'URLs Per Sitemap', 'polylang-seo' ); ?></label></th>
                <td><input type="number" id="plseo_sitemap_posts_per_type" name="plseo_sitemap_posts_per_type" min="50" max="50000" value="<?php echo esc_attr( (string) PLSEO_Helpers::get_option( 'sitemap_posts_per_type', 1000 ) ); ?>" /></td>
            </tr>
            <tr>
                <th><label for="plseo_sitemap_exclude_ids"><?php esc_html_e( 'Exclude Post IDs', 'polylang-seo' ); ?></label></th>
                <td><input type="text" class="large-text" id="plseo_sitemap_exclude_ids" name="plseo_sitemap_exclude_ids" value="<?php echo esc_attr( implode( ', ', (array) PLSEO_Helpers::get_option( 'sitemap_exclude_ids', [] ) ) ); ?>" /></td>
            </tr>
        </table>
    </div>

    <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'polylang-seo' ); ?></button></p>
</form>
</div>
