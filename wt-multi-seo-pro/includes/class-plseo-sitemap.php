<?php
defined( 'ABSPATH' ) || exit;

/**
 * Generates a multilingual XML Sitemap fully compatible with Polylang.
 *
 * Sitemap index:  /sitemap.xml
 * Sub-sitemaps:
 *   /sitemap-pages.xml
 *   /sitemap-posts.xml
 *   /sitemap-{post_type}.xml
 *   /sitemap-taxonomies.xml
 */
class PLSEO_Sitemap {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if ( ! PLSEO_Helpers::get_option( 'sitemap_enabled', true ) ) {
            return;
        }

        add_action( 'init',             [ $this, 'add_rewrite_rules' ] );
        add_action( 'template_redirect',[ $this, 'handle_sitemap_request' ] );
        add_action( 'plseo_regenerate_sitemap', [ $this, 'ping_search_engines' ] );

        // Ping on publish
        add_action( 'publish_post',     [ $this, 'ping_search_engines' ] );
        add_action( 'publish_page',     [ $this, 'ping_search_engines' ] );
    }

    public function add_rewrite_rules(): void {
        add_rewrite_rule( '^sitemap\.xml$', 'index.php?plseo_sitemap=index', 'top' );
        add_rewrite_rule( '^sitemap-([a-z0-9_-]+)\.xml$', 'index.php?plseo_sitemap=$matches[1]', 'top' );
        add_rewrite_tag( '%plseo_sitemap%', '([a-z0-9_-]+)' );
    }

    public function handle_sitemap_request(): void {
        $type = get_query_var( 'plseo_sitemap' );
        if ( ! $type ) {
            return;
        }

        // No caching for sitemaps
        nocache_headers();

        switch ( $type ) {
            case 'index':
                $this->render_index();
                break;
            case 'pages':
                $this->render_post_type_sitemap( 'page' );
                break;
            case 'posts':
                $this->render_post_type_sitemap( 'post' );
                break;
            case 'taxonomies':
                $this->render_taxonomy_sitemap();
                break;
            default:
                // Custom post types: /sitemap-{post_type}.xml
                if ( post_type_exists( $type ) ) {
                    $this->render_post_type_sitemap( $type );
                } else {
                    wp_die( 'Sitemap not found.', 404 );
                }
        }
        exit;
    }

    /* ─────────────────────────────────────────────────────────── */
    /*  Sitemap Index                                               */
    /* ─────────────────────────────────────────────────────────── */

    private function render_index(): void {
        $this->send_xml_headers();

        $sitemaps = $this->get_sub_sitemaps();

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ( $sitemaps as $sitemap ) {
            echo "\t<sitemap>\n";
            printf( "\t\t<loc>%s</loc>\n", esc_url( $sitemap['url'] ) );
            printf( "\t\t<lastmod>%s</lastmod>\n", esc_xml( $sitemap['lastmod'] ) );
            echo "\t</sitemap>\n";
        }

        echo '</sitemapindex>';
    }

    private function get_sub_sitemaps(): array {
        $list  = [];
        $stamp = gmdate( 'c' );

        if ( PLSEO_Helpers::get_option( 'sitemap_include_pages', true ) ) {
            $list[] = [ 'url' => home_url( 'sitemap-pages.xml' ), 'lastmod' => $stamp ];
        }
        if ( PLSEO_Helpers::get_option( 'sitemap_include_posts', true ) ) {
            $list[] = [ 'url' => home_url( 'sitemap-posts.xml' ), 'lastmod' => $stamp ];
        }

        $cpts = (array) PLSEO_Helpers::get_option( 'sitemap_include_cpt', [] );
        foreach ( $cpts as $cpt ) {
            if ( post_type_exists( $cpt ) ) {
                $list[] = [ 'url' => home_url( "sitemap-{$cpt}.xml" ), 'lastmod' => $stamp ];
            }
        }

        if ( PLSEO_Helpers::get_option( 'sitemap_include_taxonomies', true ) ) {
            $list[] = [ 'url' => home_url( 'sitemap-taxonomies.xml' ), 'lastmod' => $stamp ];
        }

        return $list;
    }

    /* ─────────────────────────────────────────────────────────── */
    /*  Post-type sitemap                                           */
    /* ─────────────────────────────────────────────────────────── */

    private function render_post_type_sitemap( string $post_type ): void {
        $this->send_xml_headers();

        $exclude = (array) PLSEO_Helpers::get_option( 'sitemap_exclude_ids', [] );
        $per     = (int) PLSEO_Helpers::get_option( 'sitemap_posts_per_type', 1000 );

        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $per,
            'no_found_rows'  => true,
            'post__not_in'   => $exclude,
            // Polylang: get posts in all languages
            'lang'           => '',
        ];

        $query = new WP_Query( $args );

        $has_hreflang = (bool) PLSEO_Helpers::get_option( 'sitemap_hreflang', true );
        $langs        = PLSEO_Helpers::get_languages();

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

        if ( $has_hreflang ) {
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
                . "\n\t" . 'xmlns:xhtml="http://www.w3.org/1999/xhtml"'
                . "\n\t" . 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"'
                . '>' . "\n";
        } else {
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
                . "\n\t" . 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"'
                . '>' . "\n";
        }

        // Group by translation set to avoid duplicate canonical groups
        $seen_sets = [];

        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            if ( in_array( $post_id, $exclude, true ) ) {
                continue;
            }

            // Check per-post noindex
            if ( get_post_meta( $post_id, '_plseo_noindex', true ) ) {
                continue;
            }

            // Build translation set fingerprint
            $translations = $has_hreflang ? PLSEO_Helpers::get_post_translations( $post_id ) : [];
            asort( $translations );
            $set_key = implode( '|', $translations );

            if ( $set_key && isset( $seen_sets[ $set_key ] ) ) {
                continue; // Already output all hreflang entries for this set
            }
            if ( $set_key ) {
                $seen_sets[ $set_key ] = true;
            }

            $this->render_url_entry(
                get_permalink( $post_id ),
                get_post_modified_time( 'c', true, $post_id ),
                $this->get_change_freq( $post_type ),
                $this->get_priority( $post_type, $post_id ),
                $has_hreflang ? $this->build_hreflang_links_for_posts( $translations, $langs ) : [],
                $has_hreflang ? $this->build_xdefault( $translations ) : '',
                PLSEO_Helpers::get_option( 'sitemap_images', true ) ? $this->get_post_images( $post_id ) : []
            );
        }
        wp_reset_postdata();

        echo '</urlset>';
    }

    /* ─────────────────────────────────────────────────────────── */
    /*  Taxonomy sitemap                                            */
    /* ─────────────────────────────────────────────────────────── */

    private function render_taxonomy_sitemap(): void {
        $this->send_xml_headers();

        $enabled_taxes = (array) PLSEO_Helpers::get_option( 'enabled_taxonomies', [ 'category', 'post_tag' ] );
        $has_hreflang  = (bool) PLSEO_Helpers::get_option( 'sitemap_hreflang', true );
        $langs         = PLSEO_Helpers::get_languages();

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        if ( $has_hreflang ) {
            echo "\n\t" . 'xmlns:xhtml="http://www.w3.org/1999/xhtml"';
        }
        echo '>' . "\n";

        $seen_sets = [];

        foreach ( $enabled_taxes as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $terms = get_terms( [
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
                'lang'       => '', // All Polylang languages
            ] );

            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                continue;
            }

            foreach ( $terms as $term ) {
                $translations = $has_hreflang ? PLSEO_Helpers::get_term_translations( $term->term_id ) : [];
                asort( $translations );
                $set_key = implode( '|', $translations );

                if ( $set_key && isset( $seen_sets[ $set_key ] ) ) {
                    continue;
                }
                if ( $set_key ) {
                    $seen_sets[ $set_key ] = true;
                }

                $url = get_term_link( $term );
                if ( is_wp_error( $url ) ) {
                    continue;
                }

                $this->render_url_entry(
                    $url,
                    gmdate( 'c' ),
                    'weekly',
                    0.4,
                    $has_hreflang ? $this->build_hreflang_links_for_terms( $translations, $langs, $taxonomy ) : [],
                    $has_hreflang ? $this->build_xdefault_term( $translations, $taxonomy ) : ''
                );
            }
        }

        echo '</urlset>';
    }

    /* ─────────────────────────────────────────────────────────── */
    /*  URL entry renderer                                          */
    /* ─────────────────────────────────────────────────────────── */

    private function render_url_entry(
        string $loc,
        string $lastmod,
        string $changefreq,
        float  $priority,
        array  $hreflang_links = [],
        string $x_default      = '',
        array  $images         = []
    ): void {
        echo "\t<url>\n";
        printf( "\t\t<loc>%s</loc>\n", esc_url( $loc ) );
        printf( "\t\t<lastmod>%s</lastmod>\n", esc_xml( $lastmod ) );
        printf( "\t\t<changefreq>%s</changefreq>\n", esc_xml( $changefreq ) );
        printf( "\t\t<priority>%.1f</priority>\n", (float) $priority );

        // hreflang alternate links
        foreach ( $hreflang_links as $hreflang => $href ) {
            printf(
                "\t\t<xhtml:link rel=\"alternate\" hreflang=\"%s\" href=\"%s\" />\n",
                esc_attr( $hreflang ),
                esc_url( $href )
            );
        }
        if ( $x_default && PLSEO_Helpers::get_option( 'sitemap_x_default', true ) ) {
            printf(
                "\t\t<xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"%s\" />\n",
                esc_url( $x_default )
            );
        }

        // Image extensions
        foreach ( $images as $img ) {
            echo "\t\t<image:image>\n";
            printf( "\t\t\t<image:loc>%s</image:loc>\n", esc_url( $img['src'] ) );
            if ( ! empty( $img['title'] ) ) {
                printf( "\t\t\t<image:title>%s</image:title>\n", esc_xml( $img['title'] ) );
            }
            if ( ! empty( $img['alt'] ) ) {
                printf( "\t\t\t<image:caption>%s</image:caption>\n", esc_xml( $img['alt'] ) );
            }
            echo "\t\t</image:image>\n";
        }

        echo "\t</url>\n";
    }

    /* ─────────────────────────────────────────────────────────── */
    /*  hreflang helpers                                            */
    /* ─────────────────────────────────────────────────────────── */

    /**
     * Build [ hreflang => url ] map for posts.
     *
     * @param array $translations [ lang_slug => post_id ]
     * @param array $langs        [ lang_slug => lang_data ]
     */
    private function build_hreflang_links_for_posts( array $translations, array $langs ): array {
        $links = [];
        foreach ( $translations as $slug => $pid ) {
            if ( ! isset( $langs[ $slug ] ) ) {
                continue;
            }
            $url = get_permalink( (int) $pid );
            if ( $url ) {
                $hreflang         = PLSEO_Helpers::locale_to_hreflang( $langs[ $slug ]['locale'] );
                $links[ $hreflang ] = $url;
            }
        }
        return $links;
    }

    private function build_hreflang_links_for_terms( array $translations, array $langs, string $taxonomy ): array {
        $links = [];
        foreach ( $translations as $slug => $tid ) {
            if ( ! isset( $langs[ $slug ] ) ) {
                continue;
            }
            $url = get_term_link( (int) $tid, $taxonomy );
            if ( ! is_wp_error( $url ) ) {
                $hreflang         = PLSEO_Helpers::locale_to_hreflang( $langs[ $slug ]['locale'] );
                $links[ $hreflang ] = $url;
            }
        }
        return $links;
    }

    /**
     * x-default points to the default Polylang language version.
     *
     * @param array $translations [ lang_slug => post_id ]
     */
    private function build_xdefault( array $translations ): string {
        $default = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
        if ( $default && isset( $translations[ $default ] ) ) {
            return (string) get_permalink( (int) $translations[ $default ] );
        }
        // Fallback: first translation
        $first = reset( $translations );
        return $first ? (string) get_permalink( (int) $first ) : '';
    }

    private function build_xdefault_term( array $translations, string $taxonomy ): string {
        $default = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
        if ( $default && isset( $translations[ $default ] ) ) {
            $url = get_term_link( (int) $translations[ $default ], $taxonomy );
            return is_wp_error( $url ) ? '' : $url;
        }
        $first = reset( $translations );
        if ( $first ) {
            $url = get_term_link( (int) $first, $taxonomy );
            return is_wp_error( $url ) ? '' : $url;
        }
        return '';
    }

    /* ─────────────────────────────────────────────────────────── */
    /*  Image extraction                                            */
    /* ─────────────────────────────────────────────────────────── */

    private function get_post_images( int $post_id ): array {
        $images = [];

        // Featured image
        if ( has_post_thumbnail( $post_id ) ) {
            $att_id = get_post_thumbnail_id( $post_id );
            $src    = wp_get_attachment_image_url( $att_id, 'full' );
            if ( $src ) {
                $images[] = [
                    'src'   => $src,
                    'title' => get_the_title( $att_id ),
                    'alt'   => get_post_meta( $att_id, '_wp_attachment_image_alt', true ),
                ];
            }
        }

        // Content images (first 5 to keep sitemap manageable)
        $post    = get_post( $post_id );
        $content = $post ? $post->post_content : '';
        preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
        $count = 0;
        foreach ( $matches[1] as $src ) {
            if ( $count >= 5 ) {
                break;
            }
            // Avoid duplicating featured image
            if ( isset( $images[0] ) && $images[0]['src'] === $src ) {
                continue;
            }
            // Extract alt
            preg_match( '/alt=["\']([^"\']*)["\']/', $matches[0][ array_search( $src, $matches[1], true ) ], $alt_match );
            $images[] = [
                'src'   => $src,
                'title' => '',
                'alt'   => $alt_match[1] ?? '',
            ];
            $count++;
        }

        return $images;
    }

    /* ─────────────────────────────────────────────────────────── */
    /*  Helpers                                                     */
    /* ─────────────────────────────────────────────────────────── */

    private function send_xml_headers(): void {
        header( 'Content-Type: text/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );
    }

    private function get_change_freq( string $post_type ): string {
        return match ( $post_type ) {
            'page'  => 'monthly',
            'post'  => 'weekly',
            default => 'weekly',
        };
    }

    private function get_priority( string $post_type, int $post_id ): float {
        if ( (int) get_option( 'page_on_front' ) === $post_id ) {
            return 1.0;
        }
        return match ( $post_type ) {
            'page'  => 0.8,
            'post'  => 0.6,
            default => 0.5,
        };
    }

    public function maybe_flush(): void {
        global $wp_rewrite;

        if ( ! isset( $wp_rewrite->extra_rules_top['sitemap\.xml$'] ) ) {
            flush_rewrite_rules();
        }
    }

    public function ping_search_engines(): void {
        if ( ! PLSEO_Helpers::get_option( 'sitemap_ping_google', true ) && ! PLSEO_Helpers::get_option( 'sitemap_ping_bing', true ) ) {
            return;
        }

        $sitemap_url = urlencode( home_url( 'sitemap.xml' ) );

        if ( PLSEO_Helpers::get_option( 'sitemap_ping_google', true ) ) {
            wp_remote_get( "https://www.google.com/ping?sitemap={$sitemap_url}", [ 'blocking' => false ] );
        }
        if ( PLSEO_Helpers::get_option( 'sitemap_ping_bing', true ) ) {
            wp_remote_get( "https://www.bing.com/ping?sitemap={$sitemap_url}", [ 'blocking' => false ] );
        }
    }
}
