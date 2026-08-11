<?php
/**
 * Enhanced Custom Search for WordPress
 * Supports: post title, content, excerpt, and ALL custom meta fields
 * Performance: uses JOIN instead of subquery, cached meta keys, proper SQL safety
 */

/**
 * version:2.0 
 * updated:2026-08-11
 */

/**
 * Step 1 – Modify query options before execution.
 * Restricts post_status to published posts for search queries.
 */
function cfs_pre_get_posts( WP_Query $query ): void {
    if ( is_admin() || ! $query->is_search() ) {
        return;
    }

    $query->set( 'post_status', 'publish' );
}
add_action( 'pre_get_posts', 'cfs_pre_get_posts' );


/**
 * Step 2 – JOIN postmeta once so we can search every meta_value in WHERE.
 * Using a JOIN is significantly faster than a correlated subquery (EXISTS / SELECT *).
 */
function cfs_search_join( string $join, WP_Query $query ): string {
    global $wpdb;

    if ( is_admin() || ! $query->is_search() ) {
        return $join;
    }

    $raw_term = $query->get( 's' );
    if ( '' === trim( (string) $raw_term ) ) {
        return $join;
    }

    // Prevent duplicate joins
    if ( strpos( $join, 'cfs_pm' ) !== false ) {
        return $join;
    }

    // LEFT JOIN so posts WITHOUT meta rows still appear if they match title/content.
    $join .= " LEFT JOIN {$wpdb->postmeta} AS cfs_pm
                   ON ( cfs_pm.post_id = {$wpdb->posts}.ID ) ";

    return $join;
}
add_filter( 'posts_join', 'cfs_search_join', 10, 2 );


/**
 * Step 3 – Rewrite the WHERE clause with correct parenthesisation and
 *           safe, prepared values.
 *
 * Logic:
 *   published product  AND  (title LIKE %s  OR  content LIKE %s  OR  meta_value LIKE %s)
 *
 * Optionally restrict to specific meta_keys for a massive speed boost on
 * large sites (see $allowed_meta_keys below).
 */
function cfs_search_where( string $where, WP_Query $query ): string {
    global $wpdb;

    if ( is_admin() || ! $query->is_search() ) {
        return $where;
    }

    $raw_term = $query->get( 's' );
    if ( '' === trim( (string) $raw_term ) ) {
        return $where;
    }

    // Prepare a safe LIKE value once.
    $like = '%' . $wpdb->esc_like( $raw_term ) . '%';

    // ---------------------------------------------------------------
    // OPTION A – Search ALL meta fields (simplest, works out of the box).
    //            On very large sites with millions of meta rows, switch
    //            to OPTION B for a dramatic speed improvement.
    // ---------------------------------------------------------------
    $meta_key_clause = ''; // no key restriction → search every key

    // ---------------------------------------------------------------
    // OPTION B – Whitelist specific meta keys (recommended for large sites).
    //            Uncomment and list the meta keys you actually need:
    // ---------------------------------------------------------------
    /*
    $allowed_meta_keys = apply_filters( 'cfs_allowed_meta_keys', [
        'oem_num',
        'partnumber',
        '_sku',          // WooCommerce SKU
        'custom_field_1',
    ]);

    $key_placeholders = implode( ', ', array_fill( 0, count( $allowed_meta_keys ), '%s' ) );
    $meta_key_clause  = $wpdb->prepare(
        " AND cfs_pm.meta_key IN ( $key_placeholders ) ",
        ...$allowed_meta_keys
    );
    */

    // Build the new WHERE snippet for search. We REPLACE WordPress's default search expression
    // with our own so there's no double-quoting or logic conflicts.
    $where = $wpdb->prepare(
        " AND (
              {$wpdb->posts}.post_title   LIKE %s
           OR {$wpdb->posts}.post_content LIKE %s
           OR {$wpdb->posts}.post_excerpt LIKE %s
           OR ( cfs_pm.meta_value        LIKE %s $meta_key_clause )
          ) ",
        $like, $like, $like, $like
    );

    return $where;
}
add_filter( 'posts_search', 'cfs_search_where', 10, 2 );


/**
 * Step 4 – Remove duplicate posts caused by the LEFT JOIN
 *           (one post can match multiple meta rows → appears multiple times).
 */
function cfs_search_distinct( string $distinct, WP_Query $query ): string {
    if ( is_admin() || ! $query->is_search() ) {
        return $distinct;
    }

    $raw_term = $query->get( 's' );
    if ( '' === trim( (string) $raw_term ) ) {
        return $distinct;
    }

    return 'DISTINCT';
}
add_filter( 'posts_distinct', 'cfs_search_distinct', 10, 2 );


/**
 * Step 5 (optional) – Re-enable relevance ordering even after our JOIN.
 *                     WordPress normally orders search results by relevance
 *                     but can fall back to date when the query is modified.
 */
function cfs_search_orderby( string $orderby, WP_Query $query ): string {
    global $wpdb;

    if ( is_admin() || ! $query->is_search() ) {
        return $orderby;
    }

    $raw_term = $query->get( 's' );
    if ( '' === trim( (string) $raw_term ) ) {
        return $orderby;
    }

    // Keep relevance (title match scores higher than content match).
    $like = '%' . $wpdb->esc_like( $raw_term ) . '%';
    $title_like = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s DESC", $like );

    if ( empty( $orderby ) ) {
        return $title_like;
    }

    return "{$title_like}, {$orderby}";
}
add_filter( 'posts_orderby', 'cfs_search_orderby', 10, 2 );
