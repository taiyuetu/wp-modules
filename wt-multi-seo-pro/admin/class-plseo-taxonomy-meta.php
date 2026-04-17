<?php
defined( 'ABSPATH' ) || exit;

/**
 * Adds SEO fields to taxonomy term edit screens (categories, tags, custom taxonomies).
 * Works with all Polylang language versions of each term.
 */
class PLSEO_Taxonomy_Meta {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $taxonomies = (array) PLSEO_Helpers::get_option( 'enabled_taxonomies', [ 'category', 'post_tag' ] );

        // Also register for all public taxonomies
        $all = get_taxonomies( [ 'public' => true ], 'names' );
        $taxonomies = array_unique( array_merge( $taxonomies, array_values( $all ) ) );

        foreach ( $taxonomies as $taxonomy ) {
            add_action( "{$taxonomy}_add_form_fields",  [ $this, 'add_fields' ] );
            add_action( "{$taxonomy}_edit_form_fields", [ $this, 'edit_fields' ] );
            add_action( "edited_{$taxonomy}",           [ $this, 'save_fields' ] );
            add_action( "created_{$taxonomy}",          [ $this, 'save_fields' ] );
        }
    }

    public function add_fields( string $taxonomy ): void {
        wp_nonce_field( 'plseo_taxonomy_meta', 'plseo_taxonomy_nonce' );
        ?>
        <div class="form-field plseo-tax-field">
            <h3 style="border-bottom:1px solid #ddd;padding-bottom:8px;">🔍 <?php esc_html_e( 'SEO Settings', 'polylang-seo' ); ?></h3>
            <div class="form-field">
                <label for="plseo_meta_title"><?php esc_html_e( 'SEO Title', 'polylang-seo' ); ?></label>
                <input type="text" name="_plseo_meta_title" id="plseo_meta_title" maxlength="70" style="width:100%" />
                <p class="description"><?php esc_html_e( 'Leave empty to use the term name. Max 70 characters.', 'polylang-seo' ); ?></p>
            </div>
            <div class="form-field">
                <label for="plseo_meta_description"><?php esc_html_e( 'Meta Description', 'polylang-seo' ); ?></label>
                <textarea name="_plseo_meta_description" id="plseo_meta_description" rows="3" maxlength="160" style="width:100%"></textarea>
                <p class="description"><?php esc_html_e( 'Max 160 characters.', 'polylang-seo' ); ?></p>
            </div>
        </div>
        <?php
    }

    public function edit_fields( WP_Term $term ): void {
        wp_nonce_field( 'plseo_taxonomy_meta', 'plseo_taxonomy_nonce' );
        $title = get_term_meta( $term->term_id, '_plseo_meta_title', true );
        $desc  = get_term_meta( $term->term_id, '_plseo_meta_description', true );
        $lang  = function_exists( 'pll_get_term_language' ) ? pll_get_term_language( $term->term_id ) : '';
        ?>
        <tr class="form-field plseo-tax-field">
            <th colspan="2">
                <h3 style="margin:0;border-bottom:1px solid #ddd;padding-bottom:8px;">
                    🔍 <?php esc_html_e( 'SEO Settings', 'polylang-seo' ); ?>
                    <?php if ( $lang ) : ?>
                        <span style="font-weight:normal;color:#666;font-size:13px;">
                            (<?php echo esc_html( strtoupper( $lang ) ); ?>)
                        </span>
                    <?php endif; ?>
                </h3>
            </th>
        </tr>
        <tr class="form-field">
            <th scope="row">
                <label for="plseo_meta_title"><?php esc_html_e( 'SEO Title', 'polylang-seo' ); ?></label>
            </th>
            <td>
                <input type="text" name="_plseo_meta_title" id="plseo_meta_title"
                    value="<?php echo esc_attr( $title ); ?>" maxlength="70" style="width:100%" />
                <p class="description"><?php esc_html_e( 'Leave empty to use the term name. Max 70 characters.', 'polylang-seo' ); ?></p>
                <div class="plseo-char-counter" data-target="plseo_meta_title" data-max="70"></div>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row">
                <label for="plseo_meta_description"><?php esc_html_e( 'Meta Description', 'polylang-seo' ); ?></label>
            </th>
            <td>
                <textarea name="_plseo_meta_description" id="plseo_meta_description"
                    rows="3" maxlength="160" style="width:100%"><?php echo esc_textarea( $desc ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Max 160 characters.', 'polylang-seo' ); ?></p>
                <div class="plseo-char-counter" data-target="plseo_meta_description" data-max="160"></div>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e( 'Translations', 'polylang-seo' ); ?></th>
            <td>
                <?php $this->render_translation_links( $term ); ?>
            </td>
        </tr>
        <?php
    }

    private function render_translation_links( WP_Term $term ): void {
        $translations = PLSEO_Helpers::get_term_translations( $term->term_id );
        $langs        = PLSEO_Helpers::get_languages();

        if ( empty( $translations ) ) {
            echo '<em>' . esc_html__( 'No translations found.', 'polylang-seo' ) . '</em>';
            return;
        }

        echo '<ul style="margin:0;">';
        foreach ( $translations as $slug => $tid ) {
            if ( ! isset( $langs[ $slug ] ) ) { continue; }
            $edit_link = get_edit_term_link( (int) $tid, $term->taxonomy );
            $label     = $langs[ $slug ]['name'];
            $active    = ( $tid === $term->term_id ) ? ' <strong>(' . esc_html__( 'current', 'polylang-seo' ) . ')</strong>' : '';
            printf(
                '<li><a href="%s">%s</a>%s</li>',
                esc_url( $edit_link ),
                esc_html( $label ),
                $active
            );
        }
        echo '</ul>';
    }

    public function save_fields( int $term_id ): void {
        if ( ! isset( $_POST['plseo_taxonomy_nonce'] ) ||
             ! wp_verify_nonce( sanitize_key( $_POST['plseo_taxonomy_nonce'] ), 'plseo_taxonomy_meta' ) ) {
            return;
        }

        if ( isset( $_POST['_plseo_meta_title'] ) ) {
            update_term_meta( $term_id, '_plseo_meta_title', sanitize_text_field( wp_unslash( $_POST['_plseo_meta_title'] ) ) );
        }

        if ( isset( $_POST['_plseo_meta_description'] ) ) {
            update_term_meta( $term_id, '_plseo_meta_description', sanitize_text_field( wp_unslash( $_POST['_plseo_meta_description'] ) ) );
        }
    }
}
