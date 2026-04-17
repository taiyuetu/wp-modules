<?php
defined( 'ABSPATH' ) || exit;

class PLSEO_Robots {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_filter( 'robots_txt', [ $this, 'filter_robots_txt' ], 20, 2 );
    }

    public function filter_robots_txt( string $output, bool $public ): string {
        if ( ! $public ) {
            return $output;
        }

        if ( PLSEO_Helpers::get_option( 'sitemap_enabled', true ) ) {
            $output .= "\nSitemap: " . home_url( 'sitemap.xml' ) . "\n";
        }

        $extra = trim( (string) PLSEO_Helpers::get_option( 'robots_txt_additions', '' ) );
        if ( '' !== $extra ) {
            $output .= "\n" . $extra . "\n";
        }

        return trim( $output ) . "\n";
    }
}
