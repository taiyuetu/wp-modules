<?php
defined( 'ABSPATH' ) || exit;

class PLSEO_OpenGraph {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        if ( ! PLSEO_Helpers::get_option( 'og_enabled', true ) ) {
            return;
        }

        add_action( 'wp_head', [ $this, 'output_og' ], 6 );
    }

    public function output_og(): void {
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            if ( $post_id && PLSEO_Helpers::is_seo_disabled( $post_id ) ) {
                return;
            }
        }

        foreach ( $this->collect_tags() as $property => $content ) {
            if ( '' === (string) $content ) {
                continue;
            }

            printf(
                '<meta property="%s" content="%s" />' . "\n",
                esc_attr( $property ),
                esc_attr( $content )
            );
        }
    }

    private function collect_tags(): array {
        $meta      = PLSEO_Meta::get_instance();
        $canonical = PLSEO_Canonical::get_instance();
        $languages = PLSEO_Helpers::get_languages();
        $lang      = PLSEO_Helpers::current_lang();
        $locale    = get_locale();

        if ( $lang && isset( $languages[ $lang ]['locale'] ) ) {
            $locale = $languages[ $lang ]['locale'];
        }

        $tags = [
            'og:site_name'   => get_bloginfo( 'name' ),
            'og:locale'      => str_replace( '-', '_', $locale ),
            'og:type'        => is_singular( 'post' ) ? 'article' : 'website',
            'og:title'       => wp_get_document_title(),
            'og:description' => $meta->get_description(),
            'og:url'         => $canonical->get_canonical_url(),
            'og:image'       => $this->get_image(),
        ];

        if ( is_singular() ) {
            $post_id  = get_queried_object_id();
            $og_title = PLSEO_Helpers::get_post_seo_meta( $post_id, 'og_title', false );
            $og_desc  = PLSEO_Helpers::get_post_seo_meta( $post_id, 'og_description', false );
            $og_img   = PLSEO_Helpers::get_post_seo_meta( $post_id, 'og_image', false );

            if ( $og_title ) {
                $tags['og:title'] = $og_title;
            }
            if ( $og_desc ) {
                $tags['og:description'] = $og_desc;
            }
            if ( $og_img ) {
                $tags['og:image'] = $og_img;
            }

            if ( is_singular( 'post' ) ) {
                $tags['article:published_time'] = get_post_time( 'c', true, $post_id );
                $tags['article:modified_time']  = get_post_modified_time( 'c', true, $post_id );
            }
        } elseif ( is_post_type_archive() ) {
            $post_type = get_query_var( 'post_type' );
            if ( is_array( $post_type ) ) {
                $post_type = reset( $post_type );
            }

            if ( is_string( $post_type ) ) {
                $inherit_value    = PLSEO_Helpers::get_cpt_archive_seo_meta( $post_type, 'inherit_defaults' );
                $inherit_defaults = '' === $inherit_value || '1' === $inherit_value;
                $archive_title    = PLSEO_Helpers::get_cpt_archive_seo_meta( $post_type, 'title' );
                $archive_desc     = PLSEO_Helpers::get_cpt_archive_seo_meta( $post_type, 'description' );
                $archive_og_title = PLSEO_Helpers::get_cpt_archive_seo_meta( $post_type, 'og_title' );
                $archive_og_desc  = PLSEO_Helpers::get_cpt_archive_seo_meta( $post_type, 'og_description' );
                $archive_og_image = PLSEO_Helpers::get_cpt_archive_seo_meta( $post_type, 'og_image' );

                if ( $archive_og_title ) {
                    $tags['og:title'] = PLSEO_Helpers::replace_tokens( $archive_og_title, [ 'title' => post_type_archive_title( '', false ) ] );
                } elseif ( $inherit_defaults && $archive_title ) {
                    $tags['og:title'] = PLSEO_Helpers::replace_tokens( $archive_title, [ 'title' => post_type_archive_title( '', false ) ] );
                }
                if ( $archive_og_desc ) {
                    $tags['og:description'] = PLSEO_Helpers::replace_tokens( $archive_og_desc );
                } elseif ( $inherit_defaults && $archive_desc ) {
                    $tags['og:description'] = PLSEO_Helpers::replace_tokens( $archive_desc );
                }
                if ( $archive_og_image ) {
                    $tags['og:image'] = $archive_og_image;
                }
            }
        }

        $fb_app_id = PLSEO_Helpers::get_option( 'fb_app_id', '' );
        if ( $fb_app_id ) {
            $tags['fb:app_id'] = $fb_app_id;
        }

        return $tags;
    }

    private function get_image(): string {
        if ( is_post_type_archive() ) {
            $post_type = get_query_var( 'post_type' );
            if ( is_array( $post_type ) ) {
                $post_type = reset( $post_type );
            }

            if ( is_string( $post_type ) ) {
                $archive_og_image = PLSEO_Helpers::get_cpt_archive_seo_meta( $post_type, 'og_image' );
                if ( $archive_og_image ) {
                    return $archive_og_image;
                }
            }
        }

        if ( is_singular() ) {
            $post_id = get_queried_object_id();

            if ( has_post_thumbnail( $post_id ) ) {
                $image = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'full' );
                if ( $image ) {
                    return $image;
                }
            }
        }

        return (string) PLSEO_Helpers::get_option( 'og_default_image', '' );
    }
}
