<?php
/**
 * Clean URL Rewriter
 * 
 * Removes taxonomy base slugs and custom post type slugs from permalink URLs.
 * Handles conflict detection between pages, posts, terms, and CPTs.
 * Uses transient caching for performance.
 * Fully integrated with Polylang (hides default language prefix, adds non-default prefixes).
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
 * Check if Polylang is fully active and ready to use.
 * We cache the result per-request because function_exists is cheap but
 * the semantic "is Polylang usable" check should be in one place.
 *
 * @return bool
 */
function taiyuetu_is_polylang_active()
{
    static $active = null;
    if ($active === null) {
        $active = function_exists('pll_languages_list')
            && function_exists('pll_default_language')
            && function_exists('pll_current_language');
    }
    return $active;
}

/**
 * Get the Polylang default language slug, or empty string if Polylang is not active.
 *
 * @return string
 */
function taiyuetu_pll_default_lang()
{
    static $lang = null;
    if ($lang === null) {
        $lang = taiyuetu_is_polylang_active() ? pll_default_language('slug') : '';
    }
    return $lang;
}

/**
 * Get the list of non-default Polylang language slugs.
 * Returns empty array if Polylang is not active.
 *
 * @return string[]
 */
function taiyuetu_pll_non_default_languages()
{
    if (!taiyuetu_is_polylang_active()) {
        return array();
    }

    $all = pll_languages_list(array('fields' => 'slug'));
    if (!is_array($all)) {
        return array();
    }

    $default = taiyuetu_pll_default_lang();
    return array_values(array_filter($all, function ($slug) use ($default) {
        return $slug !== $default;
    }));
}

/**
 * Whether Polylang is configured to hide the default language from URLs.
 * This is the most common configuration (Settings > Languages > URL modifications).
 * When "hide" is on, the default language has no /en/ prefix; other languages get /zh/, /ja/, etc.
 *
 * @return bool
 */
function taiyuetu_pll_hides_default_lang()
{
    if (!taiyuetu_is_polylang_active()) {
        return false;
    }
    $options = get_option('polylang', array());
    // Polylang `hide_default` is 1 when the default language code is hidden from URLs
    return !empty($options['hide_default']);
}

/**
 * Get the list of taxonomies that should have their base removed.
 * Filters out taxonomies that should be excluded (e.g., post_format).
 *
 * @return string[] Array of taxonomy names.
 */
function taiyuetu_get_clean_url_taxonomies()
{
    $excluded = array(
        'post_format', // Internal WP taxonomy
        'nav_menu', // Navigation menus
        'link_category', // Link categories (legacy)
        'wp_theme', // Block themes
        'wp_template_part_area', // Template parts
        'language', // Polylang internal taxonomy
        'post_translations', // Polylang translations
        'term_translations', // Polylang term translations
        'term_language', // Polylang term language
    );

    /**
     * Filter the list of excluded taxonomies.
     *
     * @param string[] $excluded Taxonomy names to exclude from URL rewriting.
     */
    $excluded = apply_filters('taiyuetu_clean_url_excluded_taxonomies', $excluded);

    $public_taxonomies = get_taxonomies(array('public' => true), 'names');

    $taxonomies = array_values(array_diff($public_taxonomies, $excluded));

    /**
     * Control which taxonomy "wins" when the same slug exists in multiple taxonomies.
     * Earlier entries in the array take precedence for clean (base-stripped) URLs.
     *
     * @param string[] $taxonomies Public taxonomies eligible for clean URLs.
     */
    return apply_filters('taiyuetu_clean_url_taxonomy_order', $taxonomies);
}

/**
 * Prefer product_category over default category / tags when resolving slug collisions.
 *
 * @param string[] $taxonomies Ordered taxonomy names from taiyuetu_get_clean_url_taxonomies().
 * @return string[]
 */
function taiyuetu_prioritize_product_category_taxonomy($taxonomies)
{
    $priority = array('product_category');
    $first = array();
    foreach ($priority as $p) {
        if (in_array($p, $taxonomies, true)) {
            $first[] = $p;
        }
    }
    $rest = array_diff($taxonomies, $first);

    return array_merge($first, array_values($rest));
}
add_filter('taiyuetu_clean_url_taxonomy_order', 'taiyuetu_prioritize_product_category_taxonomy', 5);

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
 * @param bool $force_refresh Force rebuild the cache.
 * @return array Associative array: slug => array of taxonomy names that use it.
 */
function taiyuetu_get_term_slug_map($force_refresh = false)
{
    $cache_key = 'taiyuetu_term_slug_map';

    if (!$force_refresh) {
        $map = get_transient($cache_key);
        if ($map !== false) {
            return $map;
        }
    }

    $map = array();
    $taxonomies = taiyuetu_get_clean_url_taxonomies();

    foreach ($taxonomies as $taxonomy) {
        // Bypass Polylang language filtering — we want ALL terms across ALL languages
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'fields' => 'id=>slug',
            'lang' => '', // Polylang: fetch all languages
        ));

        if (!is_wp_error($terms)) {
            foreach ($terms as $term_id => $term_slug) {
                if (!isset($map[$term_slug])) {
                    $map[$term_slug] = array();
                }
                if (!in_array($taxonomy, $map[$term_slug], true)) {
                    $map[$term_slug][] = $taxonomy;
                }
            }
        }
    }

    // Cache for 6 hours — invalidated on term create/edit/delete
    set_transient($cache_key, $map, 6 * HOUR_IN_SECONDS);

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
    }
    elseif ($taxonomy === 'post_tag') {
        $tag_base = get_option('tag_base');
        $tag_base = $tag_base ? $tag_base : 'tag';
        $permalink = str_replace('/' . $tag_base . '/', '/', $permalink);
    }
    else {
        // For custom taxonomies, remove the taxonomy base slug from the URL
        $tax_object = get_taxonomy($taxonomy);
        if ($tax_object && !empty($tax_object->rewrite['slug'])) {
            $tax_slug = $tax_object->rewrite['slug'];
        }
        else {
            $tax_slug = $taxonomy;
        }
        $permalink = str_replace('/' . $tax_slug . '/', '/', $permalink);
    }

    return $permalink;
}
add_filter('term_link', 'taiyuetu_remove_taxonomy_base', 999, 3);


// ─────────────────────────────────────────────────────────────────────────────
// SECTION 3: Taxonomy Rewrite Rules
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Generate rewrite rules for taxonomy terms without base slugs.
 * Uses cached term data and proper regex escaping.
 * Properly integrates with Polylang:
 *   - Default language: no prefix, no &lang= parameter (Polylang detects it)
 *   - Non-default languages: /lang-slug/ prefix + &lang= parameter
 *   - When Polylang is not active: only bare rules (no language prefixes)
 */
function taiyuetu_taxonomy_rewrite_rules()
{
    $taxonomies = taiyuetu_get_clean_url_taxonomies();
    $slug_map = taiyuetu_get_term_slug_map();
    $pll_active = taiyuetu_is_polylang_active();
    $non_default_langs = taiyuetu_pll_non_default_languages();

    // Track which slugs already have rules to prevent duplicates
    $registered_slugs = array();

    foreach ($taxonomies as $taxonomy) {
        // Use `lang => ''` to get terms from ALL languages when Polylang is active
        $term_args = array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        );
        if ($pll_active) {
            $term_args['lang'] = ''; // Bypass Polylang language filtering
        }

        $terms = get_terms($term_args);

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
            }
            elseif ($taxonomy === 'post_tag') {
                $query_var = 'tag';
            }
            else {
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

            /**
             * Helper closure to add the rewrite rules for a given term.
             *
             * @param string $rule_prefix  Regex prefix for the rewrite rule.
             * @param string $lang_query   Additional query string for language parameter.
             */
            $add_term_rules = function ($rule_prefix, $lang_query) use ($query_var, $term) {
                // Main term page
                add_rewrite_rule(
                    '^' . $rule_prefix . '/?$',
                    'index.php?' . $query_var . '=' . $term->slug . $lang_query,
                    'top'
                );

                // Pagination
                add_rewrite_rule(
                    '^' . $rule_prefix . '/page/([0-9]{1,})/?$',
                    'index.php?' . $query_var . '=' . $term->slug . '&paged=$matches[1]' . $lang_query,
                    'top'
                );

                // Feed support
                add_rewrite_rule(
                    '^' . $rule_prefix . '/feed/(feed|rdf|rss|rss2|atom)/?$',
                    'index.php?' . $query_var . '=' . $term->slug . '&feed=$matches[1]' . $lang_query,
                    'top'
                );

                // Default feed
                add_rewrite_rule(
                    '^' . $rule_prefix . '/(feed|rdf|rss|rss2|atom)/?$',
                    'index.php?' . $query_var . '=' . $term->slug . '&feed=$matches[1]' . $lang_query,
                    'top'
                );

                // Embed support
                add_rewrite_rule(
                    '^' . $rule_prefix . '/embed/?$',
                    'index.php?' . $query_var . '=' . $term->slug . '&embed=true' . $lang_query,
                    'top'
                );
            };

            // ── DEFAULT LANGUAGE / NO POLYLANG ──
            // Bare rules (no language prefix, no &lang= param).
            // When Polylang hides the default language from URL, the default language
            // pages are served at the bare URL (e.g., /wheel-hub-bearings/).
            // When Polylang is not active, same bare rules apply.
            $add_term_rules($url_prefix, '');

            // ── NON-DEFAULT POLYLANG LANGUAGES ──
            // Language-prefixed rules, e.g. /zh/wheel-hub-bearings → &lang=zh
            if ($pll_active) {
                foreach ($non_default_langs as $language_slug) {
                    $escaped_language_slug = preg_quote($language_slug, '/');
                    $add_term_rules(
                        $escaped_language_slug . '/' . $url_prefix,
                        '&lang=' . $language_slug
                    );
                }
            }

            // For child terms from hierarchical taxonomies, also add the flat slug rule
            // so both /parent/child/ and /child/ work (if no collision)
            if (is_taxonomy_hierarchical($taxonomy) && $term->parent > 0) {
                if (
                !in_array($term->slug, $registered_slugs, true)
                && !taiyuetu_is_reserved_slug($term->slug, 'term')
                && !(isset($slug_map[$term->slug]) && count($slug_map[$term->slug]) > 1)
                ) {
                    $add_term_rules($escaped_slug, '');
                    if ($pll_active) {
                        foreach ($non_default_langs as $language_slug) {
                            $escaped_language_slug = preg_quote($language_slug, '/');
                            $add_term_rules(
                                $escaped_language_slug . '/' . $escaped_slug,
                                '&lang=' . $language_slug
                            );
                        }
                    }
                }
            }
        }
    }
}
add_action('init', 'taiyuetu_taxonomy_rewrite_rules', 10);

/**
 * Fix: Ensure Polylang recognises our custom taxonomy queries as proper taxonomy queries.
 * 
 * When we rewrite /wheel-hub-bearings/ → index.php?product_category=wheel-hub-bearings,
 * Polylang may not know which language to assign if the term belongs to the default language.
 * This filter ensures that when a taxonomy query is resolved without a `lang` parameter
 * and Polylang is active, the query is treated correctly as the default language.
 *
 * This also fixes the 404 issue: when Polylang filters the query and cannot determine
 * the language for a custom taxonomy term, it may inadvertently return no results.
 * By explicitly telling Polylang "this is default language content", the 404 disappears.
 */
function taiyuetu_fix_polylang_taxonomy_query($query)
{
    if (!$query->is_main_query() || is_admin()) {
        return;
    }

    if (!taiyuetu_is_polylang_active()) {
        return;
    }

    // Get all custom taxonomies we manage
    $eligible_taxonomies = taiyuetu_get_clean_url_taxonomies();
    $found_tax = '';
    $found_term_slug = '';

    foreach ($eligible_taxonomies as $taxonomy) {
        $term_slug = $query->get($taxonomy);
        if (!empty($term_slug)) {
            $found_tax = $taxonomy;
            $found_term_slug = $term_slug;
            break;
        }
    }

    // Also check built-in taxonomies
    if (empty($found_tax)) {
        $cat = $query->get('category_name');
        if (!empty($cat)) {
            $found_tax = 'category';
            $found_term_slug = $cat;
        }
    }
    if (empty($found_tax)) {
        $tag = $query->get('tag');
        if (!empty($tag)) {
            $found_tax = 'post_tag';
            $found_term_slug = $tag;
        }
    }

    if (empty($found_tax)) {
        return;
    }

    // If `lang` is already set in the query, don't override it
    $lang_from_query = $query->get('lang');
    if (!empty($lang_from_query)) {
        return;
    }

    // Look up the term and determine its Polylang language
    $term = get_term_by('slug', $found_term_slug, $found_tax);
    if (!$term || is_wp_error($term)) {
        return;
    }

    // Get the language of this specific term from Polylang
    if (function_exists('pll_get_term_language')) {
        $term_lang = pll_get_term_language($term->term_id, 'slug');
        if ($term_lang) {
            // Tell Polylang which language this is
            $query->set('lang', $term_lang);
        }
    }
}
add_action('pre_get_posts', 'taiyuetu_fix_polylang_taxonomy_query', 1);


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

    // ── 404 FALLBACK FOR TAXONOMY TERMS ──
    // If the request matches a taxonomy term slug, resolve it as a taxonomy archive.
    // This catches cases where our rewrite rules missed the term (e.g., newly created term
    // before the rules were flushed).
    $slug_map = taiyuetu_get_term_slug_map();
    if (isset($slug_map[$request])) {
        $taxonomies = $slug_map[$request];
        $winning_taxonomy = $taxonomies[0];

        $term = get_term_by('slug', $request, $winning_taxonomy);
        if ($term && !is_wp_error($term)) {
            // Determine the query var
            if ($winning_taxonomy === 'category') {
                $query->set('category_name', $request);
            }
            elseif ($winning_taxonomy === 'post_tag') {
                $query->set('tag', $request);
            }
            else {
                $query->set($winning_taxonomy, $request);
            }

            // If Polylang is active, set the lang
            if (taiyuetu_is_polylang_active() && function_exists('pll_get_term_language')) {
                $term_lang = pll_get_term_language($term->term_id, 'slug');
                if ($term_lang) {
                    $query->set('lang', $term_lang);
                }
            }

            $query->is_404 = false;
            $query->is_archive = true;
            $query->is_tax = ($winning_taxonomy !== 'category' && $winning_taxonomy !== 'post_tag');
            $query->is_category = ($winning_taxonomy === 'category');
            $query->is_tag = ($winning_taxonomy === 'post_tag');

            // Set the queried object
            $query->queried_object = $term;
            $query->queried_object_id = $term->term_id;

            return false;
        }
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
    // Clear all related transients immediately
    delete_transient('taiyuetu_term_slug_map');
    delete_transient('taiyuetu_slug_conflicts');

    // Reset static caches (within this request)
    taiyuetu_get_term_slug_map(true);

    // Set flag to flush rewrite rules on next page load
    update_option('taiyuetu_clean_url_flush_needed', 'yes', false); // autoload = false
}
add_action('created_term', 'taiyuetu_invalidate_url_caches', 10, 3);
add_action('edited_term', 'taiyuetu_invalidate_url_caches', 10, 3);
add_action('delete_term', 'taiyuetu_invalidate_url_caches', 10, 3);

/**
 * Page slug changes affect taiyuetu_is_reserved_slug() and conflict detection — refresh caches.
 *
 * @param int $post_id Post ID.
 */
function taiyuetu_invalidate_url_caches_on_page_save($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'page') {
        return;
    }
    delete_transient('taiyuetu_term_slug_map');
    delete_transient('taiyuetu_slug_conflicts');
    update_option('taiyuetu_clean_url_flush_needed', 'yes', false);
}

/**
 * @param int      $post_id Post ID.
 * @param WP_Post|null $post Post object (available before removal on delete_post).
 */
function taiyuetu_invalidate_url_caches_on_page_delete($post_id, $post = null)
{
    $ptype = ($post && isset($post->post_type)) ? $post->post_type : get_post_type($post_id);
    if ($ptype !== 'page') {
        return;
    }
    delete_transient('taiyuetu_term_slug_map');
    delete_transient('taiyuetu_slug_conflicts');
    update_option('taiyuetu_clean_url_flush_needed', 'yes', false);
}
add_action('save_post_page', 'taiyuetu_invalidate_url_caches_on_page_save', 20);
add_action('delete_post', 'taiyuetu_invalidate_url_caches_on_page_delete', 20, 2);

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
    // Conflicts can involve pages and other published content slugs, so always clear
    // conflict cache when publish state changes.
    delete_transient('taiyuetu_slug_conflicts');

    // Only care about status transitions involving 'publish'
    if ($new_status === 'publish' || $old_status === 'publish') {
        $clean_post_types = taiyuetu_get_clean_url_post_types();
        if (in_array($post->post_type, $clean_post_types, true) || $post->post_type === 'page') {
            // Also clear the term slug map because conflict status may have changed
            delete_transient('taiyuetu_term_slug_map');
            update_option('taiyuetu_clean_url_flush_needed', 'yes', false);
        }
    }
}
add_action('transition_post_status', 'taiyuetu_invalidate_on_post_change', 10, 3);

/**
 * Conditionally flush rewrite rules after clean URL rules are registered on init.
 * Runs on front end and admin when the flag is set (not only admin_init), so visitors
 * do not keep stale rules if permalinks were rebuilt without loading wp-admin.
 */
function taiyuetu_maybe_flush_rewrite_rules()
{
    if (get_option('taiyuetu_clean_url_flush_needed', 'no') === 'yes') {
        // Delete stale transient so rules are rebuilt fresh
        delete_transient('taiyuetu_term_slug_map');

        // Re-register rules first (init already fired, but we need fresh rules)
        taiyuetu_taxonomy_rewrite_rules();
        flush_rewrite_rules();
        update_option('taiyuetu_clean_url_flush_needed', 'no', false);
    }
}
add_action('init', 'taiyuetu_maybe_flush_rewrite_rules', 99);

/**
 * Also flush when switching themes (to ensure rules are fresh).
 */
function taiyuetu_flush_on_theme_switch()
{
    taiyuetu_invalidate_url_caches();
}
add_action('after_switch_theme', 'taiyuetu_flush_on_theme_switch');

/**
 * Flush rewrite rules when Polylang languages are added, removed, or modified.
 * This ensures clean URL rules include/exclude the correct language prefixes.
 */
function taiyuetu_flush_on_polylang_language_change()
{
    taiyuetu_invalidate_url_caches();
}
add_action('pll_add_language', 'taiyuetu_flush_on_polylang_language_change');
add_action('pll_update_language', 'taiyuetu_flush_on_polylang_language_change');
add_action('pll_delete_language', 'taiyuetu_flush_on_polylang_language_change');


// ─────────────────────────────────────────────────────────────────────────────
// SECTION 6: Admin Notices for Slug Conflicts
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Build a deterministic signature for the current conflict set.
 *
 * @param string[] $conflicts Conflict messages.
 * @return string
 */
function taiyuetu_get_slug_conflicts_signature($conflicts)
{
    if (empty($conflicts) || !is_array($conflicts)) {
        return '';
    }

    return md5(wp_json_encode(array_values($conflicts)));
}

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

    $signature = taiyuetu_get_slug_conflicts_signature($conflicts);
    $user_id = get_current_user_id();
    $dismissed_signature = $user_id ? get_user_meta($user_id, 'taiyuetu_dismissed_slug_conflicts_signature', true) : '';

    // Keep notice hidden for this user until conflict content changes.
    if (!empty($signature) && hash_equals((string)$dismissed_signature, (string)$signature)) {
        return;
    }

    echo '<div class="notice notice-warning is-dismissible taiyuetu-slug-conflict-notice" data-signature="' . esc_attr($signature) . '" data-nonce="' . esc_attr(wp_create_nonce('taiyuetu_dismiss_slug_conflicts_notice')) . '">';
    echo '<p><strong>' . esc_html__('Clean URL Rewriter: Potential slug conflicts detected:', 'taiyuetu') . '</strong></p>';
    echo '<ul style="list-style: disc; padding-left: 20px;">';
    foreach ($conflicts as $conflict) {
        echo '<li>' . esc_html($conflict) . '</li>';
    }
    echo '</ul>';
    echo '<p>' . esc_html__('Conflicting URLs will retain their original structure to prevent errors.', 'taiyuetu') . '</p>';
    echo '</div>';
?>
    <script>
        (function ($) {
            $(document).on('click', '.taiyuetu-slug-conflict-notice .notice-dismiss', function () {
                var $notice = $(this).closest('.taiyuetu-slug-conflict-notice');
                var signature = $notice.data('signature');
                var nonce = $notice.data('nonce');
                if (!signature || !nonce || typeof ajaxurl === 'undefined') {
                    return;
                }

                $.post(ajaxurl, {
                    action: 'taiyuetu_dismiss_slug_conflicts_notice',
                    signature: signature,
                    nonce: nonce
                });
            });
        })(jQuery);
    </script>
    <?php
}
add_action('admin_notices', 'taiyuetu_slug_conflict_admin_notice');

/**
 * Persist admin notice dismissal for current user until conflicts change.
 */
function taiyuetu_dismiss_slug_conflicts_notice()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'), 403);
    }

    check_ajax_referer('taiyuetu_dismiss_slug_conflicts_notice', 'nonce');

    $signature = sanitize_text_field(wp_unslash($_POST['signature'] ?? ''));
    $user_id = get_current_user_id();
    if (!$user_id || empty($signature)) {
        wp_send_json_error(array('message' => 'Invalid request'), 400);
    }

    update_user_meta($user_id, 'taiyuetu_dismissed_slug_conflicts_signature', $signature);
    wp_send_json_success();
}
add_action('wp_ajax_taiyuetu_dismiss_slug_conflicts_notice', 'taiyuetu_dismiss_slug_conflicts_notice');

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