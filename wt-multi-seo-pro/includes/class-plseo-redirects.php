<?php
defined( 'ABSPATH' ) || exit;

class PLSEO_Redirects {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        if ( PLSEO_Helpers::get_option( 'redirect_old_slugs', true ) ) {
            add_filter( 'redirect_canonical', [ $this, 'handle_old_slug_redirect' ], 10, 2 );
        }

        if ( PLSEO_Helpers::get_option( 'redirect_404_home', false ) ) {
            add_action( 'template_redirect', [ $this, 'redirect_404_to_home' ] );
        }
    }

    public function handle_old_slug_redirect( $redirect_url, string $requested_url ) {
        if ( $redirect_url || ! is_404() || is_admin() || wp_doing_ajax() ) {
            return $redirect_url;
        }

        $parts = wp_parse_url( $requested_url );
        if ( empty( $parts['path'] ) ) {
            return $redirect_url;
        }

        $slug = sanitize_title( basename( trim( (string) $parts['path'], '/' ) ) );
        if ( '' === $slug ) {
            return $redirect_url;
        }

        global $wpdb;
        $post_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_wp_old_slug'
                   AND pm.meta_value = %s
                   AND p.post_status = 'publish'
                 ORDER BY p.post_date DESC
                 LIMIT 1",
                $slug
            )
        );

        if ( $post_id <= 0 ) {
            return $redirect_url;
        }

        $permalink = get_permalink( $post_id );
        if ( ! $permalink ) {
            return $redirect_url;
        }

        return $permalink;
    }

    public function redirect_404_to_home(): void {
        if ( is_404() ) {
            wp_safe_redirect( home_url( '/' ), 301 );
            exit;
        }
    }
}
