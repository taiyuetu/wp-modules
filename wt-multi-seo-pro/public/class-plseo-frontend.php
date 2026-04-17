<?php
defined( 'ABSPATH' ) || exit;

/**
 * Frontend cleanups and UX improvements.
 */
class PLSEO_Frontend {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Head cleanup
        if ( PLSEO_Helpers::get_option( 'remove_rsd_link', true ) ) {
            remove_action( 'wp_head', 'rsd_link' );
        }
        if ( PLSEO_Helpers::get_option( 'remove_shortlink', true ) ) {
            remove_action( 'wp_head', 'wp_shortlink_wp_head' );
        }
        if ( PLSEO_Helpers::get_option( 'remove_oembed', false ) ) {
            remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
            remove_action( 'wp_head', 'wp_oembed_add_host_js' );
        }

        // Add lang attribute helper (Polylang already handles <html lang>, this reinforces for themes)
        add_filter( 'language_attributes', [ $this, 'add_og_namespace' ] );

        // Pagination rel links
        add_action( 'wp_head', [ $this, 'output_pagination_links' ], 3 );
    }

    /**
     * Add OG namespace to <html> tag.
     */
    public function add_og_namespace( string $output ): string {
        if ( PLSEO_Helpers::get_option( 'og_enabled', true ) ) {
            $output .= ' prefix="og: https://ogp.me/ns#"';
        }
        return $output;
    }

    /**
     * Output rel=prev / rel=next for paginated archives.
     */
    public function output_pagination_links(): void {
        global $wp_query;

        $paged      = (int) get_query_var( 'paged' );
        $max_pages  = (int) $wp_query->max_num_pages;

        if ( $paged > 1 ) {
            $prev_url = get_pagenum_link( $paged - 1 );
            printf( '<link rel="prev" href="%s" />' . "\n", esc_url( $prev_url ) );
        }

        if ( $paged && $paged < $max_pages ) {
            $next_url = get_pagenum_link( $paged + 1 );
            printf( '<link rel="next" href="%s" />' . "\n", esc_url( $next_url ) );
        }

        if ( ! $paged && $max_pages > 1 ) {
            $next_url = get_pagenum_link( 2 );
            printf( '<link rel="next" href="%s" />' . "\n", esc_url( $next_url ) );
        }
    }
}
