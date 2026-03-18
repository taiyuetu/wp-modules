<?php 
//同时搜索自定义字段，标题和内容


function custom_search_query( $query ) {
    if ( $query->is_search() && !is_admin() ) {
        // 获取搜索关键词
        $search_term = sanitize_text_field($query->query_vars['s']);

        // 设置 meta_query 以搜索元字段
        $meta_query = array(
            'relation' => 'OR',
            array(
                'key'     => 'oem_num', // 替换为您的元字段键
                'value'   => $search_term,
                'compare' => 'LIKE'
            ),
             array(
                'key'     => 'partnumber', // 替换为您的元字段键
                'value'   => $search_term,
                'compare' => 'LIKE'
            ),
        );

        // 将 meta_query 添加到查询中
        $query->set( 'meta_query', $meta_query );

        // 设置搜索关键词以搜索标题和内容
        $query->set( 's', $search_term ); // 保留搜索关键词
        $query->set( 'post_type', array('product') ); // 搜索所有类型的帖子
        $query->set( 'post_status', 'publish' );
        $query->set( 'post_parent', 0 );


    }
    return $query;
}
add_filter( 'pre_get_posts', 'custom_search_query' );

// 添加自定义搜索条件
function custom_search_where( $where, $query ) {
    global $wpdb;
    
    if ( $query->is_search() && !is_admin() ) {

        $search_term = $query->query_vars['s'];
         $where .= " AND {$wpdb->posts}.post_status = 'publish'";  // Only include published posts
        $where .= " AND {$wpdb->posts}.post_type = 'product'";  // Only search the 'product' post type
        $where .= " OR ({$wpdb->posts}.post_title LIKE '%" . esc_sql( $search_term ) . "%' ";
        $where .= " OR {$wpdb->posts}.post_content LIKE '%" . esc_sql( $search_term ) . "%' ";
        $where .= " OR EXISTS (SELECT * FROM {$wpdb->postmeta} WHERE {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID AND {$wpdb->postmeta}.meta_value LIKE '%" . esc_sql( $search_term ) . "%'))";
        
    }

    return $where;
}
add_filter( 'posts_search', 'custom_search_where', 10, 2 );


function include_product_post_type_in_tag_archive( $query ) {
    if ( $query->is_tag() && $query->is_main_query() && !is_admin() ) {
        // Add custom post type 'product' to the query
        $query->set( 'post_type', ['post', 'product'] );
    }
}
add_action( 'pre_get_posts', 'include_product_post_type_in_tag_archive' );