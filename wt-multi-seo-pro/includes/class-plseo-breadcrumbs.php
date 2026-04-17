<?php
defined( 'ABSPATH' ) || exit;

class PLSEO_Breadcrumbs {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'plseo_breadcrumbs', [ $this, 'render_shortcode' ] );
    }

    public static function get_items(): array {
        $items = [
            [
                'name' => __( 'Home', 'polylang-seo' ),
                'url'  => home_url( '/' ),
            ],
        ];

        if ( is_singular() ) {
            $post = get_post();
            if ( ! $post ) {
                return $items;
            }

            if ( 'post' === $post->post_type ) {
                $categories = get_the_category( $post->ID );
                if ( ! empty( $categories ) ) {
                    $items[] = [
                        'name' => $categories[0]->name,
                        'url'  => get_category_link( $categories[0]->term_id ),
                    ];
                }
            }

            foreach ( array_reverse( get_post_ancestors( $post->ID ) ) as $ancestor_id ) {
                $items[] = [
                    'name' => get_the_title( $ancestor_id ),
                    'url'  => get_permalink( $ancestor_id ),
                ];
            }

            $items[] = [
                'name' => get_the_title( $post->ID ),
                'url'  => '',
            ];
        } elseif ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term ) {
                $parents = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
                foreach ( $parents as $parent_id ) {
                    $parent = get_term( $parent_id, $term->taxonomy );
                    if ( $parent instanceof WP_Term && ! is_wp_error( $parent ) ) {
                        $items[] = [
                            'name' => $parent->name,
                            'url'  => get_term_link( $parent ),
                        ];
                    }
                }

                $items[] = [
                    'name' => $term->name,
                    'url'  => '',
                ];
            }
        }

        return $items;
    }

    public function render_shortcode(): string {
        $items = self::get_items();
        if ( empty( $items ) ) {
            return '';
        }

        $separator = apply_filters( 'plseo_breadcrumb_separator', '/' );
        $output    = '<nav class="plseo-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'polylang-seo' ) . '"><ol>';

        foreach ( $items as $index => $item ) {
            $is_last = $index === count( $items ) - 1;
            $output .= '<li>';

            if ( ! $is_last && ! empty( $item['url'] ) ) {
                $output .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
            } else {
                $output .= '<span>' . esc_html( $item['name'] ) . '</span>';
            }

            $output .= '</li>';

            if ( ! $is_last ) {
                $output .= '<li class="plseo-breadcrumb-separator" aria-hidden="true">' . esc_html( $separator ) . '</li>';
            }
        }

        $output .= '</ol></nav>';

        return $output;
    }
}
