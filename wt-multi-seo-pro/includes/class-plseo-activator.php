<?php
defined( 'ABSPATH' ) || exit;

class PLSEO_Activator {

    public static function activate(): void {
        // Set default options
        $defaults = PLSEO_Options::get_defaults();
        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }

        // Schedule sitemap regeneration
        if ( ! wp_next_scheduled( 'plseo_regenerate_sitemap' ) ) {
            wp_schedule_event( time(), 'daily', 'plseo_regenerate_sitemap' );
        }

        add_rewrite_rule( '^sitemap\.xml$', 'index.php?plseo_sitemap=index', 'top' );
        add_rewrite_rule( '^sitemap-([a-z0-9_-]+)\.xml$', 'index.php?plseo_sitemap=$matches[1]', 'top' );
        add_rewrite_tag( '%plseo_sitemap%', '([a-z0-9_-]+)' );

        // Flush rewrite rules so sitemap URLs register
        flush_rewrite_rules();

        update_option( 'plseo_db_version', PLSEO_DB_VERSION );
    }
}
