<?php
defined( 'ABSPATH' ) || exit;

class PLSEO_Meta {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_filter( 'pre_get_document_title', [ $this, 'get_title' ], 20 );
        add_action( 'wp_head', [ $this, 'output_meta_tags' ], 2 );
        remove_action( 'wp_head', 'wp_generator' );
    }

    public function get_title( string $title ): string {
        if ( is_front_page() || is_home() ) {
            $custom = PLSEO_Helpers::get_option( 'homepage_title', '%%sitename%% %%sep%% %%tagline%%' );
            return PLSEO_Helpers::replace_tokens( $custom, [ 'title' => get_bloginfo( 'name' ) ] );
        }

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            if ( $post_id && PLSEO_Helpers::is_seo_disabled( $post_id ) ) {
                return $title;
            }

            $custom = PLSEO_Helpers::get_post_seo_meta( $post_id, 'meta_title' );
            $label  = get_the_title( $post_id );

            if ( $custom ) {
                return PLSEO_Helpers::replace_tokens( $custom, [ 'title' => $label ] );
            }

            return PLSEO_Helpers::replace_tokens(
                PLSEO_Helpers::get_option( 'title_format_single', '%%title%% %%sep%% %%sitename%%' ),
                [ 'title' => $label ]
            );
        }

        if ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term ) {
                $custom = PLSEO_Helpers::get_term_seo_meta( $term->term_id, 'meta_title' );
                if ( $custom ) {
                    return PLSEO_Helpers::replace_tokens( $custom, [ 'title' => $term->name ] );
                }

                return PLSEO_Helpers::replace_tokens(
                    PLSEO_Helpers::get_option( 'title_format_archive', '%%title%% %%sep%% %%sitename%%' ),
                    [ 'title' => $term->name ]
                );
            }
        }

        if ( is_search() ) {
            return PLSEO_Helpers::replace_tokens(
                PLSEO_Helpers::get_option( 'title_format_search', 'Search: %%searchterm%% %%sep%% %%sitename%%' ),
                [ 'searchterm' => get_search_query() ]
            );
        }

        if ( is_404() ) {
            return PLSEO_Helpers::replace_tokens( PLSEO_Helpers::get_option( 'title_format_404', 'Page not found %%sep%% %%sitename%%' ) );
        }

        if ( is_archive() ) {
            if ( is_post_type_archive() ) {
                $post_type = get_query_var( 'post_type' );
                if ( is_array( $post_type ) ) {
                    $post_type = reset( $post_type );
                }

                $archive_title = is_string( $post_type ) ? PLSEO_Helpers::get_cpt_archive_seo_meta( $post_type, 'title' ) : '';
                if ( $archive_title ) {
                    return PLSEO_Helpers::replace_tokens( $archive_title, [ 'title' => post_type_archive_title( '', false ) ] );
                }
            }

            return PLSEO_Helpers::replace_tokens(
                PLSEO_Helpers::get_option( 'title_format_archive', '%%title%% %%sep%% %%sitename%%' ),
                [ 'title' => wp_strip_all_tags( get_the_archive_title() ) ]
            );
        }

        return $title;
    }

    public function get_description(): string {
        if ( is_front_page() || is_home() ) {
            $custom = PLSEO_Helpers::get_option( 'homepage_description', '' );
            return $custom ? PLSEO_Helpers::replace_tokens( $custom ) : PLSEO_Helpers::clean_text( get_bloginfo( 'description' ) );
        }

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            if ( $post_id && PLSEO_Helpers::is_seo_disabled( $post_id ) ) {
                return '';
            }

            $custom = PLSEO_Helpers::get_post_seo_meta( $post_id, 'meta_description' );
            if ( $custom ) {
                return PLSEO_Helpers::replace_tokens( $custom );
            }

            $post = get_post( $post_id );
            if ( $post instanceof WP_Post ) {
                if ( $post->post_excerpt ) {
                    return PLSEO_Helpers::truncate( PLSEO_Helpers::clean_text( $post->post_excerpt ), 160 );
                }

                return PLSEO_Helpers::truncate( PLSEO_Helpers::clean_text( $post->post_content ), 160 );
            }
        }

        if ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term ) {
                $custom = PLSEO_Helpers::get_term_seo_meta( $term->term_id, 'meta_description' );
                if ( $custom ) {
                    return PLSEO_Helpers::replace_tokens( $custom );
                }

                return PLSEO_Helpers::truncate( PLSEO_Helpers::clean_text( $term->description ), 160 );
            }
        }

        if ( is_post_type_archive() ) {
            $post_type = get_query_var( 'post_type' );
            if ( is_array( $post_type ) ) {
                $post_type = reset( $post_type );
            }

            $archive_description = is_string( $post_type ) ? PLSEO_Helpers::get_cpt_archive_seo_meta( $post_type, 'description' ) : '';
            if ( $archive_description ) {
                return PLSEO_Helpers::replace_tokens( $archive_description );
            }
        }

        return '';
    }

    public function get_robots(): string {
        if ( is_search() && PLSEO_Helpers::get_option( 'noindex_search', true ) ) {
            return 'noindex, nofollow';
        }
        if ( is_404() && PLSEO_Helpers::get_option( 'noindex_404', true ) ) {
            return 'noindex, nofollow';
        }
        if ( is_author() && PLSEO_Helpers::get_option( 'noindex_author', false ) ) {
            return 'noindex, follow';
        }
        if ( is_date() && PLSEO_Helpers::get_option( 'noindex_date', true ) ) {
            return 'noindex, follow';
        }
        if ( is_attachment() && PLSEO_Helpers::get_option( 'noindex_attachment', true ) ) {
            return 'noindex, follow';
        }
        if ( ( is_tax() || is_category() || is_tag() ) && PLSEO_Helpers::get_option( 'noindex_empty_taxonomy', true ) ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term && 0 === (int) $term->count ) {
                return 'noindex, follow';
            }
        }

        if ( is_singular() ) {
            $post_id = get_queried_object_id();
            if ( $post_id && PLSEO_Helpers::is_seo_disabled( $post_id ) ) {
                return 'index, follow';
            }

            $robots = [];
            if ( get_post_meta( $post_id, '_plseo_noindex', true ) ) {
                $robots[] = 'noindex';
            }
            if ( get_post_meta( $post_id, '_plseo_nofollow', true ) ) {
                $robots[] = 'nofollow';
            }

            if ( ! empty( $robots ) ) {
                if ( ! in_array( 'noindex', $robots, true ) && ! in_array( 'index', $robots, true ) ) {
                    $robots[] = 'index';
                }
                if ( ! in_array( 'nofollow', $robots, true ) && ! in_array( 'follow', $robots, true ) ) {
                    $robots[] = 'follow';
                }

                return implode( ', ', $robots );
            }
        }

        return 'index, follow';
    }

    public function output_meta_tags(): void {
        $post_id = is_singular() ? get_queried_object_id() : 0;
        if ( $post_id && PLSEO_Helpers::is_seo_disabled( $post_id ) ) {
            return;
        }

        $description = $this->get_description();
        if ( $description ) {
            printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
        }

        printf( '<meta name="robots" content="%s" />' . "\n", esc_attr( $this->get_robots() ) );

        $lang = PLSEO_Helpers::current_lang();
        if ( $lang ) {
            printf( '<meta http-equiv="content-language" content="%s" />' . "\n", esc_attr( $lang ) );
        }

        $this->output_resource_hints();
    }

    private function output_resource_hints(): void {
        foreach ( explode( "\n", (string) PLSEO_Helpers::get_option( 'preconnect_domains', '' ) ) as $domain ) {
            $domain = trim( $domain );
            if ( $domain ) {
                printf( '<link rel="preconnect" href="%s" />' . "\n", esc_url( $domain ) );
            }
        }

        foreach ( explode( "\n", (string) PLSEO_Helpers::get_option( 'dns_prefetch', '' ) ) as $domain ) {
            $domain = trim( $domain );
            if ( $domain ) {
                printf( '<link rel="dns-prefetch" href="%s" />' . "\n", esc_url( $domain ) );
            }
        }
    }
}
