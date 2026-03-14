<?php 

add_action('wp_head', function () {
    if (is_singular('product')) {
        $post_id = get_the_ID();

        // 1. Technical Specs - Simplified Logic
        // We define the map, then let the loop filter out empty ones
        $spec_map = [
            'Inner Diameter' => ws_get_post_meta('inner_diameter', $post_id),
            'Outer Diameter' => ws_get_post_meta('outer_diameter', $post_id),
            'Weight' => ws_get_post_meta('weight', $post_id),
            'ABS' => ws_get_post_meta('abs_included', $post_id),
        ];

        $properties = [];
        foreach ($spec_map as $name => $value) {
            if ($value) { // This individual check is more robust
                $properties[] = [
                    "@type" => "PropertyValue",
                    "name" => $name,
                    "value" => esc_attr($value)
                ];
            }
        }

        // 2. Automated MPN Recovery
        $raw_title = get_the_title();
        $mpn = ws_get_post_meta('product_mpn', $post_id);
        if (!$mpn) {
            preg_match('/(\d{5}-\d{5})|(\d{6,8})/', $raw_title, $matches);
            $mpn = !empty($matches[0]) ? $matches[0] : 'N/A';
        }

        // 3. Main Product Array     
        $product_url = get_permalink($post_id);
        $schema = [
            "@context" => "https://schema.org/",
            "@type" => "Product",
            "@id" => $product_url . "#product",
            "url" => $product_url,
            "name" => esc_attr(trim(str_ireplace(['China', 'Manufacturers', 'Suppliers', 'Factory', '-'], '', $raw_title))),
            "image" => get_the_post_thumbnail_url($post_id, 'full'),
            "description" => wp_strip_all_tags(get_the_excerpt()),
            "sku" => ws_get_post_meta('product_sku', $post_id) ?: $mpn,
            "mpn" => $mpn,
            "brand" => [
                "@type" => "Brand",
                "name" => ws_get_post_meta('brand_name', $post_id) ?: 'PRORUN'
            ]
        ];

        // 4. THE RFQ PRICE LOGIC
        $price = ws_get_post_meta('product_price', $post_id);
        $schema["offers"] = [
            "@type" => "Offer",
            "url" => $product_url,
            "availability" => "https://schema.org/InStock",
            "itemCondition" => "https://schema.org/NewCondition"
        ];

        if (is_numeric($price) && $price > 0) {
            $schema["offers"]["price"] = $price;
            $schema["offers"]["priceCurrency"] = "USD";
        }

        if (!empty($properties)) {
            $schema["additionalProperty"] = $properties;
        }

        // 5. Output
        echo "\n\n";
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "</script>\n";
    }
});



// Combined BlogPosting and Person Schema
add_action('wp_head', function () {
    if (is_singular('post')) {
        global $post;

        $author_id = get_post_field('post_author', $post->ID);
        $author_name = get_the_author_meta('display_name', $author_id);

        $agency_url = "https://mjwebagency.com";
        $author_node_id = $agency_url . "/#/schema/person/" . $author_id;

        // 1. Build the Person Schema
        $person_schema = [
            "@type" => "Person",
            "@id" => $author_node_id,
            "name" => $author_name,
            "jobTitle" => "Front-End Architect", // Update or make dynamic via ACF
            "worksFor" => [
                "@type" => "Organization",
                "name" => "MJ Web Agency",
                "@id" => $agency_url . "/#organization"
            ],
            "url" => get_author_posts_url($author_id),
            "sameAs" => [
                "https://www.linkedin.com/in/your-profile",
                "https://github.com/your-username"
            ]
        ];

        // 2. Build the BlogPosting Schema
        $blog_schema = [
            "@type" => "BlogPosting",
            "headline" => get_the_title(),
            "datePublished" => get_the_date('c'),
            "dateModified" => get_the_modified_date('c'),
            "author" => [
                "@id" => $author_node_id // Seamlessly links to the Person schema above
            ],
            "publisher" => [
                "@type" => "Organization",
                "name" => get_bloginfo('name'),
                "logo" => [
                    "@type" => "ImageObject",
                    "url" => "https://mjwebagency.com/path-to-your-actual-logo.png" // Replaced @id with a concrete URL
                ]
            ],
            "description" => get_the_excerpt($post->ID)
        ];

        // Safely handle the featured image to prevent JSON-LD errors
        $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'full');
        if ($thumbnail_url) {
            $blog_schema['image'] = [$thumbnail_url];
        }
        else {
            // Provide a fallback default image if no featured image exists
            $blog_schema['image'] = ["https://mjwebagency.com/default-blog-image.png"];
        }

        // 3. Combine them into a standard @graph structure
        $combined_schema = [
            "@context" => "https://schema.org",
            "@graph" => [$person_schema, $blog_schema]
        ];

        echo "\n\n";
        echo '<script type="application/ld+json">' . wp_json_encode($combined_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "</script>\n";
    }
});