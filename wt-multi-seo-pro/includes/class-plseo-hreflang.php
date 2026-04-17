<?php
defined( 'ABSPATH' ) || exit;

class PLSEO_Hreflang {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_head', [ $this, 'output_hreflang' ], 5 );
    }

    public function output_hreflang(): void {
        if ( is_search() || is_404() ) {
            return;
        }

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            if ( $post_id && PLSEO_Helpers::is_seo_disabled( $post_id ) ) {
                return;
            }
        }

        $links = $this->get_hreflang_links();
        if ( empty( $links ) ) {
            return;
        }

        foreach ( $links as $hreflang => $url ) {
            printf( '<link rel="alternate" hreflang="%s" href="%s" />' . "\n", esc_attr( $hreflang ), esc_url( $url ) );
        }

        $x_default = $this->get_x_default( $links );
        if ( $x_default ) {
            printf( '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n", esc_url( $x_default ) );
        }
    }

    private function get_hreflang_links(): array {
        $languages = PLSEO_Helpers::get_languages();
        $links     = [];

        if ( is_singular() ) {
            foreach ( PLSEO_Helpers::get_post_translations( get_queried_object_id() ) as $slug => $post_id ) {
                if ( empty( $languages[ $slug ] ) || ! $post_id || get_post_meta( (int) $post_id, '_plseo_noindex', true ) ) {
                    continue;
                }

                $url = get_permalink( (int) $post_id );
                if ( $url ) {
                    $links[ PLSEO_Helpers::locale_to_hreflang( $languages[ $slug ]['locale'] ) ] = $url;
                }
            }

            return $links;
        }

        if ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();
            if ( ! $term instanceof WP_Term ) {
                return [];
            }

            foreach ( PLSEO_Helpers::get_term_translations( $term->term_id ) as $slug => $term_id ) {
                if ( empty( $languages[ $slug ] ) || ! $term_id ) {
                    continue;
                }

                $url = get_term_link( (int) $term_id, $term->taxonomy );
                if ( ! is_wp_error( $url ) ) {
                    $links[ PLSEO_Helpers::locale_to_hreflang( $languages[ $slug ]['locale'] ) ] = $url;
                }
            }

            return $links;
        }

        if ( is_front_page() || is_home() ) {
            foreach ( $languages as $slug => $language ) {
                $url = function_exists( 'pll_home_url' ) ? pll_home_url( $slug ) : ( $language['url'] ?: home_url( '/' . $slug . '/' ) );
                if ( $url ) {
                    $links[ PLSEO_Helpers::locale_to_hreflang( $language['locale'] ) ] = $url;
                }
            }
        }

        return $links;
    }

    private function get_x_default( array $links ): string {
        $default_language = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
        $languages        = PLSEO_Helpers::get_languages();

        if ( $default_language && isset( $languages[ $default_language ]['locale'] ) ) {
            $hreflang = PLSEO_Helpers::locale_to_hreflang( $languages[ $default_language ]['locale'] );
            return $links[ $hreflang ] ?? '';
        }

        return (string) reset( $links );
    }
}
