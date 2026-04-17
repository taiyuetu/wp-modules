<?php
defined( 'ABSPATH' ) || exit;

class PLSEO_Canonical {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        if ( ! PLSEO_Helpers::get_option( 'canonical_enabled', true ) ) {
            return;
        }

        remove_action( 'wp_head', 'rel_canonical' );
        add_action( 'wp_head', [ $this, 'output_canonical' ], 4 );
    }

    public function output_canonical(): void {
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            if ( $post_id && PLSEO_Helpers::is_seo_disabled( $post_id ) ) {
                return;
            }
        }

        $url = $this->get_canonical_url();

        if ( $url ) {
            printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $url ) );
        }
    }

    public function get_canonical_url(): string {
        if ( is_singular() ) {
            $post_id  = get_queried_object_id();
            $override = PLSEO_Helpers::get_post_seo_meta( $post_id, 'canonical', false );

            return $this->normalise( $override ?: (string) get_permalink( $post_id ) );
        }

        if ( is_front_page() ) {
            return $this->normalise( home_url( '/' ) );
        }

        if ( is_home() ) {
            $page_for_posts = (int) get_option( 'page_for_posts' );
            return $page_for_posts ? $this->normalise( get_permalink( $page_for_posts ) ) : $this->normalise( home_url( '/' ) );
        }

        if ( is_tax() || is_category() || is_tag() ) {
            $term_link = get_term_link( get_queried_object() );
            return is_wp_error( $term_link ) ? '' : $this->normalise( $term_link );
        }

        if ( is_post_type_archive() ) {
            $post_type = get_query_var( 'post_type' );
            if ( is_array( $post_type ) ) {
                $post_type = reset( $post_type );
            }

            if ( is_string( $post_type ) ) {
                $archive_canonical = PLSEO_Helpers::get_cpt_archive_seo_meta( $post_type, 'canonical' );
                if ( $archive_canonical ) {
                    return $this->normalise( $archive_canonical );
                }
            }

            $archive_link = $post_type ? get_post_type_archive_link( $post_type ) : '';
            return $archive_link ? $this->normalise( $archive_link ) : '';
        }

        if ( is_archive() ) {
            return $this->normalise( PLSEO_Helpers::get_current_url() );
        }

        return $this->normalise( PLSEO_Helpers::get_current_url() );
    }

    private function normalise( string $url ): string {
        if ( '' === $url ) {
            return '';
        }

        if ( PLSEO_Helpers::get_option( 'canonical_strip_params', true ) ) {
            $url = (string) strtok( $url, '?' );
        }

        if ( PLSEO_Helpers::get_option( 'canonical_force_https', true ) ) {
            $url = preg_replace( '#^http://#i', 'https://', $url );
        }

        if ( PLSEO_Helpers::get_option( 'canonical_trailing_slash', true ) ) {
            $path = wp_parse_url( $url, PHP_URL_PATH );
            if ( $path && '' === pathinfo( $path, PATHINFO_EXTENSION ) ) {
                $url = trailingslashit( $url );
            }
        }

        return (string) $url;
    }
}
