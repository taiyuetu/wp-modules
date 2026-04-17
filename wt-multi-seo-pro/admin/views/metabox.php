<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="plseo-metabox-grid">
    <section class="plseo-card">
        <div class="plseo-card-header">
            <h3><?php esc_html_e( 'Search Preview', 'polylang-seo' ); ?></h3>
            <p><?php esc_html_e( 'Control how this post appears in search engines.', 'polylang-seo' ); ?></p>
        </div>

        <div class="plseo-card-body">
            <div class="plseo-field">
                <label for="_plseo_meta_title"><?php esc_html_e( 'SEO Title', 'polylang-seo' ); ?></label>
                <input type="text" class="widefat" id="_plseo_meta_title" name="_plseo_meta_title" value="<?php echo esc_attr( $title ); ?>" maxlength="70" />
            </div>

            <div class="plseo-field">
                <label for="_plseo_meta_description"><?php esc_html_e( 'Meta Description', 'polylang-seo' ); ?></label>
                <textarea class="widefat" id="_plseo_meta_description" name="_plseo_meta_description" rows="4" maxlength="160"><?php echo esc_textarea( $description ); ?></textarea>
            </div>

            <div class="plseo-field">
                <label for="_plseo_canonical"><?php esc_html_e( 'Canonical URL', 'polylang-seo' ); ?></label>
                <input type="url" class="widefat" id="_plseo_canonical" name="_plseo_canonical" value="<?php echo esc_attr( $canonical ); ?>" />
            </div>
        </div>
    </section>

    <section class="plseo-card">
        <div class="plseo-card-header">
            <h3><?php esc_html_e( 'Social Sharing', 'polylang-seo' ); ?></h3>
            <p><?php esc_html_e( 'Customize Open Graph and Twitter metadata.', 'polylang-seo' ); ?></p>
        </div>

        <div class="plseo-card-body plseo-two-col">
            <div class="plseo-field">
                <label for="_plseo_og_title"><?php esc_html_e( 'Open Graph Title', 'polylang-seo' ); ?></label>
                <input type="text" class="widefat" id="_plseo_og_title" name="_plseo_og_title" value="<?php echo esc_attr( $og_title ); ?>" />
            </div>

            <div class="plseo-field">
                <label for="_plseo_og_image"><?php esc_html_e( 'Open Graph Image URL', 'polylang-seo' ); ?></label>
                <input type="url" class="widefat" id="_plseo_og_image" name="_plseo_og_image" value="<?php echo esc_attr( $og_image ); ?>" />
            </div>

            <div class="plseo-field">
                <label for="_plseo_og_description"><?php esc_html_e( 'Open Graph Description', 'polylang-seo' ); ?></label>
                <textarea class="widefat" id="_plseo_og_description" name="_plseo_og_description" rows="3"><?php echo esc_textarea( $og_desc ); ?></textarea>
            </div>

            <div class="plseo-field">
                <label for="_plseo_twitter_image"><?php esc_html_e( 'Twitter Image URL', 'polylang-seo' ); ?></label>
                <input type="url" class="widefat" id="_plseo_twitter_image" name="_plseo_twitter_image" value="<?php echo esc_attr( $twitter_img ); ?>" />
            </div>
        </div>
    </section>

    <section class="plseo-card">
        <div class="plseo-card-header">
            <h3><?php esc_html_e( 'Advanced Controls', 'polylang-seo' ); ?></h3>
            <p><?php esc_html_e( 'Fine-tune indexing and output behavior for this post.', 'polylang-seo' ); ?></p>
        </div>

        <div class="plseo-card-body">
            <div class="plseo-field-inline">
                <label><input type="checkbox" name="_plseo_noindex" <?php checked( $noindex, '1' ); ?> /> <?php esc_html_e( 'Noindex', 'polylang-seo' ); ?></label>
                <label><input type="checkbox" name="_plseo_nofollow" <?php checked( $nofollow, '1' ); ?> /> <?php esc_html_e( 'Nofollow', 'polylang-seo' ); ?></label>
                <label><input type="checkbox" name="_plseo_disable_seo" <?php checked( $disable_seo, '1' ); ?> /> <?php esc_html_e( 'Disable plugin SEO output for this item', 'polylang-seo' ); ?></label>
            </div>
        </div>
    </section>

    <section class="plseo-card">
        <div class="plseo-card-header">
            <h3><?php esc_html_e( 'Translations', 'polylang-seo' ); ?></h3>
            <p><?php esc_html_e( 'Jump to language variants and edit SEO metadata per translation.', 'polylang-seo' ); ?></p>
        </div>

        <div class="plseo-card-body">
            <?php if ( empty( $translations ) ) : ?>
                <p class="plseo-note"><?php esc_html_e( 'No Polylang translations found for this item yet.', 'polylang-seo' ); ?></p>
            <?php else : ?>
                <ul class="plseo-translation-list">
                    <?php foreach ( $translations as $slug => $translated_post_id ) : ?>
                        <li>
                            <?php
                            $label = $languages[ $slug ]['name'] ?? strtoupper( $slug );
                            $link  = get_edit_post_link( (int) $translated_post_id );
                            ?>
                            <a href="<?php echo esc_url( $link ?: '' ); ?>"><?php echo esc_html( $label ); ?></a>
                            <?php if ( (int) $translated_post_id === (int) $post->ID ) : ?>
                                <span class="plseo-current"><?php esc_html_e( 'Current', 'polylang-seo' ); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>
