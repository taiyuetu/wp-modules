<?php
/**
 * Clean URL Rewriter
 * 
 * Removes taxonomy base slugs and custom post type slugs from permalink URLs.
 * Handles conflict detection between pages, posts, terms, and CPTs.
 * Uses transient caching for performance.
 * 
 * @package Taiyuetu
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 1: Configuration & Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get the list of taxonomies that should have their base removed.
 * Filters out taxonomies that should be excluded (e.g., post_format).
 *
 * @return string[] Array of taxonomy names.
 */
function taiyuetu_get_clean_url_taxonomies()
{
    $excluded = array(
        'post_format',       // Internal WP taxonomy
        'nav_menu',          // Navigation menus
        'link_category',     // Link categories (legacy)
        'wp_theme',          // Block themes
        'wp_template_part_area', // Template parts
    );

    /**
     * Filter the list of excluded taxonomies.
     *
     * @param string[] $excluded Taxonomy names to exclude from URL rewriting.
     */
    $excluded = apply_filters('taiyuetu_clean_url_excluded_taxonomies', $excluded);

    $public_taxonomies = get_taxonomies(array('public' => true), 'names');

    return array_diff($public_taxonomies, $excluded);
}

/**
 * Get the list of custom post types that should have their slug removed.
 * Only returns publicly queryable, non-built-in post types.
 *
 * @return string[] Array of post type names.
 */
function taiyuetu_get_clean_url_post_types()
{
    $excluded = array();

    /**
     * Filter the list of excluded post types.
     *
     * @param string[] $excluded Post type names to exclude from URL rewriting.
     */
    $excluded = apply_filters('taiyuetu_clean_url_excluded_post_types', $excluded);

    $post_types = get_post_types(array(
        'public' => true,
        '_builtin' => false,
    ), 'names');

    // Only include post types that are publicly queryable
    $result = array();
    foreach ($post_types as $pt) {
        $obj = get_post_type_object($pt);
        if ($obj && $obj->publicly_queryable) {
            $result[] = $pt;
        }
    }

    return array_diff($result, $excluded);
}

/**
 * Check if a slug is reserved by WordPress core, existing pages, or other entities.
 * Uses a combined check against WP reserved terms, registered post type slugs,
 * existing page slugs, and existing post slugs.
 *
 * @param string $slug   The slug to check.
 * @param string $context Optional. What type of entity this slug is for ('term' or 'cpt').
 * @return bool True if the slug is reserved/conflicting.
 */
function taiyuetu_is_reserved_slug($slug, $context = 'term')
{
    // WordPress core reserved slugs
    static $reserved_slugs = null;
    if ($reserved_slugs === null) {
        $reserved_slugs = array(
            'attachment',
            'attachment_id',
            'author',
            'author_name',
            'calendar',
            'cat',
            'category_name',
            'cpage',
            'day',
            'debug',
            'embed',
            'error',
            'exact',
            'feed',
            'hour',
            'link_category',
            'm',
            'minute',
            'monthnum',
            'more',
            'name',
            'nav_menu_item',
            'nopaging',
            'offset',
            'order',
            'orderby',
            'p',
            'page',
            'page_id',
            'paged',
            'pagename',
            'pb',
            'post_type',
            'preview',
            'robots',
            's',
            'search',
            'second',
            'sentence',
            'sitemap',
            'tag_id',
            'tb',
            'term',
            'terms',
            'theme',
            'title',
            'type',
            'w',
            'year',
            'comments_popup',
            'admin',
            'login',
            'register',
            'wp-admin',
            'wp-content',
            'wp-includes',
            'wp-json',
            'wp-login',
            'wp-register',
            'wp-signup',
            'comments',
            'trackback',
            'xmlrpc',
        );
    }

    if (in_array($slug, $reserved_slugs, true)) {
        return true;
    }

    // Check against registered post type slugs (the rewrite slug, not the post type name)
    $post_types = get_post_types(array('public' => true), 'objects');
    foreach ($post_types as $pt) {
        // Check both the post type name and its rewrite slug
        if ($pt->name === $slug) {
            return true;
        }
        if (!empty($pt->rewrite['slug']) && $pt->rewrite['slug'] === $slug) {
            return true;
        }
        // Check if slug matches archive slug
        if ($pt->has_archive) {
            $archive_slug = ($pt->has_archive === true) ? $pt->rewrite['slug'] ?? $pt->name : $pt->has_archive;
            if ($archive_slug === $slug) {
                return true;
            }
        }
    }

    // Check against registered taxonomy slugs
    if ($context === 'cpt') {
        $taxonomies = get_taxonomies(array('public' => true), 'objects');
        foreach ($taxonomies as $tax) {
            if (!empty($tax->rewrite['slug']) && $tax->rewrite['slug'] === $slug) {
                return true;
            }
        }
    }

    // Check against existing WordPress pages (only top-level)
    if ($context === 'term') {
        $page = get_page_by_path($slug);
        if ($page) {
            return true;
        }
    }

    return false;
}

/**
 * Build a map of all term slugs → taxonomy for conflict detection.
 * Cached via transient for performance.
 *
 * @return array Associative array: slug => array of taxonomy names that use it.
 */
function taiyuetu_get_term_slug_map()
{
    $cache_key = 'taiyuetu_term_slug_map';
    $map = get_transient($cache_key);

    if ($map !== false) {
        return $map;
    }

    $map = array();
    $taxonomies = taiyuetu_get_clean_url_taxonomies();

    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'fields' => 'id=>slug',
        ));

        if (!is_wp_error($terms)) {
            foreach ($terms as $term_id => $term_slug) {
                if (!isset($map[$term_slug])) {
                    $map[$term_slug] = array();
                }
                $map[$term_slug][] = $taxonomy;
            }
        }
    }

    // Cache for 12 hours — invalidated on term create/edit/delete
    set_transient($cache_key, $map, 12 * HOUR_IN_SECONDS);

    return $map;
}


// ─────────────────────────────────────────────────────────────────────────────
// SECTION 2: Taxonomy Base Removal
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Remove taxonomy base from permalink URL for all eligible taxonomies.
 *
 * @param string $permalink The full permalink.
 * @param object $term      The term object.
 * @param string $taxonomy  The taxonomy name.
 * @return string Modified permalink without taxonomy base.
 */
function taiyuetu_remove_taxonomy_base($permalink, $term, $taxonomy)
{
    $eligible_taxonomies = taiyuetu_get_clean_url_taxonomies();

    if (!in_array($taxonomy, $eligible_taxonomies, true)) {
        return $permalink;
    }

    // Skip reserved slugs — keep original permalink to avoid conflicts
    if (taiyuetu_is_reserved_slug($term->slug, 'term')) {
        return $permalink;
    }

    // Check for cross-taxonomy slug collisions:
    // If multiple taxonomies share this slug, only rewrite for the first one (by priority)
    $slug_map = taiyuetu_get_term_slug_map();
    if (isset($slug_map[$term->slug]) && count($slug_map[$term->slug]) > 1) {
        // Only rewrite for the first taxonomy that registered this slug
        $priority_taxonomy = $slug_map[$term->slug][0];
        if ($taxonomy !== $priority_taxonomy) {
            return $permalink;
        }
    }

    // Handle default category taxonomy
    if ($taxonomy === 'category') {
        $category_base = get_option('category_base');
        $category_base = $category_base ? $category_base : 'category';
        $permalink = str_replace('/' . $category_base . '/', '/', $permalink);
    } elseif ($taxonomy === 'post_tag') {
        $tag_base = get_option('tag_base');
        $tag_base = $tag_base ? $tag_base : 'tag';
        $permalink = str_replace('/' . $tag_base . '/', '/', $permalink);
    } else {
        // For custom taxonomies, build the clean URL
        // Preserve hierarchical path for child terms
        if (is_taxonomy_hierarchical($taxonomy) && $term->parent > 0) {
            $ancestors = get_ancestors($term->term_id, $taxonomy, 'taxonomy');
            $path_parts = array();
            foreach (array_reverse($ancestors) as $ancestor_id) {
                $ancestor = get_term($ancestor_id, $taxonomy);
                if ($ancestor && !is_wp_error($ancestor)) {
                    $path_parts[] = $ancestor->slug;
                }
            }
            $path_parts[] = $term->slug;
            $permalink = home_url('/' . implode('/', $path_parts) . '/');
        } else {
            $permalink = home_url('/' . $term->slug . '/');
        }
    }

    return $permalink;
}
add_filter('term_link', 'taiyuetu_remove_taxonomy_base', 10, 3);


// ─────────────────────────────────────────────────────────────────────────────
// SECTION 3: Taxonomy Rewrite Rules
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Generate rewrite rules for taxonomy terms without base slugs.
 * Uses cached term data and proper regex escaping.
 */
function taiyuetu_taxonomy_rewrite_rules()
{
    $taxonomies = taiyuetu_get_clean_url_taxonomies();
    $slug_map = taiyuetu_get_term_slug_map();

    // Track which slugs already have rules to prevent duplicates
    $registered_slugs = array();

    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ));

        if (empty($terms) || is_wp_error($terms)) {
            continue;
        }

        foreach ($terms as $term) {
            // Skip reserved slugs
            if (taiyuetu_is_reserved_slug($term->slug, 'term')) {
                continue;
            }

            // Handle cross-taxonomy slug collision — only first taxonomy wins
            if (isset($slug_map[$term->slug]) && count($slug_map[$term->slug]) > 1) {
                if ($slug_map[$term->slug][0] !== $taxonomy) {
                    continue;
                }
            }

            // Regex-escape the slug to prevent special characters from breaking rules
            $escaped_slug = preg_quote($term->slug, '/');

            // Determine the query var
            if ($taxonomy === 'category') {
                $query_var = 'category_name';
            } elseif ($taxonomy === 'post_tag') {
                $query_var = 'tag';
            } else {
                $query_var = $taxonomy;
            }

            // Build the URL prefix (just slug for top-level, parent/child for hierarchical)
            $url_prefix = $escaped_slug;
            $raw_prefix = $term->slug;

            if (is_taxonomy_hierarchical($taxonomy) && $term->parent > 0) {
                $ancestors = get_ancestors($term->term_id, $taxonomy, 'taxonomy');
                $path_parts = array();
                $raw_parts = array();
                foreach (array_reverse($ancestors) as $ancestor_id) {
                    $ancestor = get_term($ancestor_id, $taxonomy);
                    if ($ancestor && !is_wp_error($ancestor)) {
                        $path_parts[] = preg_quote($ancestor->slug, '/');
                        $raw_parts[] = $ancestor->slug;
                    }
                }
                $path_parts[] = $escaped_slug;
                $raw_parts[] = $term->slug;
                $url_prefix = implode('/', $path_parts);
                $raw_prefix = implode('/', $raw_parts);
            }

            // Prevent duplicate rules for the same URL prefix
            if (in_array($raw_prefix, $registered_slugs, true)) {
                continue;
            }
            $registered_slugs[] = $raw_prefix;

            // Main term page
            add_rewrite_rule(
                '^' . $url_prefix . '/?$',
                'index.php?' . $query_var . '=' . $term->slug,
                'top'
            );

            // Pagination
            add_rewrite_rule(
                '^' . $url_prefix . '/page/([0-9]{1,})/?$',
                'index.php?' . $query_var . '=' . $term->slug . '&paged=$matches[1]',
                'top'
            );

            // Feed support
            add_rewrite_rule(
                '^' . $url_prefix . '/feed/(feed|rdf|rss|rss2|atom)/?$',
                'index.php?' . $query_var . '=' . $term->slug . '&feed=$matches[1]',
                'top'
            );

            // Default feed
            add_rewrite_rule(
                '^' . $url_prefix . '/(feed|rdf|rss|rss2|atom)/?$',
                'index.php?' . $query_var . '=' . $term->slug . '&feed=$matches[1]',
                'top'
            );

            // Embed support
            add_rewrite_rule(
                '^' . $url_prefix . '/embed/?$',
                'index.php?' . $query_var . '=' . $term->slug . '&embed=true',
                'top'
            );

            // For top-level terms from hierarchical taxonomies, also add the flat slug rule
            // so both /parent/child/ and /child/ work (if no collision)
            if (is_taxonomy_hierarchical($taxonomy) && $term->parent > 0) {
                if (
                    !in_array($term->slug, $registered_slugs, true)
                    && !taiyuetu_is_reserved_slug($term->slug, 'term')
                    && !(isset($slug_map[$term->slug]) && count($slug_map[$term->slug]) > 1)
                ) {
                    add_rewrite_rule(
                        '^' . $escaped_slug . '/?$',
                        'index.php?' . $query_var . '=' . $term->slug,
                        'top'
                    );
                    add_rewrite_rule(
                        '^' . $escaped_slug . '/page/([0-9]{1,})/?$',
                        'index.php?' . $query_var . '=' . $term->slug . '&paged=$matches[1]',
                        'top'
                    );
                }
            }
        }
    }
}
add_action('init', 'taiyuetu_taxonomy_rewrite_rules', 10);


// ─────────────────────────────────────────────────────────────────────────────
// SECTION 4: Custom Post Type Slug Removal
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Remove custom post type slug from permalink.
 *
 * @param string  $post_link The post permalink.
 * @param WP_Post $post      The post object.
 * @return string Modified permalink.
 */
function taiyuetu_remove_cpt_slug($post_link, $post)
{
    $clean_post_types = taiyuetu_get_clean_url_post_types();

    if (!in_array($post->post_type, $clean_post_types, true)) {
        return $post_link;
    }

    // Only rewrite for published posts — drafts/pending/etc. should keep the slug
    // to avoid issues with preview URLs
    if ($post->post_status !== 'publish') {
        return $post_link;
    }

    // Check if the post slug would conflict with an existing page
    $conflicting_page = get_page_by_path($post->post_name);
    if ($conflicting_page && $conflicting_page->ID !== $post->ID) {
        // Conflict with an existing page — keep the CPT slug in URL
        return $post_link;
    }

    // Check if the post slug would conflict with a taxonomy term
    $slug_map = taiyuetu_get_term_slug_map();
    if (isset($slug_map[$post->post_name])) {
        // Conflict with a taxonomy term — keep the CPT slug in URL
        return $post_link;
    }

    // Get the post type object to find the correct slug to remove
    $pt_object = get_post_type_object($post->post_type);
    if (!$pt_object) {
        return $post_link;
    }

    // Determine the slug used in the URL
    $rewrite_slug = $pt_object->rewrite['slug'] ?? $post->post_type;

    return str_replace('/' . $rewrite_slug . '/', '/', $post_link);
}
add_filter('post_type_link', 'taiyuetu_remove_cpt_slug', 10, 2);


/**
 * Parse incoming requests and resolve slugless CPT URLs to the correct post.
 * Uses a single efficient query instead of looping through each CPT.
 *
 * @param WP_Query $query The main query.
 */
function taiyuetu_parse_request_for_cpt($query)
{
    // Only modify the main front-end query
    if (!$query->is_main_query() || is_admin()) {
        return;
    }

    // Only handle requests that look like a single page/post (has 'name' or 'pagename')
    if (!isset($query->query['name']) && !isset($query->query['pagename'])) {
        return;
    }

    $path = isset($query->query['name'])
        ? trim($query->query['name'], '/')
        : trim($query->query['pagename'], '/');

    if (empty($path)) {
        return;
    }

    // Don't process paths that contain slashes (hierarchical) — could be page children
    // Unless there's no matching page, in which case we let it fall through
    if (strpos($path, '/') !== false) {
        return;
    }

    // First, check if a real page exists with this slug — pages take priority
    $existing_page = get_page_by_path($path);
    if ($existing_page) {
        return; // Let WP handle it as a normal page
    }

    // Check if it matches a taxonomy term — taxonomy terms take priority over CPT
    $slug_map = taiyuetu_get_term_slug_map();
    if (isset($slug_map[$path])) {
        return; // Rewrite rules will handle this as a taxonomy term
    }

    // Now check custom post types with a single efficient query
    $clean_post_types = taiyuetu_get_clean_url_post_types();
    if (empty($clean_post_types)) {
        return;
    }

    global $wpdb;

    // Single query to find matching post across all clean CPTs
    $placeholders = implode(',', array_fill(0, count($clean_post_types), '%s'));
    $query_args = array_merge(array($path), $clean_post_types);

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $result = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT ID, post_type FROM {$wpdb->posts} 
             WHERE post_name = %s 
             AND post_type IN ({$placeholders}) 
             AND post_status = 'publish' 
             LIMIT 1",
            ...$query_args
        )
    );

    if ($result) {
        $query->set('post_type', $result->post_type);
        $query->set('name', $path);
        // Clear pagename to avoid WP trying to find a page
        $query->set('pagename', '');
        // Mark that this was resolved as a CPT
        $query->set('taiyuetu_cpt_resolved', true);
    }
}
add_action('pre_get_posts', 'taiyuetu_parse_request_for_cpt');

/**
 * Handle 404 fallback — if WordPress returns a 404, try to resolve as a CPT post.
 * This catches edge cases where pre_get_posts didn't fire correctly.
 *
 * @param bool     $preempt Whether to short-circuit.
 * @param WP_Query $query   The WP_Query instance.
 * @return bool
 */
function taiyuetu_handle_404_fallback($preempt, $query)
{
    if ($preempt) {
        return $preempt;
    }

    // Only on front-end 404s for the main query
    if (!$query->is_main_query() || is_admin() || !$query->is_404()) {
        return $preempt;
    }

    // Already resolved by our code
    if ($query->get('taiyuetu_cpt_resolved')) {
        return $preempt;
    }

    $request = trim($query->query_vars['name'] ?? '', '/');
    if (empty($request) || strpos($request, '/') !== false) {
        return $preempt;
    }

    $clean_post_types = taiyuetu_get_clean_url_post_types();
    if (empty($clean_post_types)) {
        return $preempt;
    }

    global $wpdb;
    $placeholders = implode(',', array_fill(0, count($clean_post_types), '%s'));
    $query_args = array_merge(array($request), $clean_post_types);

    $result = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT ID, post_type FROM {$wpdb->posts} 
             WHERE post_name = %s 
             AND post_type IN ({$placeholders}) 
             AND post_status = 'publish' 
             LIMIT 1",
            ...$query_args
        )
    );

    if ($result) {
        $query->set('post_type', $result->post_type);
        $query->set('name', $request);
        $query->set('pagename', '');
        $query->is_404 = false;
        $query->is_single = true;
        $query->is_singular = true;
        return false;
    }

    return $preempt;
}
add_filter('pre_handle_404', 'taiyuetu_handle_404_fallback', 10, 2);


// ─────────────────────────────────────────────────────────────────────────────
// SECTION 5: Cache Invalidation & Rewrite Flushing
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Clear cached data and set flush flag when terms change.
 * 
 * @param int    $term_id  Term ID.
 * @param int    $tt_id    Term taxonomy ID (optional).
 * @param string $taxonomy Taxonomy slug (optional).
 */
function taiyuetu_invalidate_url_caches($term_id = 0, $tt_id = 0, $taxonomy = '')
{
    // Clear the term slug map transient
    delete_transient('taiyuetu_term_slug_map');

    // Set flag to flush rewrite rules on next admin page load
    update_option('taiyuetu_clean_url_flush_needed', 'yes', false); // autoload = false
}
add_action('created_term', 'taiyuetu_invalidate_url_caches', 10, 3);
add_action('edited_term', 'taiyuetu_invalidate_url_caches', 10, 3);
add_action('delete_term', 'taiyuetu_invalidate_url_caches', 10, 3);

/**
 * Also invalidate when permalink structure changes.
 */
function taiyuetu_invalidate_on_permalink_change()
{
    taiyuetu_invalidate_url_caches();
}
add_action('permalink_structure_changed', 'taiyuetu_invalidate_on_permalink_change');

/**
 * Also invalidate when a post is published, updated, or trashed.
 * This catches CPT slug conflicts that might arise.
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Old post status.
 * @param WP_Post $post       Post object.
 */
function taiyuetu_invalidate_on_post_change($new_status, $old_status, $post)
{
    // Only care about status transitions involving 'publish'
    if ($new_status === 'publish' || $old_status === 'publish') {
        $clean_post_types = taiyuetu_get_clean_url_post_types();
        if (in_array($post->post_type, $clean_post_types, true)) {
            update_option('taiyuetu_clean_url_flush_needed', 'yes', false);
        }
    }
}
add_action('transition_post_status', 'taiyuetu_invalidate_on_post_change', 10, 3);

/**
 * Conditionally flush rewrite rules on admin_init.
 * Only flushes when the flag is set, avoiding expensive operations on every load.
 */
function taiyuetu_maybe_flush_rewrite_rules()
{
    if (get_option('taiyuetu_clean_url_flush_needed', 'no') === 'yes') {
        // Re-register rules first (init already fired, but we need fresh rules)
        taiyuetu_taxonomy_rewrite_rules();
        flush_rewrite_rules();
        update_option('taiyuetu_clean_url_flush_needed', 'no', false);
    }
}
add_action('admin_init', 'taiyuetu_maybe_flush_rewrite_rules');

/**
 * Also flush when switching themes (to ensure rules are fresh).
 */
function taiyuetu_flush_on_theme_switch()
{
    taiyuetu_invalidate_url_caches();
}
add_action('after_switch_theme', 'taiyuetu_flush_on_theme_switch');


// ─────────────────────────────────────────────────────────────────────────────
// SECTION 6: Admin Notices for Slug Conflicts
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Display admin notice when slug conflicts are detected.
 * Only checks on taxonomy and post edit screens for performance.
 */
function taiyuetu_slug_conflict_admin_notice()
{
    $screen = get_current_screen();
    if (!$screen) {
        return;
    }

    // Only check on relevant admin screens
    $check_screens = array('edit-tags', 'term', 'post');
    if (!in_array($screen->base, $check_screens, true)) {
        return;
    }

    $conflicts = taiyuetu_detect_slug_conflicts();
    if (empty($conflicts)) {
        return;
    }

    echo '<div class="notice notice-warning is-dismissible">';
    echo '<p><strong>' . esc_html__('Clean URL Rewriter: Potential slug conflicts detected:', 'taiyuetu') . '</strong></p>';
    echo '<ul style="list-style: disc; padding-left: 20px;">';
    foreach ($conflicts as $conflict) {
        echo '<li>' . esc_html($conflict) . '</li>';
    }
    echo '</ul>';
    echo '<p>' . esc_html__('Conflicting URLs will retain their original structure to prevent errors.', 'taiyuetu') . '</p>';
    echo '</div>';
}
add_action('admin_notices', 'taiyuetu_slug_conflict_admin_notice');

/**
 * Detect slug conflicts between taxonomy terms, pages, and CPT posts.
 * Results are cached in a short-lived transient.
 *
 * @return string[] Array of human-readable conflict descriptions.
 */
function taiyuetu_detect_slug_conflicts()
{
    $cache_key = 'taiyuetu_slug_conflicts';
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $conflicts = array();
    $slug_map = taiyuetu_get_term_slug_map();

    // Check for cross-taxonomy slug collisions
    foreach ($slug_map as $slug => $taxonomies) {
        if (count($taxonomies) > 1) {
            $conflicts[] = sprintf(
                /* translators: %1$s: slug, %2$s: comma-separated taxonomy names */
                __('Slug "%1$s" is used by multiple taxonomies: %2$s. Only "%3$s" will use the clean URL.', 'taiyuetu'),
                $slug,
                implode(', ', $taxonomies),
                $taxonomies[0]
            );
        }
    }

    // Check for term vs page collisions
    foreach ($slug_map as $slug => $taxonomies) {
        $page = get_page_by_path($slug);
        if ($page) {
            $conflicts[] = sprintf(
                /* translators: %1$s: slug, %2$s: taxonomy name */
                __('Term slug "%1$s" (taxonomy: %2$s) conflicts with an existing page. The term will keep its base URL.', 'taiyuetu'),
                $slug,
                implode(', ', $taxonomies)
            );
        }
    }

    // Cache for 1 hour
    set_transient($cache_key, $conflicts, HOUR_IN_SECONDS);

    return $conflicts;
}