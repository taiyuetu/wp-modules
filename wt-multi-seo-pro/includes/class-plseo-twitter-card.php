<?php
defined( 'ABSPATH' ) || exit;

class PLSEO_Twitter_Card {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        if ( ! PLSEO_Helpers::get_option( 'twitter_enabled', true ) ) {
            return;
        }

        add_action( 'wp_head', [ $this, 'output_tags' ], 7 );
    }

    public function output_tags(): void {
        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            if ( $post_id && PLSEO_Helpers::is_seo_disabled( $post_id ) ) {
                return;
            }
        }

        $meta = PLSEO_Meta::get_instance();
        $tags = [
            'twitter:card'        => PLSEO_Helpers::get_option( 'twitter_card_type', 'summary_large_image' ),
            'twitter:title'       => wp_get_document_title(),
            'twitter:description' => $meta->get_description(),
            'twitter:image'       => $this->get_image(),
        ];

        $site = PLSEO_Helpers::get_option( 'twitter_site', '' );
        if ( $site ) {
            $tags['twitter:site'] = '@' . ltrim( $site, '@' );
        }

        $creator = PLSEO_Helpers::get_option( 'twitter_creator', '' );
        if ( $creator ) {
            $tags['twitter:creator'] = '@' . ltrim( $creator, '@' );
        }

        if ( is_post_type_archive() ) {
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

                if ( $archive_og_title ) {
                    $tags['twitter:title'] = PLSEO_Helpers::replace_tokens( $archive_og_title, [ 'title' => post_type_archive_title( '', false ) ] );
                } elseif ( $inherit_defaults && $archive_title ) {
                    $tags['twitter:title'] = PLSEO_Helpers::replace_tokens( $archive_title, [ 'title' => post_type_archive_title( '', false ) ] );
                }

                if ( $archive_og_desc ) {
                    $tags['twitter:description'] = PLSEO_Helpers::replace_tokens( $archive_og_desc );
                } elseif ( $inherit_defaults && $archive_desc ) {
                    $tags['twitter:description'] = PLSEO_Helpers::replace_tokens( $archive_desc );
                }
            }
        }

        foreach ( $tags as $name => $content ) {
            if ( '' === (string) $content ) {
                continue;
            }

            printf(
                '<meta name="%s" content="%s" />' . "\n",
                esc_attr( $name ),
                esc_attr( $content )
            );
        }
    }

    private function get_image(): string {
        if ( is_post_type_archive() ) {
            $post_type = get_query_var( 'post_type' );
            if ( is_array( $post_type ) ) {
                $post_type = reset( $post_type );
            }

            if ( is_string( $post_type ) ) {
                $inherit_value    = PLSEO_Helpers::get_cpt_archive_seo_meta( $post_type, 'inherit_defaults' );
                $inherit_defaults = '' === $inherit_value || '1' === $inherit_value;
                $archive_twitter_image = PLSEO_Helpers::get_cpt_archive_seo_meta( $post_type, 'twitter_image' );
                if ( $archive_twitter_image ) {
                    return $archive_twitter_image;
                }

                $archive_og_image = PLSEO_Helpers::get_cpt_archive_seo_meta( $post_type, 'og_image' );
                if ( $inherit_defaults && $archive_og_image ) {
                    return $archive_og_image;
                }
            }
        }

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            $custom  = PLSEO_Helpers::get_post_seo_meta( $post_id, 'twitter_image', false );

            if ( $custom ) {
                return $custom;
            }

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
