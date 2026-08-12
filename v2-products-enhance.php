<?php

/** product enhancements, admin columns, filters and actions */

function ruixing_product_admin_columns($columns)
{

    $new_cols = [];
    $inserted = false;
    foreach ($columns as $key => $val) {
        $new_cols[$key] = $val;
        if ($key == 'cb') {
            $new_cols['product_image'] = __('Product Image', 'hubbearing');
            $new_cols['featured'] = '<span class="dashicons dashicons-star-filled text-warning" title="' . esc_attr__('Featured', 'hubbearing') . '"></span>';
            $inserted = true;
        }
        if ($key == 'title') {
            $new_cols['oem_num'] = __('OEM Number', 'hubbearing');
            $new_cols['product_mpn'] = __('Part Number (MPN)', 'hubbearing');
        }
    }

    if (!$inserted) {
        $new_cols['product_image'] = '<span class="dashicons dashicons-format-image" title="' . esc_attr__('Thumbnail', 'hubbearing') . '"></span>';
        $new_cols['featured'] = '<span class="dashicons dashicons-star-filled text-warning" title="' . esc_attr__('Featured', 'hubbearing') . '"></span>';
    }

    if (!isset($new_cols['oem_num'])) {
        $new_cols['oem_num'] = __('OEM Number', 'hubbearing');
    }
    if (!isset($new_cols['product_mpn'])) {
        $new_cols['product_mpn'] = __('Part Number (MPN)', 'hubbearing');
    }

    return $new_cols;
}
add_filter('manage_edit-product_columns', 'ruixing_product_admin_columns');


/** reder content for custom admin columns */

function ruixing_show_product_admin_column_content($column, $post_id)
{
    switch ($column) {
        case 'product_image':
            if (has_post_thumbnail($post_id)) {
                echo get_the_post_thumbnail($post_id, array(50, 50));
            } else {
                echo '<span class="dashicons dashicons-format-image" style="color: #ccc;"></span>';
            }
            break;

        case 'featured':
            $is_reorder_view = isset($_GET['featured_filter']) && $_GET['featured_filter'] === '1';

            if ($is_reorder_view) {
                echo '<span class="ruixing-drag-handle dashicons dashicons-menu" title="' . esc_attr__('Drag to reorder', 'hubbearing') . '" aria-hidden="true"></span> ';
            }

            $is_featured = get_post_meta($post_id, 'featured', true);

            $icon_class = $is_featured === '1' ? 'dashicons-star-filled text-warning' : 'dashicons-star-empty text-muted';

            $title = $is_featured === '1' ? __('Yes', 'hubbearing') : __('No', 'hubbearing');

            $color = $is_featured === '1' ? '#f01d4e' : '#ccc';

            echo sprintf(
                '<a href="#" class="toggle-featured-product" data-id="%d" data-nonce="%s" title="%s"><span class="dashicons %s" style="color: %s;"></span></a>',
                $post_id,
                wp_create_nonce('toggle_featured_product' . $post_id),
                esc_attr($title),
                esc_attr($icon_class),
                esc_attr($color)
            );

            break;

        case 'oem_num':
            $oem = function_exists('ws_get_post_meta') ? ws_get_post_meta($post_id, 'oem_num', true) : get_post_meta($post_id, 'oem_num', true);
            echo !empty($oem) ? esc_html($oem) : '<span style="color: #ccc;">—</span>';
            break;

        case 'product_mpn':
            $mpn = function_exists('ws_get_post_meta') ? ws_get_post_meta($post_id, 'product_mpn', true) : get_post_meta($post_id, 'product_mpn', true);
            echo !empty($mpn) ? esc_html($mpn) : '<span style="color: #ccc;">—</span>';
            break;
    }
}

add_action('manage_product_posts_custom_column', 'ruixing_show_product_admin_column_content', 10, 2);

/** make custom product columns sortable */

function ruixing_sortable_product_adimin_column($columns)
{
    $columns['featured'] = __('Featured', 'hubbearing');
    $columns['oem_num'] = 'oem_num';
    $columns['product_mpn'] = 'product_mpn';
    return $columns;
}

add_filter('manage_edit-product_sortable_columns', 'ruixing_sortable_product_adimin_column');

/**
 * Handle sorting by custom meta columns (oem_num, product_mpn) in admin list table
 */
function ruixing_sort_product_meta_columns($query)
{
    global $pagenow;

    if (!is_admin() || !$query->is_main_query() || $pagenow !== 'edit.php' || $query->get('post_type') !== 'product') {
        return;
    }

    $orderby = $query->get('orderby');
    if ($orderby === 'oem_num' || $orderby === 'product_mpn') {
        $query->set('meta_key', $orderby);
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'ruixing_sort_product_meta_columns');


/**
 * Filter by featured link above table
 */
function ruixing_add_featured_product_view($views)
{

    $current = isset($_GET['featured_filter']) && $_GET['featured_filter'] === '1' ? 'class="current"' : '';

    $args = [
        'post_type' => 'product',
        'meta_key' => 'featured',
        'meta_value' => '1',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'post_status' => 'any',
    ];

    $featured_posts = get_posts($args);

    $count = count($featured_posts);

    if ($count > 0) {
        $views['featured'] = sprintf(
            '<a href="%s" %s>%s <span class="count">(%d)</span></a>',
            admin_url('edit.php?post_type=product&featured_filter=1'),
            $current,
            __('Featured', 'hubbearing'),
            $count

        );
    }

    return $views;
}

add_filter('views_edit-product', 'ruixing_add_featured_product_view');

/**
 * modify query for featured filter
 * 
 */

function ruixing_filter_featured_products($query)
{

    global $pagenow;

    if (!is_admin() || !$query->is_main_query() || $pagenow !== 'edit.php' || $query->get('post_type') !== 'product') {
        return;
    }

    if (isset($_GET['featured_filter']) && $_GET['featured_filter'] === '1') {
        $query->set('meta_key', 'featured');
        $query->set('meta_value', '1');
    }
}

add_action('pre_get_posts', 'ruixing_filter_featured_products');

/**
 * Ajax handler to toggle featured status
 */

function ruixing_ajax_toggle_featured_product()
{
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (!$post_id || !check_ajax_referer('toggle_featured_product' . $post_id, 'nonce', false)) {
        wp_send_json_error(__('Invalid Request', 'hubbearing'));
    }
    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error(__('You do not have permission to perform this action', 'hubbearing'));
    }

    $current_status = get_post_meta($post_id, 'featured', true);

    $new_status = $current_status ? '' : '1';

    update_post_meta($post_id, 'featured', $new_status);

    wp_send_json_success(array(

        'new_status' => $new_status,
        'icon_class' => $new_status ? 'dashicons-star-filled' : 'dashicons-star-empty',
        'color' => $new_status ? '#f01d4e' : '#ccc'

    ));
}
//toggle_featured_product
add_action('wp_ajax_toggle_featured_product', 'ruixing_ajax_toggle_featured_product');

/** enqueue admin scripts */

function ruixing_admin_enqueue_products_scripts($hook)
{
    if ('edit.php' !== $hook) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'product' !== $screen->post_type) {
        return;
    }

    wp_enqueue_script('ruixing-product-featured', get_template_directory_uri() . '/framework/admin/js/product-featured.js', ['jquery'], '1.0', true);

    // enqueue socrtable and dragable js 
    if (isset($_GET['featured_filter']) && $_GET['featured_filter'] === '1') {
        wp_enqueue_script('jquery-ui-sortable');

        wp_enqueue_script('ruixing-product-reorder', get_template_directory_uri() . '/framework/admin/js/product-reorder.js', ['jquery', 'jquery-ui-sortable'], '1.1', true);

        wp_localize_script('ruixing-product-reorder', 'ruixing_product_reorder', array(
            'nonce' => wp_create_nonce('ruixing_product_reorder_nonce'),
        ));
    }
}

add_action('admin_enqueue_scripts', 'ruixing_admin_enqueue_products_scripts');

/**
 * modify query for featured filter
 */

function ruixing_filter_featured_products_sort($query)
{
    global $pagenow;

    if (!is_admin() || !$query->is_main_query() || $pagenow !== 'edit.php' || $query->get('post_type') !== 'product') {
        return;
    }

    if (isset($_GET['featured_filter']) && $_GET['featured_filter'] === '1') {
        $query->set('orderby', 'menu_order');
        $query->set('order', 'ASC');
    }
}
add_action('pre_get_posts', 'ruixing_filter_featured_products_sort');

/**
 * Admin styles for featured product reorder UI.
 */
function ruixing_product_admin_reorder_styles()
{
    $screen = get_current_screen();

    if (!$screen || 'edit-product' !== $screen->id) {
        return;
    }

    if (!isset($_GET['featured_filter']) || $_GET['featured_filter'] !== '1') {
        return;
    }

    echo '<style>
        .column-featured { white-space: nowrap; }
        .ruixing-drag-handle {
            cursor: move;
            color: #787c82;
            vertical-align: middle;
            margin-right: 4px;
        }
        .ruixing-drag-handle:hover { color: #2271b1; }
        tr.ruixing-sortable-placeholder td {
            background: #f0f6fc !important;
            border: 2px dashed #2271b1 !important;
            height: 52px;
        }
        tr.ui-sortable-helper {
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
        }
    </style>';
}
add_action('admin_head', 'ruixing_product_admin_reorder_styles');

/**
 * save menu order for featured products
 */
function ruixing_save_featured_products_order()
{

    if (!check_ajax_referer('ruixing_product_reorder_nonce', 'nonce', false)) {
        wp_send_json_error(__('Invalid requests', 'hubbearing'));
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(__('Permission denied', 'hubbearing'));
    }
    $order = isset($_POST['order']) ? $_POST['order'] : [];

    if (empty($order)) {
        wp_send_json_error('no order data recieved');
    }

    foreach ($order as $position => $post_id) {
        $post_id = intval($post_id);

        if ($post_id) {
            wp_update_post([
                'ID' => $post_id,
                'menu_order' => $position
            ]);
        }
    }
    wp_send_json_success(__('Order saved', 'hubbearing'));
}

//toggle_featured_product
add_action('wp_ajax_ruixing_save_product_order', 'ruixing_save_featured_products_order');

/**
 * Get published products marked as featured, ordered by menu_order.
 *
 * @param array $args Optional WP_Query-style overrides (e.g. tax_query for a product_cat slug).
 * @return WP_Post[]
 */
function hubbearing_get_featured_products($args = array())
{
    $query_args = wp_parse_args($args, array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => 'featured',
        'meta_value'     => '1',
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ));

    return get_posts($query_args);
}

/**
 * Get product_cat terms used by featured products (respects term_order).
 *
 * @return WP_Term[]
 */
function hubbearing_get_featured_product_categories()
{
    $featured_products = hubbearing_get_featured_products();

    if (empty($featured_products)) {
        return array();
    }

    $product_ids = wp_list_pluck($featured_products, 'ID');

    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'object_ids' => $product_ids,
    ));

    return is_wp_error($terms) ? array() : $terms;
}

/**
 * Primary product_cat slug for a product (used by featured filter tabs).
 *
 * @param int $post_id Product post ID.
 * @return string
 */
function hubbearing_get_product_category_slug($post_id)
{
    $terms = get_the_terms($post_id, 'product_cat');

    if (empty($terms) || is_wp_error($terms)) {
        return '';
    }

    return $terms[0]->slug;
}

/**
 * Build a WP_Query for featured products, optionally limited to one category.
 *
 * @param string $category_slug product_cat slug. Empty or 'all' = no category filter.
 * @param int    $posts_per_page Number of products to return.
 * @return WP_Query
 */
function hubbearing_featured_products_query($category_slug = 'all', $posts_per_page = 8)
{
    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => (int) $posts_per_page,
        'meta_key'       => 'featured',
        'meta_value'     => '1',
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    );

    if ($category_slug && $category_slug !== 'all') {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $category_slug,
            ),
        );
    }

    return new WP_Query($args);
}

/**
 * Render the featured product cards for a given query.
 * Shared by the front page (initial render) and the AJAX filter handler so the
 * markup stays identical in both places.
 *
 * @param WP_Query $query
 * @return void Echoes the cards markup.
 */
function hubbearing_render_featured_cards($query)
{
    if (!($query instanceof WP_Query) || !$query->have_posts()) {
        echo '<p class="featured__empty">' . esc_html__('No featured products found in this category.', 'hubbearing') . '</p>';
        return;
    }

    while ($query->have_posts()) :
        $query->the_post();

        $product_id       = get_the_ID();
        $product_name     = get_the_title();
        $product_link     = get_permalink();
        $product_image    = get_the_post_thumbnail_url($product_id, 'medium');
        $product_oem      = ws_get_post_meta($product_id, 'oem', true);
        $product_app      = ws_get_post_meta($product_id, 'application', true);
        $product_vehicle  = ws_get_post_meta($product_id, 'vehicle', true);
        $product_type     = ws_get_post_meta($product_id, 'bearing_type', true);
        $product_id_size  = ws_get_post_meta($product_id, 'inner_diameter', true);
        $product_od_size  = ws_get_post_meta($product_id, 'outer_diameter', true);
        $product_width    = ws_get_post_meta($product_id, 'width', true);
        $product_cat_slug = hubbearing_get_product_category_slug($product_id);

        $delay_count = $query->current_post * 100;
        $delay       = $delay_count ? ' data-aos-delay="' . esc_attr($delay_count) . '"' : '';
?>
        <div class="featured-card" data-category="<?php echo esc_attr($product_cat_slug); ?>" data-aos="fade-up" <?php echo $delay; ?>>
            <div class="featured-card__img">
                <?php if ($product_image) : ?>
                    <img src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr($product_name); ?>" loading="lazy">
                <?php else : ?>
                    <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/placeholder.png'); ?>" alt="<?php echo esc_attr($product_name); ?>" loading="lazy">
                <?php endif; ?>
            </div>
            <div class="featured-card__content">
                <?php if ($product_oem) : ?>
                    <span class="featured-card__oem"><?php echo esc_html(sprintf(__('OEM: %s', 'hubbearing'), $product_oem)); ?></span>
                <?php endif; ?>
                <h3><?php echo esc_html($product_name); ?></h3>
                <div class="featured-card__specs">
                    <?php if ($product_app) : ?>
                        <div class="featured-card__spec"><span><?php esc_html_e('Application:', 'hubbearing'); ?></span> <?php echo esc_html($product_app); ?></div>
                    <?php endif; ?>
                    <?php if ($product_vehicle) : ?>
                        <div class="featured-card__spec"><span><?php esc_html_e('Vehicle:', 'hubbearing'); ?></span> <?php echo esc_html($product_vehicle); ?></div>
                    <?php endif; ?>
                    <?php if ($product_type) : ?>
                        <div class="featured-card__spec"><span><?php esc_html_e('Type:', 'hubbearing'); ?></span> <?php echo esc_html($product_type); ?></div>
                    <?php endif; ?>
                    <?php if ($product_id_size || $product_od_size || $product_width) : ?>
                        <div class="featured-card__spec">
                            <?php if ($product_id_size) : ?><span><?php esc_html_e('ID:', 'hubbearing'); ?></span> <?php echo esc_html($product_id_size); ?><?php endif; ?>
                                <?php if ($product_od_size) : ?> | <span><?php esc_html_e('OD:', 'hubbearing'); ?></span> <?php echo esc_html($product_od_size); ?><?php endif; ?>
                                    <?php if ($product_width) : ?> | <span><?php esc_html_e('Width:', 'hubbearing'); ?></span> <?php echo esc_html($product_width); ?><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <a href="<?php echo esc_url($product_link); ?>" class="btn btn--primary btn--sm"><?php esc_html_e('Inquire Now', 'hubbearing'); ?></a>
            </div>
        </div>
<?php
    endwhile;

    wp_reset_postdata();
}

/**
 * AJAX: return featured product cards for a category filter (max 8).
 */
function hubbearing_ajax_filter_featured_products()
{
    check_ajax_referer('hubbearing_featured_filter', 'nonce');

    $category = isset($_POST['category']) ? sanitize_title(wp_unslash($_POST['category'])) : 'all';

    $query = hubbearing_featured_products_query($category, 8);

    ob_start();
    hubbearing_render_featured_cards($query);
    $html = ob_get_clean();

    wp_send_json_success(array('html' => $html));
}
add_action('wp_ajax_hubbearing_filter_featured', 'hubbearing_ajax_filter_featured_products');
add_action('wp_ajax_nopriv_hubbearing_filter_featured', 'hubbearing_ajax_filter_featured_products');

/**
 * Whether the current request is a product archive or product category archive.
 *
 * @param WP_Query|null $query Optional query object. Uses main query when omitted.
 * @return bool
 */
function hubbearing_is_product_archive_view($query = null)
{
    if ($query instanceof WP_Query) {
        return $query->is_post_type_archive('product') || $query->is_tax('product_cat');
    }

    return is_post_type_archive('product') || is_tax('product_cat');
}

/**
 * Build a vehicle label from product meta fields.
 *
 * @param int $post_id Product post ID.
 * @return string
 */
function hubbearing_get_product_vehicle_label($post_id)
{
    $vehicle = ws_get_post_meta($post_id, 'vehicle', true);

    if ($vehicle) {
        return $vehicle;
    }

    $parts = array_filter(array(
        ws_get_post_meta($post_id, 'car_make', true),
        ws_get_post_meta($post_id, 'car_model', true),
        ws_get_post_meta($post_id, 'car_trim', true),
    ));

    return implode(' ', $parts);
}

/**
 * Brand slug for archive card data-brand attribute (from post tags).
 *
 * @param int $post_id Product post ID.
 * @return string
 */
function hubbearing_get_product_brand_slug($post_id)
{
    $terms = get_the_terms($post_id, 'post_tag');

    if (empty($terms) || is_wp_error($terms)) {
        return '';
    }

    return $terms[0]->slug;
}

/**
 * post_tag terms assigned to published products (vehicle brands).
 *
 * @return WP_Term[]
 */
function hubbearing_get_product_brand_terms()
{
    global $wpdb;

    $term_ids = $wpdb->get_col(
        "SELECT DISTINCT tt.term_id
        FROM {$wpdb->term_relationships} tr
        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
        WHERE tt.taxonomy = 'post_tag'
            AND p.post_type = 'product'
            AND p.post_status = 'publish'"
    );

    if (empty($term_ids)) {
        return array();
    }

    $terms = get_terms(
        array(
            'taxonomy'   => 'post_tag',
            'include'    => array_map('intval', $term_ids),
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        )
    );

    return (empty($terms) || is_wp_error($terms)) ? array() : $terms;
}

/**
 * Distinct vehicle brand names used by published products (from post tags).
 *
 * @return string[]
 */
function hubbearing_get_product_brands()
{
    $terms = hubbearing_get_product_brand_terms();

    return array_values(wp_list_pluck($terms, 'name'));
}

/**
 * Archive stats for the page banner.
 *
 * @return array{skus:string,categories:int,brands:int,export_countries:string}
 */
function hubbearing_get_product_archive_stats()
{
    $product_count = wp_count_posts('product');
    $total_skus    = isset($product_count->publish) ? (int) $product_count->publish : 0;
    $categories    = wp_count_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
    ));
    $brands        = hubbearing_get_product_brands();

    return array(
        'skus'              => $total_skus >= 1000 ? number_format_i18n($total_skus) . '+' : (string) max(0, $total_skus),
        'categories'        => is_wp_error($categories) ? 0 : (int) $categories,
        'brands'            => count($brands),
        'export_countries'  => apply_filters('hubbearing_archive_stat_export_count', '80+'),
    );
}

/**
 * Current archive filter values from the query string.
 *
 * Vehicle brands are filtered via the native post_tag query var (`tag`).
 *
 * @return array{search:string,tag:string,orderby:string,category:string}
 */
function hubbearing_get_product_archive_filters()
{
    $category = 'all';

    if (is_tax('product_cat')) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $category = $term->slug;
        }
    } elseif (!empty($_GET['product_cat'])) {
        $category = sanitize_title(wp_unslash($_GET['product_cat']));
    }

    $tag = '';
    if (!empty($_GET['tag'])) {
        $tag = sanitize_title(wp_unslash($_GET['tag']));
    } elseif (get_query_var('tag')) {
        $tag = sanitize_title(get_query_var('tag'));
    }

    return array(
        'search'   => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
        'tag'      => $tag,
        'orderby'  => isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'default',
        'category' => $category,
    );
}

/**
 * Build a product archive URL with optional filter overrides.
 *
 * @param array $args Filter overrides.
 * @return string
 */
function hubbearing_get_product_archive_url($args = array())
{
    $filters = wp_parse_args($args, hubbearing_get_product_archive_filters());
    $base    = get_post_type_archive_link('product');

    if (!empty($filters['category']) && $filters['category'] !== 'all') {
        $term = get_term_by('slug', $filters['category'], 'product_cat');
        if ($term && !is_wp_error($term)) {
            $base = get_term_link($term);
        }
    }

    if (is_wp_error($base)) {
        $base = home_url('/products/');
    }

    $query_args = array();

    if (!empty($filters['search'])) {
        $query_args['s'] = $filters['search'];
    }

    if (!empty($filters['tag'])) {
        $query_args['tag'] = $filters['tag'];
    }

    if (!empty($filters['orderby']) && $filters['orderby'] !== 'default') {
        $query_args['orderby'] = $filters['orderby'];
    }

    return !empty($query_args) ? add_query_arg($query_args, $base) : $base;
}

/**
 * Configure the main query for product archive views.
 *
 * @param WP_Query $query Main query.
 */
function hubbearing_product_archive_pre_get_posts($query)
{
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    if (!$query->is_post_type_archive('product') && !$query->is_tax('product_cat')) {
        return;
    }

    $query->set('posts_per_page', 9);

    $filters = hubbearing_get_product_archive_filters();
    $tax_query = array();

    if (!empty($filters['search'])) {
        $query->set('s', $filters['search']);
    }

    if (!empty($filters['tag'])) {
        $tax_query[] = array(
            'taxonomy' => 'post_tag',
            'field'    => 'slug',
            'terms'    => $filters['tag'],
        );

        // Preserve the current product category when combining with a tag filter.
        if ($query->is_tax('product_cat')) {
            $term = get_queried_object();
            if ($term instanceof WP_Term) {
                $tax_query[] = array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => (int) $term->term_id,
                );
            }
        }
    }

    if (!$query->is_tax('product_cat') && !empty($filters['category']) && $filters['category'] !== 'all') {
        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $filters['category'],
        );
    }

    if (!empty($tax_query)) {
        if (count($tax_query) > 1) {
            $tax_query = array_merge(array('relation' => 'AND'), $tax_query);
        }
        $query->set('tax_query', $tax_query);
    }

    switch ($filters['orderby']) {
        case 'newest':
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
            break;
        case 'popular':
            $query->set('meta_key', 'featured');
            $query->set('orderby', array(
                'meta_value' => 'DESC',
                'menu_order' => 'ASC',
                'title'      => 'ASC',
            ));
            break;
        case 'oem-asc':
            $query->set('meta_key', 'oem');
            $query->set('orderby', 'meta_value');
            $query->set('order', 'ASC');
            break;
        case 'oem-desc':
            $query->set('meta_key', 'oem');
            $query->set('orderby', 'meta_value');
            $query->set('order', 'DESC');
            break;
        default:
            $query->set('orderby', array(
                'menu_order' => 'ASC',
                'title'      => 'ASC',
            ));
            $query->set('order', 'ASC');
            break;
    }
}
add_action('pre_get_posts', 'hubbearing_product_archive_pre_get_posts');

/**
 * Whether a query should also match product OEM / part number / car make meta.
 *
 * @param WP_Query $query Current query.
 * @return bool
 */
function hubbearing_query_should_search_product_meta($query)
{
    if (is_admin() || !($query instanceof WP_Query) || !$query->get('s')) {
        return false;
    }

    if (hubbearing_is_product_archive_view($query)) {
        return true;
    }

    $post_type = $query->get('post_type');

    if ($post_type === 'product') {
        return true;
    }

    if (is_array($post_type) && in_array('product', $post_type, true)) {
        return true;
    }

    return $query->is_main_query() && $query->is_search();
}

/**
 * Extend product search to include OEM, part number, and car make meta.
 *
 * @param string   $join  SQL join clause.
 * @param WP_Query $query Current query.
 * @return string
 */
function hubbearing_product_archive_search_join($join, $query)
{
    if (!hubbearing_query_should_search_product_meta($query)) {
        return $join;
    }

    global $wpdb;

    if (false !== strpos($join, 'hubbearing_pm_oem')) {
        return $join;
    }

    $join .= " LEFT JOIN {$wpdb->postmeta} AS hubbearing_pm_oem ON ({$wpdb->posts}.ID = hubbearing_pm_oem.post_id AND hubbearing_pm_oem.meta_key = 'oem')";
    $join .= " LEFT JOIN {$wpdb->postmeta} AS hubbearing_pm_part ON ({$wpdb->posts}.ID = hubbearing_pm_part.post_id AND hubbearing_pm_part.meta_key = 'part_number')";
    $join .= " LEFT JOIN {$wpdb->postmeta} AS hubbearing_pm_make ON ({$wpdb->posts}.ID = hubbearing_pm_make.post_id AND hubbearing_pm_make.meta_key = 'car_make')";

    return $join;
}
add_filter('posts_join', 'hubbearing_product_archive_search_join', 10, 2);

/**
 * @param string   $where SQL where clause.
 * @param WP_Query $query Current query.
 * @return string
 */
function hubbearing_product_archive_search_where($where, $query)
{
    if (!hubbearing_query_should_search_product_meta($query)) {
        return $where;
    }

    global $wpdb;

    $search = $query->get('s');
    $like   = '%' . $wpdb->esc_like($search) . '%';

    $where .= $wpdb->prepare(
        " OR (
            {$wpdb->posts}.post_status = 'publish'
            AND {$wpdb->posts}.post_type = 'product'
            AND (
                hubbearing_pm_oem.meta_value LIKE %s
                OR hubbearing_pm_part.meta_value LIKE %s
                OR hubbearing_pm_make.meta_value LIKE %s
            )
        )",
        $like,
        $like,
        $like
    );

    return $where;
}
add_filter('posts_where', 'hubbearing_product_archive_search_where', 10, 2);

/**
 * @param string   $distinct SQL distinct clause.
 * @param WP_Query $query    Current query.
 * @return string
 */
function hubbearing_product_archive_search_distinct($distinct, $query)
{
    if (hubbearing_query_should_search_product_meta($query)) {
        return 'DISTINCT';
    }

    return $distinct;
}
add_filter('posts_distinct', 'hubbearing_product_archive_search_distinct', 10, 2);

/**
 * Render archive product cards for the main loop.
 *
 * @return bool True when posts were rendered.
 */
function hubbearing_render_archive_cards()
{
    if (!have_posts()) {
        return false;
    }

    $index = 0;

    while (have_posts()) {
        the_post();
        get_template_part('template-parts/product-archive', 'card', array('index' => $index));
        $index++;
    }

    return true;
}

/**
 * Normalize a WP Rapid Fields group value into a list of rows.
 *
 * @param mixed $value Raw group meta.
 * @return array<int, array<string, string>>
 */
function hubbearing_normalize_group_meta($value)
{
    if (!is_array($value)) {
        return array();
    }

    $rows = array();

    foreach ($value as $row) {
        if (!is_array($row)) {
            continue;
        }

        $clean = array();
        foreach ($row as $key => $item) {
            if ($key === '{{i}}') {
                continue;
            }
            $clean[$key] = is_scalar($item) ? trim((string) $item) : '';
        }

        if (array_filter($clean)) {
            $rows[] = $clean;
        }
    }

    return $rows;
}

/**
 * Build gallery image data for a product.
 *
 * @param int $post_id Product post ID.
 * @return array<int, array{full:string,thumb:string,alt:string}>
 */
function hubbearing_get_product_gallery_images($post_id)
{
    $images  = array();
    $seen    = array();
    $title   = get_the_title($post_id);
    $gallery = ws_get_post_meta($post_id, 'product_gallery', true);

    $add_image = static function ($attachment_id, $fallback_alt = '') use (&$images, &$seen, $title) {
        $attachment_id = (int) $attachment_id;
        if (!$attachment_id || isset($seen[$attachment_id])) {
            return;
        }

        $full  = wp_get_attachment_image_url($attachment_id, 'large');
        $thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail');

        if (!$full) {
            $full = wp_get_attachment_url($attachment_id);
        }
        if (!$thumb) {
            $thumb = $full;
        }
        if (!$full) {
            return;
        }

        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (!$alt) {
            $alt = $fallback_alt ?: $title;
        }

        $seen[$attachment_id] = true;
        $images[] = array(
            'full'  => $full,
            'thumb' => $thumb,
            'alt'   => $alt,
        );
    };

    if (has_post_thumbnail($post_id)) {
        $add_image(get_post_thumbnail_id($post_id), $title);
    }

    if ($gallery) {
        $ids = array_filter(array_map('trim', explode(',', (string) $gallery)));
        foreach ($ids as $image_id) {
            $add_image($image_id);
        }
    }

    if (empty($images)) {
        $placeholder = get_template_directory_uri() . '/assets/images/placeholder.png';
        $images[] = array(
            'full'  => $placeholder,
            'thumb' => $placeholder,
            'alt'   => $title,
        );
    }

    return $images;
}

/**
 * Primary product category name.
 *
 * @param int $post_id Product post ID.
 * @return string
 */
function hubbearing_get_product_category_name($post_id)
{
    $terms = get_the_terms($post_id, 'product_cat');

    if (empty($terms) || is_wp_error($terms)) {
        return '';
    }

    return $terms[0]->name;
}

/**
 * Human-readable availability label.
 *
 * @param int $post_id Product post ID.
 * @return string
 */
function hubbearing_get_product_availability_label($post_id)
{
    $status = ws_get_post_meta($post_id, 'availability', true);

    $labels = array(
        'in_stock'     => __('In Stock', 'hubbearing'),
        'low_stock'    => __('Low Stock', 'hubbearing'),
        'out_of_stock' => __('Out of Stock', 'hubbearing'),
        'preorder'     => __('Available on Request', 'hubbearing'),
    );

    return $labels[$status] ?? ($status ? ucwords(str_replace('_', ' ', $status)) : __('In Stock', 'hubbearing'));
}

/**
 * Availability color style for product meta display.
 *
 * @param int $post_id Product post ID.
 * @return string
 */
function hubbearing_get_product_availability_color($post_id)
{
    $status = ws_get_post_meta($post_id, 'availability', true);

    if ($status === 'out_of_stock') {
        return 'var(--text-secondary)';
    }

    if ($status === 'low_stock') {
        return 'var(--accent-primary)';
    }

    return 'var(--accent-secondary)';
}

/**
 * Specification rows for the product details table.
 *
 * @param int $post_id Product post ID.
 * @return array<int, array{label:string,value:string}>
 */
function hubbearing_get_product_spec_rows($post_id)
{
    $category = hubbearing_get_product_category_name($post_id);
    $map      = array(
        __('Product Name', 'hubbearing')        => get_the_title($post_id),
        __('OEM Part Number', 'hubbearing')     => ws_get_post_meta($post_id, 'oem', true),
        __('SKU', 'hubbearing')                 => ws_get_post_meta($post_id, 'part_number', true),
        __('Category', 'hubbearing')            => $category,
        __('Bearing Type', 'hubbearing')        => ws_get_post_meta($post_id, 'bearing_type', true),
        __('Inner Diameter (d)', 'hubbearing')  => ws_get_post_meta($post_id, 'inner_diameter', true),
        __('Outer Diameter (D)', 'hubbearing')  => ws_get_post_meta($post_id, 'outer_diameter', true),
        __('Width (B)', 'hubbearing')           => ws_get_post_meta($post_id, 'width', true),
        __('Size', 'hubbearing')                => ws_get_post_meta($post_id, 'size', true),
        __('Weight', 'hubbearing')              => ws_get_post_meta($post_id, 'weight', true),
        __('Application', 'hubbearing')         => ws_get_post_meta($post_id, 'application', true),
        __('Vehicle', 'hubbearing')             => hubbearing_get_product_vehicle_label($post_id),
        __('Availability', 'hubbearing')        => hubbearing_get_product_availability_label($post_id),
    );

    $rows = array();
    foreach ($map as $label => $value) {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if ($value !== '') {
            $rows[] = array(
                'label' => $label,
                'value' => $value,
            );
        }
    }

    return $rows;
}

/**
 * Build a compact dimensions label for inquiry strips and summaries.
 *
 * @param int $post_id Product post ID.
 * @return string
 */
function hubbearing_get_product_dimensions_label($post_id)
{
    $parts = array();

    $inner = ws_get_post_meta($post_id, 'inner_diameter', true);
    $outer = ws_get_post_meta($post_id, 'outer_diameter', true);
    $width = ws_get_post_meta($post_id, 'width', true);
    $size  = ws_get_post_meta($post_id, 'size', true);

    if ($inner) {
        $parts[] = sprintf(__('ID %s', 'hubbearing'), $inner);
    }
    if ($outer) {
        $parts[] = sprintf(__('OD %s', 'hubbearing'), $outer);
    }
    if ($width) {
        $parts[] = sprintf(__('W %s', 'hubbearing'), $width);
    } elseif ($size) {
        $parts[] = sprintf(__('Size %s', 'hubbearing'), $size);
    }

    return implode(' · ', $parts);
}

/**
 * Vehicle compatibility rows with fallback to vehicle meta.
 *
 * @param int $post_id Product post ID.
 * @return array<int, array{title:string,details:string}>
 */
function hubbearing_get_product_compatibility_rows($post_id)
{
    $rows = hubbearing_normalize_group_meta(ws_get_post_meta($post_id, 'vehicle_compatibility', true));

    $normalized = array();
    foreach ($rows as $row) {
        $title   = $row['title'] ?? '';
        $details = $row['details'] ?? '';
        if ($title || $details) {
            $normalized[] = array(
                'title'   => $title,
                'details' => $details,
            );
        }
    }

    if (!empty($normalized)) {
        return $normalized;
    }

    $vehicle = hubbearing_get_product_vehicle_label($post_id);
    if ($vehicle) {
        return array(
            array(
                'title'   => $vehicle,
                'details' => ws_get_post_meta($post_id, 'application', true),
            ),
        );
    }

    return array();
}

/**
 * Product tabs that have content to render.
 *
 * @param int $post_id Product post ID.
 * @return array<string, string> Tab slug => label.
 */
function hubbearing_get_product_tabs($post_id)
{
    $tabs = array();

    if (!empty(hubbearing_get_product_spec_rows($post_id))) {
        $tabs['specifications'] = __('Specifications', 'hubbearing');
    }

    if (!empty(hubbearing_normalize_group_meta(ws_get_post_meta($post_id, 'cross_reference', true)))) {
        $tabs['cross-ref'] = __('Cross Reference', 'hubbearing');
    }

    if (!empty(hubbearing_get_product_compatibility_rows($post_id))) {
        $tabs['compatibility'] = __('Vehicle Compatibility', 'hubbearing');
    }

    if (!empty(hubbearing_normalize_group_meta(ws_get_post_meta($post_id, 'key_features', true)))) {
        $tabs['features'] = __('Key Features', 'hubbearing');
    }

    if (
        !empty(hubbearing_normalize_group_meta(ws_get_post_meta($post_id, 'packaging', true)))
        || trim((string) ws_get_post_meta($post_id, 'packaging_notes', true)) !== ''
    ) {
        $tabs['packaging'] = __('Packaging', 'hubbearing');
    }

    $content = trim((string) get_post_field('post_content', $post_id));
    if ($content !== '') {
        $tabs['details'] = __('Product Details', 'hubbearing');
    }

    return $tabs;
}

/**
 * Banner image for the single product page.
 *
 * @param int $post_id Product post ID.
 * @return string
 */
function hubbearing_get_product_banner_image($post_id)
{
    $image = get_the_post_thumbnail_url($post_id, 'full');

    if (!$image) {
        $gallery = hubbearing_get_product_gallery_images($post_id);
        $image   = $gallery[0]['full'] ?? '';
    }

    if (!$image) {
        $terms = get_the_terms($post_id, 'product_cat');
        if (!empty($terms) && !is_wp_error($terms)) {
            $image = ws_get_term_meta($terms[0]->term_id, 'term_cover', true);
        }
    }

    if (!$image) {
        $image = 'https://images.unsplash.com/photo-1581092160562-40aa08e78837?w=1920&q=80';
    }

    return $image;
}

/**
 * Related products in the same category.
 *
 * @param int $post_id Product post ID.
 * @param int $limit   Number of products.
 * @return WP_Query
 */
function hubbearing_get_related_products_query($post_id, $limit = 4)
{
    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => (int) $limit,
        'post__not_in'   => array((int) $post_id),
        'orderby'        => 'rand',
    );

    $term_ids = wp_get_post_terms($post_id, 'product_cat', array('fields' => 'ids'));
    if (!empty($term_ids) && !is_wp_error($term_ids)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $term_ids,
            ),
        );
    }

    return new WP_Query($args);
}

/**
 * WhatsApp link for product inquiries.
 *
 * @param int    $post_id Product post ID.
 * @param string $phone   Optional phone override.
 * @return string
 */
function hubbearing_get_product_whatsapp_url($post_id, $phone = '')
{
    if (!$phone) {
        $phone = get_theme_opt('contact_phone');
    }

    $digits = preg_replace('/\D+/', '', (string) $phone);
    if (!$digits) {
        return '';
    }

    $oem   = ws_get_post_meta($post_id, 'oem', true);
    $title = get_the_title($post_id);
    $text  = sprintf(
        /* translators: 1: product title, 2: OEM number */
        __('Hello, I would like to inquire about %1$s (OEM: %2$s).', 'hubbearing'),
        $title,
        $oem ?: __('N/A', 'hubbearing')
    );

    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
}

/**
 * Contact page URL used by product CTAs.
 *
 * @return string
 */
function hubbearing_get_product_contact_url()
{
    return home_url('/#contact');
}

/**
 * Enable admin product list search to search custom post meta fields: product_mpn, oem_num
 */
function hubbearing_product_admin_search_join($join, $query)
{
    global $wpdb, $pagenow;

    if (!is_admin() || !$query->is_main_query() || $pagenow !== 'edit.php') {
        return $join;
    }

    $post_type = $query->get('post_type');
    if ($post_type !== 'product' && (!is_array($post_type) || !in_array('product', $post_type, true))) {
        return $join;
    }

    $search_term = $query->get('s');
    if (empty(trim((string) $search_term))) {
        return $join;
    }

    if (strpos($join, 'hubbearing_pm_search') === false) {
        $join .= " LEFT JOIN {$wpdb->postmeta} AS hubbearing_pm_search ON ({$wpdb->posts}.ID = hubbearing_pm_search.post_id) ";
    }

    return $join;
}
add_filter('posts_join', 'hubbearing_product_admin_search_join', 10, 2);

function hubbearing_product_admin_posts_search($search, $query)
{
    global $wpdb, $pagenow;

    if (!is_admin() || !$query->is_main_query() || $pagenow !== 'edit.php') {
        return $search;
    }

    $post_type = $query->get('post_type');
    if ($post_type !== 'product' && (!is_array($post_type) || !in_array('product', $post_type, true))) {
        return $search;
    }

    $search_term = $query->get('s');
    if (empty(trim((string) $search_term))) {
        return $search;
    }

    $allowed_meta_keys = apply_filters('hubbearing_product_admin_search_meta_keys', [
        'product_mpn',
        'oem_num',
    ]);

    if (empty($allowed_meta_keys)) {
        return $search;
    }

    $like = '%' . $wpdb->esc_like(trim((string) $search_term)) . '%';
    $key_placeholders = implode(', ', array_fill(0, count($allowed_meta_keys), '%s'));

    $search = $wpdb->prepare(
        " AND (
            {$wpdb->posts}.post_title LIKE %s
            OR {$wpdb->posts}.post_excerpt LIKE %s
            OR {$wpdb->posts}.post_content LIKE %s
            OR (hubbearing_pm_search.meta_key IN ($key_placeholders) AND hubbearing_pm_search.meta_value LIKE %s)
        ) ",
        ...array_merge([$like, $like, $like], $allowed_meta_keys, [$like])
    );

    return $search;
}
add_filter('posts_search', 'hubbearing_product_admin_posts_search', 10, 2);

function hubbearing_product_admin_search_distinct($distinct, $query)
{
    global $pagenow;

    if (!is_admin() || !$query->is_main_query() || $pagenow !== 'edit.php') {
        return $distinct;
    }

    $post_type = $query->get('post_type');
    if ($post_type !== 'product' && (!is_array($post_type) || !in_array('product', $post_type, true))) {
        return $distinct;
    }

    $search_term = $query->get('s');
    if (empty(trim((string) $search_term))) {
        return $distinct;
    }

    return 'DISTINCT';
}
add_filter('posts_distinct', 'hubbearing_product_admin_search_distinct', 10, 2);

