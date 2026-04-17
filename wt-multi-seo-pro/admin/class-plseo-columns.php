<?php
defined( 'ABSPATH' ) || exit;

class PLSEO_Columns {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        foreach ( get_post_types( [ 'public' => true ], 'names' ) as $post_type ) {
            add_filter( "manage_{$post_type}_posts_columns", [ $this, 'add_columns' ] );
            add_action( "manage_{$post_type}_posts_custom_column", [ $this, 'render_column' ], 10, 2 );
        }
    }

    public function add_columns( array $columns ): array {
        $columns['plseo_status'] = esc_html__( 'SEO', 'polylang-seo' );
        return $columns;
    }

    public function render_column( string $column, int $post_id ): void {
        if ( 'plseo_status' !== $column ) {
            return;
        }

        if ( get_post_meta( $post_id, '_plseo_noindex', true ) ) {
            echo '<span style="color:#d63638;font-weight:600;">noindex</span>';
            return;
        }

        $score = 0;
        if ( get_post_meta( $post_id, '_plseo_meta_title', true ) ) {
            $score++;
        }
        if ( get_post_meta( $post_id, '_plseo_meta_description', true ) ) {
            $score++;
        }

        if ( 2 === $score ) {
            echo '<span style="color:#008a20;font-weight:600;">good</span>';
        } elseif ( 1 === $score ) {
            echo '<span style="color:#b26200;font-weight:600;">partial</span>';
        } else {
            echo '<span style="color:#d63638;font-weight:600;">missing</span>';
        }
    }
}
