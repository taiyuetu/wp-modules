<?php
defined( 'ABSPATH' ) || exit;

class PLSEO_Metabox {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_meta' ], 20, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_metabox_assets' ] );
    }

    public function register_meta_boxes(): void {
        $enabled = (array) PLSEO_Helpers::get_option( 'enabled_post_types', [ 'post', 'page' ] );

        foreach ( $enabled as $post_type ) {
            add_meta_box(
                'plseo-seo-metabox',
                __( 'SEO Settings', 'polylang-seo' ),
                [ $this, 'render_metabox' ],
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public function render_metabox( WP_Post $post ): void {
        wp_nonce_field( 'plseo_save_meta_' . $post->ID, 'plseo_meta_nonce' );

        $translations = PLSEO_Helpers::get_post_translations( $post->ID );
        $languages    = PLSEO_Helpers::get_languages();
        $title        = get_post_meta( $post->ID, '_plseo_meta_title', true );
        $description  = get_post_meta( $post->ID, '_plseo_meta_description', true );
        $og_title     = get_post_meta( $post->ID, '_plseo_og_title', true );
        $og_desc      = get_post_meta( $post->ID, '_plseo_og_description', true );
        $og_image     = get_post_meta( $post->ID, '_plseo_og_image', true );
        $twitter_img  = get_post_meta( $post->ID, '_plseo_twitter_image', true );
        $canonical    = get_post_meta( $post->ID, '_plseo_canonical', true );
        $noindex      = get_post_meta( $post->ID, '_plseo_noindex', true );
        $nofollow     = get_post_meta( $post->ID, '_plseo_nofollow', true );
        $disable_seo  = get_post_meta( $post->ID, '_plseo_disable_seo', true );
        $defaults     = $this->get_default_meta_values( $post );

        if ( '' === trim( (string) $title ) ) {
            $title = $defaults['title'];
        }

        if ( '' === trim( (string) $description ) ) {
            $description = $defaults['description'];
        }

        if ( '' === trim( (string) $canonical ) ) {
            $canonical = $defaults['canonical'];
        }

        if ( '' === trim( (string) $og_title ) ) {
            $og_title = $title;
        }

        if ( '' === trim( (string) $og_desc ) ) {
            $og_desc = $description;
        }

        if ( '' === trim( (string) $og_image ) ) {
            $og_image = $defaults['image'];
        }

        if ( '' === trim( (string) $twitter_img ) ) {
            $twitter_img = $defaults['image'];
        }

        include PLSEO_DIR . 'admin/views/metabox.php';
    }

    private function get_default_meta_values( WP_Post $post ): array {
        $title       = get_the_title( $post );
        $content     = wp_strip_all_tags( (string) $post->post_content );
        $description = wp_trim_words( $content, 25, '...' );
        $canonical   = get_permalink( $post );
        $image       = get_the_post_thumbnail_url( $post, 'full' );

        return [
            'title'       => is_string( $title ) ? $title : '',
            'description' => is_string( $description ) ? $description : '',
            'canonical'   => is_string( $canonical ) ? $canonical : '',
            'image'       => is_string( $image ) ? $image : '',
        ];
    }

    public function save_meta( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST['plseo_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plseo_meta_nonce'] ) ), 'plseo_save_meta_' . $post_id ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        foreach ( [ '_plseo_meta_title', '_plseo_og_title' ] as $field ) {
            update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ?? '' ) ) );
        }

        foreach ( [ '_plseo_meta_description', '_plseo_og_description' ] as $field ) {
            update_post_meta( $post_id, $field, sanitize_textarea_field( wp_unslash( $_POST[ $field ] ?? '' ) ) );
        }

        foreach ( [ '_plseo_og_image', '_plseo_twitter_image', '_plseo_canonical' ] as $field ) {
            update_post_meta( $post_id, $field, esc_url_raw( wp_unslash( $_POST[ $field ] ?? '' ) ) );
        }

        foreach ( [ '_plseo_noindex', '_plseo_nofollow', '_plseo_disable_seo' ] as $field ) {
            update_post_meta( $post_id, $field, isset( $_POST[ $field ] ) ? '1' : '' );
        }
    }

    public function enqueue_metabox_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        wp_enqueue_style( 'plseo-metabox', PLSEO_URL . 'assets/css/metabox.css', [], PLSEO_VERSION );
        wp_enqueue_script( 'plseo-metabox', PLSEO_URL . 'assets/js/metabox.js', [ 'jquery' ], PLSEO_VERSION, true );
        wp_enqueue_media();
    }
}
