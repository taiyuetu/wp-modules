<?php
defined( 'ABSPATH' ) || exit;

/**
 * PLSEO_Schema - Professional GEO & SEO Schema Generator
 * Enhanced for AI Indexing, Products (Catalog), and Video Objects.
 */
class PLSEO_Schema {

    private static ?self $instance = null;
    private array $graph = [];
    private string $home_url;
    private string $site_name;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if ( ! PLSEO_Helpers::get_option( 'schema_enabled', true ) ) {
            return;
        }

        $this->home_url  = trailingslashit( get_home_url() );
        $this->site_name = get_bloginfo( 'name' );

        add_action( 'wp_head', [ $this, 'output_schema' ], 8 );
    }

    /**
     * Main output controller using @graph to connect entities
     */
    public function output_schema(): void {
        $this->graph = [];

        $post_id = 0;
        $post_type = '';

        if ( is_singular() ) {
            $post_id   = get_queried_object_id();
            $post_type = get_post_type( $post_id );
            
            if ( $post_id && PLSEO_Helpers::is_seo_disabled( $post_id ) ) {
                return;
            }
        }

        // 1. Global Core Entities
        $this->add_organization_to_graph();
        $this->add_website_to_graph();

        // 2. Breadcrumbs (Essential for Hierarchy AI)
        if ( PLSEO_Helpers::get_option( 'schema_breadcrumb', true ) ) {
            $this->add_breadcrumb_to_graph();
        }

        // 3. Content Specific Entities
        if ( is_singular() ) {
            switch ( $post_type ) {
                case 'post':
                    $this->add_article_to_graph( $post_id );
                    break;
                case 'product':
                    $this->add_product_to_graph( $post_id );
                    break;
                case 'video':
                    $this->add_video_to_graph( $post_id );
                    break;
                default:
                    $this->add_webpage_to_graph( $post_id );
                    break;
            }
        }

        // 4. Local Business Override
        if ( PLSEO_Helpers::get_option( 'schema_local_business', false ) ) {
            $this->add_local_business_to_graph();
        }

        // Clean up and output
        $this->graph = $this->filter_graph_values( $this->graph );

        if ( ! empty( $this->graph ) ) {
            echo "\n" . '<!-- PLSEO AI-Optimized Graph Schema -->' . "\n";
            echo '<script type="application/ld+json" class="plseo-schema-graph">' . "\n";
            echo json_encode( [
                '@context' => 'https://schema.org',
                '@graph'   => array_values( $this->graph )
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
            echo "\n</script>\n";
        }
    }

    private function add_organization_to_graph(): void {
        $logo_id = get_theme_mod( 'custom_logo' );
        $logo    = $logo_id ? wp_get_attachment_image_src( $logo_id, 'full' ) : null;

        $this->graph['organization'] = $this->filter_graph_values( [
            '@type' => 'Organization',
            '@id'   => $this->home_url . '#organization',
            'name'  => $this->site_name,
            'url'   => $this->home_url,
            'logo'  => $logo ? [
                '@type' => 'ImageObject',
                '@id'   => $this->home_url . '#logo',
                'url'   => $logo[0],
                'width' => $logo[1],
                'height' => $logo[2]
            ] : null,
            'sameAs' => array_filter( [
                PLSEO_Helpers::get_option( 'social_facebook' ),
                PLSEO_Helpers::get_option( 'social_twitter' ),
                PLSEO_Helpers::get_option( 'social_linkedin' )
            ] )
        ] );
    }

    private function add_website_to_graph(): void {
        $this->graph['website'] = [
            '@type'     => 'WebSite',
            '@id'       => $this->home_url . '#website',
            'url'       => $this->home_url,
            'name'      => $this->site_name,
            'publisher' => [ '@id' => $this->home_url . '#organization' ]
        ];
    }

    /**
     * PRODUCT CPT (Non-Ecommerce Information Catalog)
     */
    private function add_product_to_graph( int $post_id ): void {
        $this->graph['product'] = [
            '@type'       => 'Product',
            '@id'         => get_permalink( $post_id ) . '#product',
            'name'        => get_the_title( $post_id ),
            'description' => wp_strip_all_tags( get_the_excerpt( $post_id ) ),
            'image'       => $this->get_featured_image_url( $post_id ),
            'sku'         => get_post_meta( $post_id, '_sku', true ) ?: 'N/A',
            'brand'       => [
                '@type' => 'Brand',
                'name'  => $this->site_name
            ],
            'mainEntityOfPage' => [ '@id' => get_permalink( $post_id ) . '#webpage' ]
        ];
        $this->add_webpage_to_graph( $post_id );
    }

    /**
     * VIDEO CPT (Optimized for Video Rich Snippets)
     */
    private function add_video_to_graph( int $post_id ): void {
        // AI specifically looks for uploadDate and contentUrl/embedUrl
        $this->graph['video'] = [
            '@type'        => 'VideoObject',
            '@id'          => get_permalink( $post_id ) . '#video',
            'name'         => get_the_title( $post_id ),
            'description'  => wp_strip_all_tags( get_the_excerpt( $post_id ) ),
            'thumbnailUrl' => $this->get_featured_image_url( $post_id ),
            'uploadDate'   => get_the_date( 'c', $post_id ),
            'duration'     => get_post_meta( $post_id, '_video_duration', true ) ?: 'PT1M', // ISO 8601 format
            'contentUrl'   => get_post_meta( $post_id, '_video_url', true ) ?: get_permalink($post_id),
            'embedUrl'     => get_post_meta( $post_id, '_video_embed_url', true ) ?: '',
            'publisher'    => [ '@id' => $this->home_url . '#organization' ]
        ];
        $this->add_webpage_to_graph( $post_id );
    }

    private function add_article_to_graph( int $post_id ): void {
        $this->graph['article'] = [
            '@type'         => 'BlogPosting',
            '@id'           => get_permalink( $post_id ) . '#article',
            'headline'      => get_the_title( $post_id ),
            'datePublished' => get_the_date( 'c', $post_id ),
            'dateModified'  => get_post_modified_time( 'c', true, $post_id ),
            'author'        => [
                '@type' => 'Person',
                'name'  => get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) )
            ],
            'image'         => $this->get_featured_image_url( $post_id ),
            'publisher'     => [ '@id' => $this->home_url . '#organization' ]
        ];
        $this->add_webpage_to_graph( $post_id );
    }

    private function add_webpage_to_graph( int $post_id ): void {
        $this->graph['webpage'] = [
            '@type'      => 'WebPage',
            '@id'        => get_permalink( $post_id ) . '#webpage',
            'url'        => get_permalink( $post_id ),
            'name'       => get_the_title( $post_id ),
            'isPartOf'   => [ '@id' => $this->home_url . '#website' ],
            'breadcrumb' => [ '@id' => get_permalink( $post_id ) . '#breadcrumb' ]
        ];
    }

    private function add_breadcrumb_to_graph(): void {
        $items = PLSEO_Breadcrumbs::get_items();
        if ( empty( $items ) ) return;

        $list_items = [];
        foreach ( array_values( $items ) as $index => $item ) {
            $list_items[] = [
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'item'     => [
                    '@id'  => $item['url'] ?? $this->home_url,
                    'name' => $item['name']
                ]
            ];
        }

        $this->graph['breadcrumb'] = [
            '@type'           => 'BreadcrumbList',
            '@id'             => PLSEO_Canonical::get_instance()->get_canonical_url() . '#breadcrumb',
            'itemListElement' => $list_items
        ];
    }

    private function get_featured_image_url( int $post_id ): string {
        $img = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
        return $img ? $img[0] : '';
    }

    private function add_local_business_to_graph(): void {
        $business_type = (string) PLSEO_Helpers::get_option( 'schema_lb_type', 'LocalBusiness' );
        $address       = (string) PLSEO_Helpers::get_option( 'schema_lb_address', '' );
        $phone         = (string) PLSEO_Helpers::get_option( 'schema_lb_phone', '' );
        $hours         = (string) PLSEO_Helpers::get_option( 'schema_lb_hours', '' );

        $local_business = [
            '@type'     => $business_type ?: 'LocalBusiness',
            '@id'       => $this->home_url . '#localbusiness',
            'name'      => $this->site_name,
            'url'       => $this->home_url,
            'telephone' => $phone,
            'address'   => $address ? [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $address,
            ] : null,
            'openingHours' => $hours,
        ];

        $this->graph['localbusiness'] = $this->filter_graph_values( $local_business );
    }

    private function filter_graph_values( mixed $value ): mixed {
        if ( ! is_array( $value ) ) {
            return $value;
        }

        $filtered = [];
        foreach ( $value as $key => $item ) {
            if ( is_array( $item ) ) {
                $item = $this->filter_graph_values( $item );
                if ( [] === $item ) {
                    continue;
                }
            } elseif ( null === $item || '' === $item ) {
                continue;
            }
            $filtered[ $key ] = $item;
        }

        return $filtered;
    }
}