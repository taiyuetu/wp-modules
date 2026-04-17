<?php
defined( 'ABSPATH' ) || exit;
include PLSEO_DIR . 'admin/views/header.php';

$separator_options = [
    '-'  => '-',
    '|'  => '|',
    '•'  => '•',
    '>'  => '>',
    '»'  => '»',
];
?>
<form method="post">
    <?php wp_nonce_field( 'plseo_settings' ); ?>

    <div class="plseo-card">
        <h2><?php esc_html_e( 'Title Templates', 'polylang-seo' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Tokens: %%title%%, %%sitename%%, %%tagline%%, %%sep%%, %%page%%, %%currentyear%%, %%searchterm%%', 'polylang-seo' ); ?></p>
        <table class="form-table">
            <tr>
                <th><label for="plseo_title_separator"><?php esc_html_e( 'Title Separator', 'polylang-seo' ); ?></label></th>
                <td>
                    <select id="plseo_title_separator" name="plseo_title_separator">
                        <?php foreach ( $separator_options as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( PLSEO_Helpers::get_option( 'title_separator', '-' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php
            $fields = [
                'homepage_title'       => __( 'Homepage Title', 'polylang-seo' ),
                'homepage_description' => __( 'Homepage Description', 'polylang-seo' ),
                'title_format_single'  => __( 'Single Title Template', 'polylang-seo' ),
                'title_format_archive' => __( 'Archive Title Template', 'polylang-seo' ),
                'title_format_search'  => __( 'Search Title Template', 'polylang-seo' ),
                'title_format_404'     => __( '404 Title Template', 'polylang-seo' ),
            ];
            foreach ( $fields as $key => $label ) :
                $value = PLSEO_Helpers::get_option( $key, '' );
                ?>
                <tr>
                    <th><label for="plseo_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                    <td>
                        <?php if ( 'homepage_description' === $key ) : ?>
                            <textarea class="large-text" rows="3" id="plseo_<?php echo esc_attr( $key ); ?>" name="plseo_<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
                        <?php else : ?>
                            <input class="large-text" type="text" id="plseo_<?php echo esc_attr( $key ); ?>" name="plseo_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="plseo-card">
        <h2><?php esc_html_e( 'SEO Metabox Coverage', 'polylang-seo' ); ?></h2>
        <div class="plseo-grid-two">
            <div>
                <h3><?php esc_html_e( 'Post Types', 'polylang-seo' ); ?></h3>
                <?php foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $post_type ) : ?>
                    <?php $enabled = (array) PLSEO_Helpers::get_option( 'enabled_post_types', [ 'post', 'page' ] ); ?>
                    <label class="plseo-check">
                        <input type="checkbox" name="plseo_enabled_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $enabled, true ) ); ?> />
                        <span><?php echo esc_html( $post_type->label ); ?> <code><?php echo esc_html( $post_type->name ); ?></code></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div>
                <h3><?php esc_html_e( 'Taxonomies', 'polylang-seo' ); ?></h3>
                <?php foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $taxonomy ) : ?>
                    <?php $enabled = (array) PLSEO_Helpers::get_option( 'enabled_taxonomies', [ 'category', 'post_tag' ] ); ?>
                    <label class="plseo-check">
                        <input type="checkbox" name="plseo_enabled_taxonomies[]" value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php checked( in_array( $taxonomy->name, $enabled, true ) ); ?> />
                        <span><?php echo esc_html( $taxonomy->label ); ?> <code><?php echo esc_html( $taxonomy->name ); ?></code></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="plseo-card">
        <h2><?php esc_html_e( 'Custom Post Type Archive SEO', 'polylang-seo' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Use these fields for archive pages of custom post types that do not have a dedicated editor page.', 'polylang-seo' ); ?></p>
        <?php
        $archive_types = get_post_types(
            [
                'public'      => true,
                'has_archive' => true,
                '_builtin'    => false,
            ],
            'objects'
        );
        $archive_meta = PLSEO_Helpers::get_option( 'cpt_archive_seo', [] );
        ?>
        <?php if ( empty( $archive_types ) ) : ?>
            <p><?php esc_html_e( 'No public custom post type archives found.', 'polylang-seo' ); ?></p>
        <?php else : ?>
            <?php foreach ( $archive_types as $post_type ) : ?>
                <?php $values = is_array( $archive_meta[ $post_type->name ] ?? null ) ? $archive_meta[ $post_type->name ] : []; ?>
                <div class="plseo-cpt-archive-block">
                    <h3>
                        <?php echo esc_html( $post_type->label ); ?>
                        <code><?php echo esc_html( $post_type->name ); ?></code>
                    </h3>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Inherit Defaults', 'polylang-seo' ); ?></th>
                            <td>
                                <?php $inherit_defaults = ! isset( $values['inherit_defaults'] ) || '1' === (string) $values['inherit_defaults']; ?>
                                <label class="plseo-check">
                                    <input type="checkbox" id="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_inherit_defaults" name="plseo_cpt_archive_seo[<?php echo esc_attr( $post_type->name ); ?>][inherit_defaults]" value="1" <?php checked( $inherit_defaults ); ?> />
                                    <span><?php esc_html_e( 'If OG/Twitter fields are empty, use SEO title/description/image values automatically.', 'polylang-seo' ); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_title"><?php esc_html_e( 'SEO Title', 'polylang-seo' ); ?></label></th>
                            <td><input class="large-text" type="text" id="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_title" name="plseo_cpt_archive_seo[<?php echo esc_attr( $post_type->name ); ?>][title]" value="<?php echo esc_attr( $values['title'] ?? '' ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_description"><?php esc_html_e( 'Meta Description', 'polylang-seo' ); ?></label></th>
                            <td><textarea class="large-text" rows="3" id="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_description" name="plseo_cpt_archive_seo[<?php echo esc_attr( $post_type->name ); ?>][description]"><?php echo esc_textarea( $values['description'] ?? '' ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_canonical"><?php esc_html_e( 'Canonical URL', 'polylang-seo' ); ?></label></th>
                            <td><input class="large-text" type="url" id="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_canonical" name="plseo_cpt_archive_seo[<?php echo esc_attr( $post_type->name ); ?>][canonical]" value="<?php echo esc_attr( $values['canonical'] ?? '' ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_og_title"><?php esc_html_e( 'Open Graph Title', 'polylang-seo' ); ?></label></th>
                            <td><input class="large-text" type="text" id="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_og_title" name="plseo_cpt_archive_seo[<?php echo esc_attr( $post_type->name ); ?>][og_title]" value="<?php echo esc_attr( $values['og_title'] ?? '' ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_og_description"><?php esc_html_e( 'Open Graph Description', 'polylang-seo' ); ?></label></th>
                            <td><textarea class="large-text" rows="3" id="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_og_description" name="plseo_cpt_archive_seo[<?php echo esc_attr( $post_type->name ); ?>][og_description]"><?php echo esc_textarea( $values['og_description'] ?? '' ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_og_image"><?php esc_html_e( 'Open Graph Image URL', 'polylang-seo' ); ?></label></th>
                            <td><input class="large-text" type="url" id="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_og_image" name="plseo_cpt_archive_seo[<?php echo esc_attr( $post_type->name ); ?>][og_image]" value="<?php echo esc_attr( $values['og_image'] ?? '' ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_twitter_image"><?php esc_html_e( 'Twitter Image URL', 'polylang-seo' ); ?></label></th>
                            <td><input class="large-text" type="url" id="plseo_cpt_archive_seo_<?php echo esc_attr( $post_type->name ); ?>_twitter_image" name="plseo_cpt_archive_seo[<?php echo esc_attr( $post_type->name ); ?>][twitter_image]" value="<?php echo esc_attr( $values['twitter_image'] ?? '' ); ?>" /></td>
                        </tr>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'polylang-seo' ); ?></button></p>
</form>
</div>
