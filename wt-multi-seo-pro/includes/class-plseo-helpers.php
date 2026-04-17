<?php
defined( 'ABSPATH' ) || exit;

class PLSEO_Helpers {

    public static function get_languages(): array {
        if ( ! function_exists( 'pll_the_languages' ) ) {
            return [];
        }

        $raw_languages = pll_the_languages(
            [
                'raw'                    => 1,
                'hide_if_empty'          => 0,
                'hide_if_no_translation' => 0,
            ]
        );

        if ( empty( $raw_languages ) || ! is_array( $raw_languages ) ) {
            return [];
        }

        $languages = [];

        foreach ( $raw_languages as $language ) {
            if ( empty( $language['slug'] ) ) {
                continue;
            }

            $languages[ $language['slug'] ] = [
                'slug'   => $language['slug'],
                'locale' => $language['locale'] ?? $language['slug'],
                'name'   => $language['name'] ?? strtoupper( $language['slug'] ),
                'url'    => $language['url'] ?? '',
            ];
        }

        return $languages;
    }

    public static function current_lang(): string {
        return function_exists( 'pll_current_language' ) ? (string) pll_current_language() : '';
    }

    public static function locale_to_hreflang( string $locale ): string {
        $map = [
            'zh_CN' => 'zh-Hans',
            'zh_TW' => 'zh-Hant',
            'zh_HK' => 'zh-Hant-HK',
        ];

        return $map[ $locale ] ?? str_replace( '_', '-', $locale );
    }

    public static function get_post_seo_meta( int $post_id, string $key, bool $fallback = true ): string {
        $value = (string) get_post_meta( $post_id, '_plseo_' . $key, true );

        if ( '' === $value && $fallback && function_exists( 'pll_get_post' ) ) {
            $default_lang = function_exists( 'pll_default_language' ) ? pll_default_language() : '';

            if ( $default_lang ) {
                $default_id = pll_get_post( $post_id, $default_lang );
                if ( $default_id && (int) $default_id !== $post_id ) {
                    $value = (string) get_post_meta( (int) $default_id, '_plseo_' . $key, true );
                    $value = (string) apply_filters( 'plseo_post_seo_meta_fallback', $value, $post_id, $key, (int) $default_id );
                }
            }
        }

        return $value;
    }

    public static function get_term_seo_meta( int $term_id, string $key ): string {
        return (string) get_term_meta( $term_id, '_plseo_' . $key, true );
    }

    public static function replace_tokens( string $template, array $vars = [] ): string {
        $defaults = [
            'sep'         => self::get_option( 'title_separator', '-' ),
            'sitename'    => get_bloginfo( 'name' ),
            'tagline'     => get_bloginfo( 'description' ),
            'currentyear' => gmdate( 'Y' ),
            'page'        => is_paged() ? sprintf( __( 'Page %d', 'polylang-seo' ), (int) get_query_var( 'paged' ) ) : '',
        ];

        $vars = array_merge( $defaults, $vars );

        foreach ( $vars as $token => $replacement ) {
            $template = str_replace( '%%' . $token . '%%', (string) $replacement, $template );
        }

        $template = preg_replace( '/%%[a-zA-Z0-9_]+%%/', '', $template );

        return trim( preg_replace( '/\s+/', ' ', (string) $template ) );
    }

    public static function get_option( string $key, mixed $default = '' ): mixed {
        return get_option( 'plseo_' . $key, $default );
    }

    public static function get_cpt_archive_seo_meta( string $post_type, string $key ): string {
        if ( '' === $post_type || '' === $key ) {
            return '';
        }

        $all = self::get_option( 'cpt_archive_seo', [] );
        if ( ! is_array( $all ) || ! isset( $all[ $post_type ] ) || ! is_array( $all[ $post_type ] ) ) {
            return '';
        }

        $value = self::resolve_cpt_archive_lang_value( $all[ $post_type ], $key );

        return is_string( $value ) ? trim( $value ) : '';
    }

    private static function resolve_cpt_archive_lang_value( array $post_type_settings, string $key ): string {
        // Backward compatibility: old storage without per-language nesting.
        if ( isset( $post_type_settings[ $key ] ) && is_string( $post_type_settings[ $key ] ) ) {
            return $post_type_settings[ $key ];
        }

        $current_lang = self::current_lang();
        $default_lang = function_exists( 'pll_default_language' ) ? (string) pll_default_language() : '';

        if ( $current_lang && isset( $post_type_settings[ $current_lang ][ $key ] ) && is_string( $post_type_settings[ $current_lang ][ $key ] ) ) {
            return $post_type_settings[ $current_lang ][ $key ];
        }

        if ( $default_lang && isset( $post_type_settings[ $default_lang ][ $key ] ) && is_string( $post_type_settings[ $default_lang ][ $key ] ) ) {
            return $post_type_settings[ $default_lang ][ $key ];
        }

        if ( isset( $post_type_settings['default'][ $key ] ) && is_string( $post_type_settings['default'][ $key ] ) ) {
            return $post_type_settings['default'][ $key ];
        }

        return '';
    }

    public static function truncate( string $str, int $limit ): string {
        if ( mb_strlen( $str ) <= $limit ) {
            return $str;
        }

        return rtrim( mb_substr( $str, 0, $limit - 3 ) ) . '...';
    }

    public static function clean_text( string $text ): string {
        return trim( wp_strip_all_tags( strip_shortcodes( $text ) ) );
    }

    public static function get_current_url(): string {
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            return home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );
        }

        global $wp;

        return isset( $wp->request ) ? trailingslashit( home_url( $wp->request ) ) : home_url( '/' );
    }

    public static function is_seo_disabled( int $post_id ): bool {
        return (bool) get_post_meta( $post_id, '_plseo_disable_seo', true );
    }

    public static function get_post_translations( int $post_id ): array {
        if ( ! function_exists( 'pll_get_post_translations' ) ) {
            return [];
        }

        return (array) pll_get_post_translations( $post_id );
    }

    public static function get_term_translations( int $term_id ): array {
        if ( ! function_exists( 'pll_get_term_translations' ) ) {
            return [];
        }

        return (array) pll_get_term_translations( $term_id );
    }
}
