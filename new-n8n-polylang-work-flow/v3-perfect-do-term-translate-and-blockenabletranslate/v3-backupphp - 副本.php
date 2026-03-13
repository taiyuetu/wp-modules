<?php
/**
 * Plugin Name: Polylang DeepSeek Auto Translator
 * Description: Custom REST API endpoints for n8n + Polylang + DeepSeek auto-translation workflow.
 *              Handles posts, pages, custom post types, custom terms, all meta fields,
 *              post thumbnails, and language linking via Polylang.
 * Version: 1.3.0
 * Author: Pro WP Developer
 * Requires: Polylang Pro or Polylang (free)
 *
 * Changelog:
 *   v1.3.0 — 2026-03-12
 *     - FIX: Removed wp_kses_post() from block_content saving. It was stripping
 *       <!-- wp:xxx --> Gutenberg block comment markers, causing WordPress to lose
 *       the block structure and fall back to displaying untranslated source content.
 *     - FIX: Expanded block text extraction (plt_walk_blocks) from a limited whitelist
 *       to a catch-all approach. Now extracts translatable text from ANY block type
 *       (including third-party/custom blocks) unless it is a known wrapper-only block.
 *     - Added more translatable attribute keys: linkText, description, heading,
 *       subheading, name.
 *
 *   v1.2.0
 *     - Initial version with full block segment extraction and string-replace strategy.
 */

if (!defined('ABSPATH'))
    exit;

// ─────────────────────────────────────────────
// SECTION 1 — REGISTER REST API ROUTES
// ─────────────────────────────────────────────

add_action('rest_api_init', function () {

    $ns = 'pll-translate/v1';

    // 1. Get all supported languages
    register_rest_route($ns, '/languages', [
        'methods' => 'GET',
        'callback' => 'plt_get_languages',
        'permission_callback' => 'plt_auth',
    ]);

    // 2. Get posts/pages/CPT that need translation (missing any language)
    register_rest_route($ns, '/untranslated-posts', [
        'methods' => 'GET',
        'callback' => 'plt_get_untranslated_posts',
        'permission_callback' => 'plt_auth',
        'args' => [
            'post_type' => ['default' => 'post', 'sanitize_callback' => 'sanitize_text_field'],
            'lang' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            'per_page' => ['default' => 20, 'sanitize_callback' => 'absint'],
            'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
        ],
    ]);

    // 3. Get full post payload (content + meta + thumbnail) for a single post
    register_rest_route($ns, '/post-payload/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'plt_get_post_payload',
        'permission_callback' => 'plt_auth',
    ]);

    // 4. Create or update a translated post and link it in Polylang
    register_rest_route($ns, '/save-translation', [
        'methods' => 'POST',
        'callback' => 'plt_save_translation',
        'permission_callback' => 'plt_auth',
    ]);

    // 5. Get untranslated terms
    register_rest_route($ns, '/untranslated-terms', [
        'methods' => 'GET',
        'callback' => 'plt_get_untranslated_terms',
        'permission_callback' => 'plt_auth',
        'args' => [
            'taxonomy' => ['default' => 'category', 'sanitize_callback' => 'sanitize_text_field'],
            'lang' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            'per_page' => ['default' => 50, 'sanitize_callback' => 'absint'],
            'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
        ],
    ]);

    // 6. Get full term payload (name + description + meta) for translation
    register_rest_route($ns, '/term-payload/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'plt_get_term_payload',
        'permission_callback' => 'plt_auth',
    ]);

    // 7. Create or update a translated term and link it in Polylang
    register_rest_route($ns, '/save-term-translation', [
        'methods' => 'POST',
        'callback' => 'plt_save_term_translation',
        'permission_callback' => 'plt_auth',
    ]);

    // 8. Get translation status summary (for dashboard / logging)
    register_rest_route($ns, '/status', [
        'methods' => 'GET',
        'callback' => 'plt_translation_status',
        'permission_callback' => 'plt_auth',
    ]);
});


// ─────────────────────────────────────────────
// SECTION 2 — AUTHENTICATION
// ─────────────────────────────────────────────

/**
 * Allow requests authenticated with Application Password OR a shared secret header.
 * In n8n use WordPress "Application Password" credential, or send
 * X-PLT-Secret: <your_secret> header and define PLT_SECRET in wp-config.php.
 */
function plt_auth(WP_REST_Request $request)
{
    // Option A: WordPress Application Passwords (built-in since WP 5.6)
    if (is_user_logged_in() && current_user_can('edit_posts')) {
        return true;
    }

    // Option B: Shared secret for server-to-server calls
    if (defined('PLT_SECRET') && PLT_SECRET !== '') {
        $header = $request->get_header('X-PLT-Secret');
        if (hash_equals(PLT_SECRET, (string)$header)) {
            // Temporarily elevate to an admin user for capability checks
            $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ids']);
            if (!empty($admins)) {
                wp_set_current_user($admins[0]);
                return true;
            }
        }
    }

    return new WP_Error('rest_forbidden', 'Authentication required.', ['status' => 401]);
}


// ─────────────────────────────────────────────
// SECTION 3 — LANGUAGES ENDPOINT
// ─────────────────────────────────────────────

function plt_get_languages()
{
    if (!function_exists('pll_languages_list')) {
        return new WP_Error('polylang_missing', 'Polylang is not active.', ['status' => 500]);
    }

    $langs = pll_languages_list(['fields' => '']); // full PLL_Language objects
    $result = [];

    foreach ($langs as $lang) {
        $result[] = [
            'slug' => $lang->slug,
            'name' => $lang->name,
            'locale' => $lang->locale,
            'default' => (pll_default_language() === $lang->slug),
        ];
    }

    return rest_ensure_response($result);
}


// ─────────────────────────────────────────────
// SECTION 4 — UNTRANSLATED POSTS ENDPOINT
// ─────────────────────────────────────────────

function plt_get_untranslated_posts(WP_REST_Request $request)
{
    $post_type = $request->get_param('post_type');
    $target_lang = $request->get_param('lang'); // the language we want to translate INTO
    $per_page = $request->get_param('per_page');
    $page = $request->get_param('page');

    if (!function_exists('pll_get_post_language')) {
        return new WP_Error('polylang_missing', 'Polylang is not active.', ['status' => 500]);
    }

    $default_lang = pll_default_language();
    $all_langs = pll_languages_list();

    // Get posts in default language
    $query = new WP_Query([
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'lang' => $default_lang,
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);

    $results = [];

    foreach ($query->posts as $post) {
        $translations = pll_get_post_translations($post->ID);
        $missing_langs = [];

        $check_langs = $target_lang ? [$target_lang] : $all_langs;

        foreach ($check_langs as $lang) {
            if ($lang === $default_lang)
                continue;
            if (empty($translations[$lang])) {
                $missing_langs[] = $lang;
            }
        }

        if (!empty($missing_langs)) {
            $results[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'post_type' => $post->post_type,
                'source_lang' => $default_lang,
                'missing_langs' => $missing_langs,
                'translations' => $translations,
            ];
        }
    }

    return rest_ensure_response([
        'total' => $query->found_posts,
        'pages' => $query->max_num_pages,
        'page' => $page,
        'per_page' => $per_page,
        'posts' => $results,
    ]);
}


// ─────────────────────────────────────────────
// SECTION 5 — POST PAYLOAD ENDPOINT
// ─────────────────────────────────────────────

function plt_get_post_payload(WP_REST_Request $request)
{
    $post_id = (int)$request->get_param('id');
    $post = get_post($post_id);

    if (!$post) {
        return new WP_Error('not_found', 'Post not found.', ['status' => 404]);
    }

    // Detect Gutenberg block content
    $has_blocks = function_exists('has_blocks') && has_blocks($post->post_content);

    // ── Core fields ──
    $payload = [
        'id' => $post->ID,
        'post_type' => $post->post_type,
        'post_status' => $post->post_status,
        'post_author' => $post->post_author,
        'menu_order' => $post->menu_order,
        'source_lang' => pll_get_post_language($post->ID),
        'translations' => pll_get_post_translations($post->ID),
        'has_blocks' => $has_blocks,
        'translatable' => [
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'slug' => $post->post_name,
        ],
        // Block segments — only populated for Gutenberg posts.
        // n8n sends each segment's text to DeepSeek, gets back the translated text,
        // then does a direct string replace on `blocks.original_content`.
        // The fully rebuilt content string is sent back as `block_content` in the save payload.
        'blocks' => $has_blocks ? plt_extract_block_texts($post->post_content) : null,
        'meta' => plt_get_all_meta($post->ID, 'post'),
        'thumbnail' => plt_get_thumbnail_data($post->ID),
        'taxonomies' => plt_get_post_taxonomies($post->ID),
        'acf_fields' => plt_get_acf_fields($post->ID, 'post'),
    ];

    return rest_ensure_response($payload);
}

/**
 * Get ALL post meta, classifying each value type for the translator.
 * Returns: [ key => [ value, type, translatable ] ]
 */
function plt_get_all_meta(int $object_id, string $object_type = 'post')
{
    $raw_meta = ($object_type === 'post')
        ? get_post_meta($object_id)
        : get_term_meta($object_id);

    $result = [];
    $skip_prefixes = ['_edit_lock', '_edit_last', '_wp_old_slug', '_encloseme'];
    // Internal Polylang keys to skip (they are managed by Polylang itself)
    $pll_keys = ['_pll_', 'pll_'];

    foreach ($raw_meta as $key => $values) {
        // Skip internal WP / Polylang keys
        $skip = false;
        foreach (array_merge($skip_prefixes, $pll_keys) as $prefix) {
            if (strpos($key, $prefix) === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip)
            continue;

        $raw_value = maybe_unserialize($values[0]);
        $type = plt_detect_meta_type($key, $raw_value);

        $result[$key] = [
            'raw_value' => $raw_value,
            'type' => $type, // text|html|json|number|boolean|url|image_id|serialized|unknown
            'translatable' => plt_is_translatable_meta($key, $type),
        ];
    }

    return $result;
}

/**
 * Heuristically detect the type of a meta value for smart translation decisions.
 */
function plt_detect_meta_type(string $key, $value): string
{
    if (is_array($value) || is_object($value))
        return 'json';
    if (is_bool($value))
        return 'boolean';
    if (is_numeric($value) && !preg_match('/[a-zA-Z]/', (string)$value))
        return 'number';

    $str = (string)$value;

    // WordPress attachment ID pattern (common meta keys)
    $image_keys = ['_thumbnail_id', 'image', 'photo', 'logo', 'icon', 'banner', 'avatar', 'featured'];
    foreach ($image_keys as $ik) {
        if (stripos($key, $ik) !== false && is_numeric($str))
            return 'image_id';
    }

    if (filter_var($str, FILTER_VALIDATE_URL))
        return 'url';
    if (preg_match('/<[^>]+>/', $str))
        return 'html';

    // Try to detect serialized data
    if (is_serialized($str))
        return 'serialized';

    return 'text';
}

/**
 * Decide whether a given meta key / type should be sent for translation.
 */
function plt_is_translatable_meta(string $key, string $type): bool
{
    $non_translatable_types = ['number', 'boolean', 'url', 'image_id'];
    if (in_array($type, $non_translatable_types, true))
        return false;

    // Add any site-specific non-translatable keys here
    $non_translatable_keys = apply_filters('plt_non_translatable_meta_keys', [
        '_yoast_wpseo_primary_category',
        '_yoast_wpseo_focuskw',
        '_yoast_wpseo_linkdex',
        '_yoast_wpseo_content_score',
        'rank_math_seo_score',
        'total_sales',
        '_stock',
        '_price',
        '_regular_price',
        '_sale_price',
        '_sku',
        '_weight',
        '_length',
        '_width',
        '_height',
    ]);

    return !in_array($key, $non_translatable_keys, true);
}

/**
 * Get thumbnail attachment data (we share the same image across languages).
 */
function plt_get_thumbnail_data(int $post_id): ?array
{
    $thumb_id = get_post_thumbnail_id($post_id);
    if (!$thumb_id)
        return null;

    return [
        'attachment_id' => $thumb_id,
        'url' => get_the_post_thumbnail_url($post_id, 'full'),
        'alt' => get_post_meta($thumb_id, '_wp_attachment_image_alt', true),
    ];
}

/**
 * Get taxonomy terms attached to a post, split by Polylang-aware / non-aware.
 */
function plt_get_post_taxonomies(int $post_id): array
{
    $taxonomies = get_post_taxonomies($post_id);
    $result = [];

    foreach ($taxonomies as $tax) {
        $terms = wp_get_post_terms($post_id, $tax, ['fields' => 'all']);
        if (is_wp_error($terms))
            continue;

        $term_data = [];
        foreach ($terms as $term) {
            $td = [
                'term_id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'lang' => function_exists('pll_get_term_language') ? pll_get_term_language($term->term_id) : null,
                'translations' => function_exists('pll_get_term_translations') ? pll_get_term_translations($term->term_id) : [],
            ];
            $term_data[] = $td;
        }

        $result[$tax] = $term_data;
    }

    return $result;
}

/**
 * Get ACF field values if ACF is active.
 */
function plt_get_acf_fields(int $object_id, string $type = 'post'): array
{
    if (!function_exists('get_fields'))
        return [];

    $prefix = ($type === 'term') ? 'term_' . $object_id : $object_id;
    $fields = get_fields($prefix);

    if (!$fields)
        return [];

    // Flatten & classify each field
    $result = [];
    foreach ($fields as $field_key => $value) {
        $field_obj = get_field_object($field_key, $prefix);
        $field_type = $field_obj['type'] ?? 'text';

        $translatable_acf_types = ['text', 'textarea', 'wysiwyg', 'url']; // extend as needed
        $result[$field_key] = [
            'value' => $value,
            'acf_type' => $field_type,
            'translatable' => in_array($field_type, $translatable_acf_types, true),
            'field_label' => $field_obj['label'] ?? $field_key,
        ];
    }

    return $result;
}



// ─────────────────────────────────────────────
// SECTION 5b — GUTENBERG BLOCK TEXT EXTRACTION
// ─────────────────────────────────────────────

/**
 * Parse Gutenberg block content and return two things:
 *   original_content  — the raw serialized post_content string (used for direct replacement)
 *   segments          — flat list of every translatable text snippet, each with:
 *                         index      sequential ID
 *                         block_type e.g. "core/paragraph"
 *                         sub_path   "innerHTML" | "attrs.<key>"
 *                         text       the exact string that appears in original_content
 *                         is_html    whether the string contains HTML tags
 *
 * Translation strategy (n8n side):
 *   DeepSeek receives each segment's `text` value, translates it (preserving HTML tags),
 *   and returns the translated string. n8n then does a direct split-join string replacement
 *   on original_content — NO parse/serialize round-trip, which avoids serialization bugs.
 */
function plt_extract_block_texts(string $post_content): array
{
    if (!function_exists('parse_blocks')) {
        return [];
    }

    $blocks = parse_blocks($post_content);
    $segments = [];
    $index = 0;

    plt_walk_blocks($blocks, $segments, $index, []);

    return [
        'original_content' => $post_content,
        'segments' => $segments,
    ];
}

/**
 * Recursively collect translatable text segments from parsed blocks.
 */
function plt_walk_blocks(array $blocks, array &$segments, int&$index, array $parent_path): void
{
    // These block types carry their primary translatable content in innerHTML / innerContent.
    $html_content_blocks = [
        'core/paragraph', 'core/heading', 'core/quote', 'core/pullquote',
        'core/verse', 'core/preformatted', 'core/freeform',
        'core/list', 'core/list-item',
        'core/button',
        'core/table',
        'core/details',
        'core/cover',
        'core/media-text',
        'core/column',
        'core/group',
        'core/separator',
        'core/spacer',
    ];

    // Layout / wrapper blocks that should NOT have their own innerHTML extracted
    // because their innerHTML is just the container markup around innerBlocks.
    // Their children are handled by recursion.
    $wrapper_only_blocks = [
        'core/columns', 'core/column', 'core/group', 'core/cover',
        'core/media-text', 'core/separator', 'core/spacer',
        'core/template-part', 'core/block', 'core/widget-area',
    ];

    // Block attribute keys whose values are plain translatable strings.
    // Includes standard Gutenberg keys and custom block keys.
    $translatable_attr_keys = [
        'content', 'caption', 'alt', 'label', 'title', 'text',
        'placeholder', 'buttonText', 'value', 'citation', 'summary',
        'linkText', 'description', 'heading', 'subheading', 'name',
        'description1', 'description2', 'description3', 'subtitle', 'subTitle', 'number',
    ];

    foreach ($blocks as $bi => $block) {
        if (empty($block['blockName'])) {
            continue;
        }

        $block_type = $block['blockName'];
        $path_str = implode('.', array_merge($parent_path, [$bi]));
        $is_wrapper = in_array($block_type, $wrapper_only_blocks, true);

        // ── 1. innerHTML for blocks that carry translatable text ──
        // Skip wrapper blocks (their content is just container HTML around innerBlocks).
        // For known content blocks AND any unknown/third-party blocks, extract innerHTML
        // if it contains visible text (not just HTML tags and whitespace).
        if (!$is_wrapper) {
            $inner_html = trim($block['innerHTML'] ?? '');
            if ($inner_html !== '') {
                // Check if innerHTML contains any visible (non-tag) text
                $text_only = trim(strip_tags($inner_html));
                if ($text_only !== '') {
                    $segments[] = [
                        'index' => $index++,
                        'block_type' => $block_type,
                        'path' => $path_str,
                        'sub_path' => 'innerHTML',
                        'text' => $inner_html,
                        'is_html' => true,
                    ];
                }
            }
        }

        // ── 2. Named attributes (including nested arrays/objects) from ALL blocks ──
        // Many custom blocks store translatable text in attributes (e.g. description1, stats array).
        if (!empty($block['attrs'])) {
            plt_extract_attributes_recursive(
                $block['attrs'],
                $translatable_attr_keys,
                $segments,
                $index,
                $block_type,
                $path_str
            );
        }

        // ── 3. Recurse into innerBlocks ──
        if (!empty($block['innerBlocks'])) {
            plt_walk_blocks(
                $block['innerBlocks'],
                $segments,
                $index,
                array_merge($parent_path, [$bi])
            );
        }
    }
}

/**
 * Recursively search block attributes for translatable strings.
 * This handles nested arrays (e.g., custom blocks with repeating items).
 */
function plt_extract_attributes_recursive($attrs, $translatable_keys, &$segments, &$index, $block_type, $base_path, $current_subpath = 'attrs')
{
    // Attributes that should NEVER be translated (functional, styles, layout, IDs)
    $denied_attr_keys = [
        'className', 'align', 'url', 'href', 'id', 'ref', 'anchor',
        'style', 'backgroundColor', 'textColor', 'gradient', 'layout',
        'type', 'size', 'slug', 'name', 'icon', 'variation'
    ];

    foreach ($attrs as $key => $val) {
        $subpath = $current_subpath . '.' . $key;

        // Never translate functional attributes
        if (!is_numeric($key) && in_array($key, $denied_attr_keys, true)) {
            continue;
        }

        if (is_array($val) || is_object($val)) {
            // Nested structure (like the 'stats' array in custom blocks)
            plt_extract_attributes_recursive((array)$val, $translatable_keys, $segments, $index, $block_type, $base_path, $subpath);
        }
        elseif (is_string($val) && trim($val) !== '') {
            $is_numeric_key = is_numeric($key);
            $val_trim = trim($val);

            // Should we translate this string?
            $should_translate = false;

            // 1. Is it explicitly in our whitelist? OR is it an array item (numeric key)?
            if ($is_numeric_key || in_array($key, $translatable_keys, true)) {
                $should_translate = true;
            }
            // 2. Dynamic heuristic: Does it "look like" text meant to be read?
            else {
                // If it contains HTML tags, it's almost certainly translatable rich text
                $has_html = preg_match('/<[^>]+>/', $val_trim);

                // If it contains spaces (multiple words), isn't a URL, and isn't JSON
                $multiple_words = strpos($val_trim, ' ') !== false;
                $is_url = filter_var($val_trim, FILTER_VALIDATE_URL);

                if ($has_html || ($multiple_words && !$is_url)) {
                    $should_translate = true;
                }
            }

            if ($should_translate) {
                // Only store it if it's not a purely numeric string (e.g., IDs sometimes slip through)
                if (!is_numeric($val_trim)) {
                    $segments[] = [
                        'index' => $index++,
                        'block_type' => $block_type,
                        'path' => $base_path,
                        'sub_path' => $subpath,
                        'text' => $val,
                        'is_html' => (bool)preg_match('/<[^>]+>/', $val),
                    ];
                }
            }
        }
    }
}

// ─────────────────────────────────────────────
// SECTION 6 — SAVE TRANSLATION ENDPOINT
// ─────────────────────────────────────────────

function plt_save_translation(WP_REST_Request $request)
{
    $body = $request->get_json_params();

    // Required fields
    $source_id = isset($body['source_id']) ? (int)$body['source_id'] : 0;
    $target_lang = isset($body['target_lang']) ? sanitize_text_field($body['target_lang']) : '';
    $translated = $body['translated'] ?? []; // { title, content, excerpt, slug }
    $meta = $body['meta'] ?? []; // { key => translated_value }
    $acf = $body['acf'] ?? []; // { field_key => translated_value }
    $tax_map = $body['tax_map'] ?? []; // { taxonomy => [term_ids in target_lang] }

    if (!$source_id || !$target_lang) {
        return new WP_Error('bad_request', 'source_id and target_lang are required.', ['status' => 400]);
    }

    $source_post = get_post($source_id);
    if (!$source_post) {
        return new WP_Error('not_found', 'Source post not found.', ['status' => 404]);
    }

    // ── Check if translation already exists ──
    $existing_translations = pll_get_post_translations($source_id);
    $existing_id = $existing_translations[$target_lang] ?? 0;

    // ── Determine final post_content ──
    // block_content: pre-assembled Gutenberg post_content string from n8n
    //               (translated text directly substituted into original raw markup)
    // translated['content']: classic editor fallback
    $block_content = isset($body['block_content']) ? $body['block_content'] : null;

    if (!empty($block_content)) {
        // Block-based page: n8n already did the direct string replacement.
        // IMPORTANT: Do NOT use wp_kses_post() here — it strips HTML comments
        // like <!-- wp:paragraph --> which are essential Gutenberg block markers.
        // Without these markers WordPress cannot parse the block structure and
        // falls back to showing the untranslated source content.
        $final_content = $block_content;
    }
    elseif (!empty($translated['content'])) {
        // Classic editor post
        $final_content = wp_kses_post($translated['content']);
    }
    else {
        // No content provided — copy source as-is
        $final_content = $source_post->post_content;
    }

    // ── Build post data ──
    $slug = !empty($translated['slug'])
        ? sanitize_title($translated['slug'])
        : sanitize_title($translated['title'] ?? $source_post->post_title) . '-' . $target_lang;

    $post_data = [
        'post_type' => $source_post->post_type,
        'post_status' => $source_post->post_status,
        'post_author' => $source_post->post_author,
        'menu_order' => $source_post->menu_order,
        'post_title' => wp_kses_post($translated['title'] ?? $source_post->post_title),
        'post_content' => $final_content,
        'post_excerpt' => wp_kses_post($translated['excerpt'] ?? $source_post->post_excerpt),
        'post_name' => $slug,
    ];

    if ($existing_id) {
        $post_data['ID'] = $existing_id;
        $post_id = wp_update_post($post_data, true);
    }
    else {
        $post_id = wp_insert_post($post_data, true);
    }

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    // ── Set Polylang language ──
    pll_set_post_language($post_id, $target_lang);

    // ── Link translations ──
    // Collect all existing translations and add/update the new one
    $all_translations = pll_get_post_translations($source_id);
    $all_translations[$target_lang] = $post_id;
    $all_translations[pll_get_post_language($source_id)] = $source_id;
    pll_save_post_translations($all_translations);

    // ── Copy thumbnail (share the same attachment ID) ──
    $thumb_id = get_post_thumbnail_id($source_id);
    if ($thumb_id) {
        set_post_thumbnail($post_id, $thumb_id);
    }

    // ── Copy ALL meta from source, then overlay translated values ──
    // First: copy non-translatable meta verbatim
    plt_sync_post_meta($source_id, $post_id, $meta);

    // ── Save ACF translations ──
    if (!empty($acf) && function_exists('update_field')) {
        foreach ($acf as $field_key => $value) {
            update_field($field_key, $value, $post_id);
        }
    }

    // ── Assign taxonomy terms (translated equivalents) ──
    if (!empty($tax_map)) {
        foreach ($tax_map as $taxonomy => $term_ids) {
            $term_ids = array_map('intval', (array)$term_ids);
            wp_set_post_terms($post_id, $term_ids, $taxonomy);
        }
    }

    return rest_ensure_response([
        'success' => true,
        'post_id' => $post_id,
        'source_id' => $source_id,
        'target_lang' => $target_lang,
        'action' => $existing_id ? 'updated' : 'created',
        'translations' => pll_get_post_translations($source_id),
    ]);
}

/**
 * Copy meta from source post to translated post.
 * For keys present in $translated_meta, use the translated value.
 * For image_id / non-translatable keys, copy the raw value.
 * Automatically adapts suffix for WP Rapid Fields (e.g. from _en to _zh).
 */
function plt_sync_post_meta(int $source_id, int $target_id, array $translated_meta)
{
    $all_source_meta = get_post_meta($source_id);

    $skip_prefixes = ['_edit_lock', '_edit_last', '_pll_', 'pll_', '_wp_old_slug'];

    $source_lang = function_exists('pll_get_post_language') ? pll_get_post_language($source_id) : '';
    $target_lang = function_exists('pll_get_post_language') ? pll_get_post_language($target_id) : '';

    $source_suffix = $source_lang ? '_' . $source_lang : '';
    $target_suffix = $target_lang ? '_' . $target_lang : '';
    $suffix_len = $source_suffix !== '' ? strlen($source_suffix) : 0;

    foreach ($all_source_meta as $key => $values) {
        $skip = false;
        foreach ($skip_prefixes as $prefix) {
            if (strpos($key, $prefix) === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip)
            continue;

        // Thumbnail handled separately
        if ($key === '_thumbnail_id')
            continue;

        $target_key = $key;
        if ($source_suffix !== '' && $target_suffix !== '' && $source_suffix !== $target_suffix) {
            if (substr($key, -$suffix_len) === $source_suffix) {
                $target_key = substr($key, 0, -$suffix_len) . $target_suffix;
            }
        }

        if (array_key_exists($key, $translated_meta)) {
            // Use the translated value provided by n8n
            update_post_meta($target_id, $target_key, $translated_meta[$key]);
        }
        else {
            // Copy raw value (numbers, URLs, IDs, etc.)
            $raw = maybe_unserialize($values[0]);
            update_post_meta($target_id, $target_key, $raw);
        }
    }
}


// ─────────────────────────────────────────────
// SECTION 7 — UNTRANSLATED TERMS ENDPOINT
// ─────────────────────────────────────────────

function plt_get_untranslated_terms(WP_REST_Request $request)
{
    $taxonomy = $request->get_param('taxonomy');
    $target_lang = $request->get_param('lang');
    $per_page = $request->get_param('per_page');
    $page = $request->get_param('page');
    $offset = ($page - 1) * $per_page;

    if (!function_exists('pll_get_term_language')) {
        return new WP_Error('polylang_missing', 'Polylang is not active.', ['status' => 500]);
    }

    $default_lang = pll_default_language();
    $all_langs = pll_languages_list();

    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'number' => $per_page,
        'offset' => $offset,
        'lang' => $default_lang,
    ]);

    if (is_wp_error($terms))
        return $terms;

    $total = wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);

    $results = [];
    foreach ($terms as $term) {
        $translations = pll_get_term_translations($term->term_id);
        $check_langs = $target_lang ? [$target_lang] : $all_langs;
        $missing = [];

        foreach ($check_langs as $lang) {
            if ($lang === $default_lang)
                continue;
            if (empty($translations[$lang])) {
                $missing[] = $lang;
            }
        }

        if (!empty($missing)) {
            $results[] = [
                'term_id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'taxonomy' => $term->taxonomy,
                'description' => $term->description,
                'source_lang' => $default_lang,
                'missing_langs' => $missing,
                'translations' => $translations,
            ];
        }
    }

    return rest_ensure_response([
        'total' => (int)$total,
        'pages' => (int)ceil($total / $per_page),
        'page' => $page,
        'per_page' => $per_page,
        'terms' => $results,
    ]);
}


// ─────────────────────────────────────────────
// SECTION 8 — TERM PAYLOAD ENDPOINT
// ─────────────────────────────────────────────

function plt_get_term_payload(WP_REST_Request $request)
{
    $term_id = (int)$request->get_param('id');
    $term = get_term($term_id);

    if (!$term || is_wp_error($term)) {
        return new WP_Error('not_found', 'Term not found.', ['status' => 404]);
    }

    return rest_ensure_response([
        'term_id' => $term->term_id,
        'taxonomy' => $term->taxonomy,
        'source_lang' => pll_get_term_language($term->term_id),
        'translations' => pll_get_term_translations($term->term_id),
        'translatable' => [
            'name' => $term->name,
            'description' => $term->description,
            'slug' => $term->slug,
        ],
        'meta' => plt_get_all_meta($term->term_id, 'term'),
        'acf_fields' => plt_get_acf_fields($term->term_id, 'term'),
    ]);
}


// ─────────────────────────────────────────────
// SECTION 9 — SAVE TERM TRANSLATION ENDPOINT
// ─────────────────────────────────────────────

function plt_save_term_translation(WP_REST_Request $request)
{
    $body = $request->get_json_params();
    $source_id = isset($body['source_id']) ? (int)$body['source_id'] : 0;
    $taxonomy = isset($body['taxonomy']) ? sanitize_text_field($body['taxonomy']) : '';
    $target_lang = isset($body['target_lang']) ? sanitize_text_field($body['target_lang']) : '';
    $translated = $body['translated'] ?? [];
    $meta = $body['meta'] ?? [];

    if (!$source_id || !$taxonomy || !$target_lang) {
        return new WP_Error('bad_request', 'source_id, taxonomy, and target_lang are required.', ['status' => 400]);
    }

    $source_term = get_term($source_id, $taxonomy);
    if (!$source_term || is_wp_error($source_term)) {
        return new WP_Error('not_found', 'Source term not found.', ['status' => 404]);
    }

    // Check for existing translation
    $existing_translations = pll_get_term_translations($source_id);
    $existing_id = $existing_translations[$target_lang] ?? 0;

    $slug = !empty($translated['slug'])
        ? sanitize_title($translated['slug'])
        : sanitize_title($translated['name'] ?? $source_term->name) . '-' . $target_lang;

    $term_data = [
        'description' => wp_kses_post($translated['description'] ?? $source_term->description),
        'slug' => $slug,
    ];

    if ($existing_id) {
        $result = wp_update_term($existing_id, $taxonomy, $term_data);
        $term_id = $existing_id;
        $action = 'updated';
    }
    else {
        $term_name = sanitize_text_field($translated['name'] ?? $source_term->name);
        $result = wp_insert_term($term_name, $taxonomy, $term_data);
        if (is_wp_error($result))
            return $result;
        $term_id = $result['term_id'];
        $action = 'created';
    }

    // ── Set Polylang language ──
    pll_set_term_language($term_id, $target_lang);

    // ── Link translations ──
    $all_translations = pll_get_term_translations($source_id);
    $all_translations[$target_lang] = $term_id;
    $all_translations[pll_get_term_language($source_id)] = $source_id;
    pll_save_term_translations($all_translations);

    // ── Sync meta ──
    $source_lang = function_exists('pll_get_term_language') ? pll_get_term_language($source_id) : '';
    $source_suffix = $source_lang ? '_' . $source_lang : '';
    $target_suffix = $target_lang ? '_' . $target_lang : '';
    $suffix_len = $source_suffix !== '' ? strlen($source_suffix) : 0;

    foreach (get_term_meta($source_id) as $key => $values) {
        $target_key = $key;
        if ($source_suffix !== '' && $target_suffix !== '' && $source_suffix !== $target_suffix) {
            if (substr($key, -$suffix_len) === $source_suffix) {
                $target_key = substr($key, 0, -$suffix_len) . $target_suffix;
            }
        }

        $raw = maybe_unserialize($values[0]);
        if (array_key_exists($key, $meta)) {
            update_term_meta($term_id, $target_key, $meta[$key]);
        }
        else {
            update_term_meta($term_id, $target_key, $raw);
        }
    }

    return rest_ensure_response([
        'success' => true,
        'term_id' => $term_id,
        'source_id' => $source_id,
        'taxonomy' => $taxonomy,
        'target_lang' => $target_lang,
        'action' => $action,
        'translations' => pll_get_term_translations($source_id),
    ]);
}


// ─────────────────────────────────────────────
// SECTION 10 — STATUS ENDPOINT
// ─────────────────────────────────────────────

function plt_translation_status()
{
    $languages = pll_languages_list(['fields' => '']);
    $post_types = get_post_types(['public' => true], 'names');
    $taxonomies = get_taxonomies(['public' => true], 'names');

    $status = [
        'languages' => array_map(fn($l) => $l->slug, $languages),
        'post_types' => [],
        'taxonomies' => [],
    ];

    foreach ($post_types as $pt) {
        $counts = [];
        foreach ($languages as $lang) {
            $q = new WP_Query([
                'post_type' => $pt,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'lang' => $lang->slug,
                'fields' => 'ids',
            ]);
            $counts[$lang->slug] = $q->found_posts;
        }
        $status['post_types'][$pt] = $counts;
    }

    foreach ($taxonomies as $tax) {
        $counts = [];
        foreach ($languages as $lang) {
            $terms = get_terms([
                'taxonomy' => $tax,
                'hide_empty' => false,
                'lang' => $lang->slug,
                'fields' => 'count',
            ]);
            $counts[$lang->slug] = is_wp_error($terms) ? 0 : (int)$terms;
        }
        $status['taxonomies'][$tax] = $counts;
    }

    return rest_ensure_response($status);
}
