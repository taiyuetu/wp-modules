<?php
/**
 * Plugin Name: Custom Slider Post Type
 * Description: A custom post type for managing sliders with multiple slides (image, name, description, link).
 * Version: 1.0.0
 * Author: Custom Dev
 */

if (!defined('ABSPATH'))
    exit;

// ─────────────────────────────────────────────
// 1. Register Custom Post Type: Slider
// ─────────────────────────────────────────────
function csp_register_post_type()
{
    $labels = [
        'name' => _x('Sliders', 'Post type general name', 'tqb'),
        'singular_name' => _x('Slider', 'Post type singular name', 'tqb'),
        'add_new' => __('Add New', 'tqb'),
        'add_new_item' => __('Add New Slider', 'tqb'),
        'edit_item' => __('Edit Slider', 'tqb'),
        'new_item' => __('New Slider', 'tqb'),
        'view_item' => __('View Slider', 'tqb'),
        'search_items' => __('Search Sliders', 'tqb'),
        'not_found' => __('No sliders found', 'tqb'),
        'not_found_in_trash' => __('No sliders found in Trash', 'tqb'),
        'menu_name' => __('Sliders', 'tqb'),
    ];

    register_post_type('csp_slider', [
        'labels' => $labels,
        'public' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-images-alt2',
        'supports' => ['title'],
        'has_archive' => false,
        'rewrite' => ['slug' => 'sliders'],
        'show_in_rest' => false,
    ]);
}
add_action('init', 'csp_register_post_type');

// ─────────────────────────────────────────────
// 2. Add Meta Box for Slides
// ─────────────────────────────────────────────
function csp_add_meta_boxes()
{
    add_meta_box(
        'csp_slides_meta_box',
        __('Slides', 'tqb'),
        'csp_slides_meta_box_callback',
        'csp_slider',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'csp_add_meta_boxes');

function csp_slides_meta_box_callback($post)
{
    wp_nonce_field('csp_save_slides', 'csp_slides_nonce');

    $slides = ws_get_post_meta($post->ID, '_csp_slides', true);
    if (!is_array($slides))
        $slides = [];
?>
    <div id="csp-slides-wrapper">
        <?php foreach ($slides as $index => $slide): ?>
            <?php csp_render_slide_row($index, $slide); ?>
        <?php
    endforeach; ?>
    </div>

    <?php /* Template inside <script type="text/html"> — never parsed or submitted */?>
    <script type="text/html" id="csp-slide-template">
        <?php csp_render_slide_row('__INDEX__', []); ?>
    </script>

    <p>
        <button type="button" id="csp-add-slide" class="button button-primary">
            &#43; <?php esc_html_e('Add Slide', 'tqb'); ?>
        </button>
    </p>

    <style>
        /* ── Slide rows ── */
        .csp-slide-row {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-left: 4px solid #2271b1;
            border-radius: 3px;
            padding: 14px 16px 16px;
            margin-bottom: 12px;
            position: relative;
        }
        .csp-slide-row-header {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            gap: 8px;
            cursor: grab;
            user-select: none;
        }
        .csp-drag-handle {
            color: #aaa;
            font-size: 18px;
            line-height: 1;
            flex-shrink: 0;
        }
        .csp-drag-handle:hover { color: #2271b1; }
        .csp-slide-row-header strong {
            font-size: 13px;
            color: #1d2327;
            flex: 1;
        }
        .csp-remove-slide {
            color: #b32d2e;
            font-size: 12px;
            text-decoration: none;
            flex-shrink: 0;
        }
        .csp-remove-slide:hover { color: #f00; text-decoration: underline; }

        /* ── Fields ── */
        .csp-slide-row table { width: 100%; border-collapse: collapse; }
        .csp-slide-row td { padding: 5px 4px; vertical-align: middle; }
        .csp-slide-row td:first-child { width: 130px; font-weight: 600; font-size: 12px; color: #646970; }
        .csp-slide-row input[type="text"],
        .csp-slide-row textarea { width: 100%; }
        .csp-slide-row textarea { height: 68px; resize: vertical; }
        .csp-slide-thumb {
            max-width: 110px; max-height: 75px;
            display: block; margin-top: 6px;
            border-radius: 3px; border: 1px solid #ddd;
        }

        /* ── Sortable placeholder ── */
        .csp-sortable-placeholder {
            background: #e8f0fa;
            border: 2px dashed #2271b1;
            border-radius: 3px;
            margin-bottom: 12px;
        }
    </style>

    <script>
    jQuery(function($){
        // Counter used only to guarantee unique name attributes while editing.
        // On submit we reindex everything 0,1,2... in DOM order anyway.
        var index = <?php echo count($slides); ?>;

        // ── Add slide ──────────────────────────────────────────
        $('#csp-add-slide').on('click', function(){
            // Read from <script type="text/html"> — never submitted with the form
            var template = document.getElementById('csp-slide-template').innerHTML;
            var newRow   = template.replace(/__INDEX__/g, index);
            $('#csp-slides-wrapper').append(newRow);
            index++;
        });

        // ── Delegated events (work for both existing & new rows) ──
        $('#csp-slides-wrapper')

            // Remove slide
            .on('click', '.csp-remove-slide', function(e){
                e.preventDefault();
                if ( confirm('<?php echo esc_js(__('Remove this slide?', 'tqb')); ?>') ) {
                    $(this).closest('.csp-slide-row').remove();
                }
            })

            // Remove image
            .on('click', '.csp-remove-image', function(e){
                e.preventDefault();
                var row = $(this).closest('.csp-slide-row');
                row.find('.csp-image-id').val('');
                row.find('.csp-image-url').val('');
                row.find('.csp-slide-thumb').remove();
                $(this).hide();
            })

            // Upload image
            .on('click', '.csp-upload-image', function(e){
                e.preventDefault();
                var btn = $(this);
                var row = btn.closest('.csp-slide-row');
                var frame = wp.media({
                    title: '<?php echo esc_js(__('Select Slide Image', 'tqb')); ?>',
                    button: { text: '<?php echo esc_js(__('Use this image', 'tqb')); ?>' },
                    multiple: false
                });
                frame.on('select', function(){
                    var att = frame.state().get('selection').first().toJSON();
                    row.find('.csp-image-id').val( att.id );
                    row.find('.csp-image-url').val( att.url );
                    var thumb = row.find('.csp-slide-thumb');
                    if ( thumb.length ) {
                        thumb.attr('src', att.url);
                    } else {
                        btn.before('<img class="csp-slide-thumb" src="' + att.url + '" />');
                    }
                    row.find('.csp-remove-image').show();
                });
                frame.open();
            });

        // ── Sortable ───────────────────────────────────────────
        $('#csp-slides-wrapper').sortable({
            handle  : '.csp-slide-row-header',
            axis    : 'y',
            opacity : 0.7,
            placeholder : 'csp-sortable-placeholder',
            start: function(e, ui){
                ui.placeholder.height( ui.item.outerHeight() );
            }
        });

        // ── Reindex on submit ──────────────────────────────────
        // This ensures the POST data reflects the current DOM order,
        // fixing both the drag-reorder persistence and the ghost empty
        // slide that appeared when the hidden template's __INDEX__ key
        // was submitted alongside real slides.
        $( '#post' ).on( 'submit', function(){
            $( '#csp-slides-wrapper .csp-slide-row' ).each(function( newIndex ){
                $( this ).find( '[name]' ).each(function(){
                    // name="csp_slides[ANY][field]" → csp_slides[newIndex][field]
                    var oldName = $( this ).attr('name');
                    var newName = oldName.replace( /csp_slides\[\d+\]/, 'csp_slides[' + newIndex + ']' );
                    $( this ).attr( 'name', newName );
                });
            });
        });

    });
    </script>
    <?php
}

function csp_render_slide_row($index, $slide)
{
    $image_id = isset($slide['image_id']) ? esc_attr($slide['image_id']) : '';
    $image_url = isset($slide['image_url']) ? esc_attr($slide['image_url']) : '';
    $name = isset($slide['name']) ? esc_attr($slide['name']) : '';
    $desc = isset($slide['description']) ? esc_textarea($slide['description']) : '';
    $link = isset($slide['link']) ? esc_attr($slide['link']) : '';
    $hide_remove = $image_url ? '' : 'style="display:none;"';
?>
    <div class="csp-slide-row">
        <div class="csp-slide-row-header">
            <span class="csp-drag-handle dashicons dashicons-move" title="<?php esc_attr_e('Drag to reorder', 'tqb'); ?>"></span>
            <strong><?php esc_html_e('Slide', 'tqb'); ?></strong>
            <a href="#" class="csp-remove-slide">&#10005; <?php esc_html_e('Remove', 'tqb'); ?></a>
        </div>
        <table>
            <tr>
                <td><label><?php esc_html_e('Image', 'tqb'); ?></label></td>
                <td>
                    <input type="hidden" name="csp_slides[<?php echo $index; ?>][image_id]"  class="csp-image-id"  value="<?php echo $image_id; ?>" />
                    <input type="hidden" name="csp_slides[<?php echo $index; ?>][image_url]" class="csp-image-url" value="<?php echo $image_url; ?>" />
                    <?php if ($image_url): ?>
                        <img src="<?php echo $image_url; ?>" class="csp-slide-thumb" />
                    <?php
    endif; ?>
                    <button type="button" class="button csp-upload-image"><?php esc_html_e('Choose Image', 'tqb'); ?></button>
                    <a href="#" class="csp-remove-image" <?php echo $hide_remove; ?>> &nbsp;<?php esc_html_e('Remove Image', 'tqb'); ?></a>
                </td>
            </tr>
            <tr>
                <td><label><?php esc_html_e('Slide Name', 'tqb'); ?></label></td>
                <td>
                    <input type="text"
                           name="csp_slides[<?php echo $index; ?>][name]"
                           value="<?php echo $name; ?>"
                           placeholder="<?php esc_attr_e('Enter slide name', 'tqb'); ?>" />
                </td>
            </tr>
            <tr>
                <td><label><?php esc_html_e('Description', 'tqb'); ?></label></td>
                <td>
                    <textarea name="csp_slides[<?php echo $index; ?>][description]"
                               placeholder="<?php esc_attr_e('Enter slide description', 'tqb'); ?>"><?php echo $desc; ?></textarea>
                </td>
            </tr>
            <tr>
                <td><label><?php esc_html_e('Link URL', 'tqb'); ?></label></td>
                <td>
                    <input type="text"
                           name="csp_slides[<?php echo $index; ?>][link]"
                           value="<?php echo $link; ?>"
                           placeholder="https://example.com" />
                </td>
            </tr>
        </table>
    </div>
    <?php
}

// ─────────────────────────────────────────────
// 3. Save Meta Box Data
// ─────────────────────────────────────────────
function csp_save_slides($post_id)
{
    if (!isset($_POST['csp_slides_nonce']))
        return;
    if (!wp_verify_nonce($_POST['csp_slides_nonce'], 'csp_save_slides'))
        return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;

    $slides = [];

    if (!empty($_POST['csp_slides']) && is_array($_POST['csp_slides'])) {
        foreach ($_POST['csp_slides'] as $slide) {
            // Skip the template placeholder row if it ever slips through
            $name = sanitize_text_field($slide['name'] ?? '');
            $url = esc_url_raw($slide['image_url'] ?? '');
            $desc = sanitize_textarea_field($slide['description'] ?? '');
            $link = esc_url_raw($slide['link'] ?? '');

            // Skip completely empty rows (no data at all)
            if ($name === '' && $url === '' && $desc === '' && $link === '') {
                continue;
            }

            $slides[] = [
                'image_id' => absint($slide['image_id'] ?? 0),
                'image_url' => $url,
                'name' => $name,
                'description' => $desc,
                'link' => $link,
            ];
        }
    }

    update_post_meta($post_id, '_csp_slides', $slides);
}
add_action('save_post_csp_slider', 'csp_save_slides');

// ─────────────────────────────────────────────
// 4. Enqueue WP Media uploader on Slider CPT screen
// ─────────────────────────────────────────────
function csp_admin_enqueue($hook)
{
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'csp_slider') {
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
    }
}
add_action('admin_enqueue_scripts', 'csp_admin_enqueue');

// ─────────────────────────────────────────────
// 5. Shortcode: [csp_slider id="123"]
// ─────────────────────────────────────────────
function csp_slider_shortcode($atts)
{
    $atts = shortcode_atts(['id' => 0], $atts);
    $post_id = absint($atts['id']);

    if (!$post_id)
        return '<p>' . esc_html__('Please provide a slider ID.', 'tqb') . '</p>';

    $slides = ws_get_post_meta($post_id, '_csp_slides', true);
    if (empty($slides))
        return '';

    $uid = 'csp-slider-' . $post_id;
    ob_start();
?>
    <div class="csp-slider" id="<?php echo esc_attr($uid); ?>">
        <div class="csp-slides-track">
            <?php foreach ($slides as $i => $slide):
        $active = $i === 0 ? ' csp-active' : '';
?>
            <?php if ($slide['link']): ?>
                <a href="<?php echo esc_url($slide['link']); ?>" class="csp-slide<?php echo $active; ?>">
                    <?php if ($slide['image_url']): ?>
                        <img src="<?php echo esc_url($slide['image_url']); ?>"
                             alt="<?php echo esc_attr($slide['name'] ?? ''); ?>" />
                    <?php
            endif; ?>
                </a>
            <?php
        else: ?>
                <div class="csp-slide<?php echo $active; ?>">
                    <?php if ($slide['image_url']): ?>
                        <img src="<?php echo esc_url($slide['image_url']); ?>"
                             alt="<?php echo esc_attr($slide['name'] ?? ''); ?>" />
                    <?php
            endif; ?>
                </div>
            <?php
        endif; ?>
            <?php
    endforeach; ?>
        </div>

        <?php if (count($slides) > 1): ?>
        <button class="csp-nav csp-prev" aria-label="Previous">&#8592;</button>
        <button class="csp-nav csp-next" aria-label="Next">&#8594;</button>
        <div class="csp-dots">
            <?php foreach ($slides as $i => $slide): ?>
                <span class="csp-dot<?php echo $i === 0 ? ' csp-dot-active' : ''; ?>"
                      data-index="<?php echo $i; ?>"></span>
            <?php
        endforeach; ?>
        </div>
        <?php
    endif; ?>
    </div>

    <style>
    .csp-slider { position: relative; overflow: hidden; width: 100%; background: #111; }
    .csp-slides-track { display: flex; transition: transform .5s ease; }
    .csp-slide { min-width: 100%; position: relative; }
    .csp-slide img { width: 100%; height: auto; display: block; }
    .csp-slide { display: block; text-decoration: none; }
    .csp-nav {
        position: absolute; top: 50%; transform: translateY(-50%);
        background: rgba(255,255,255,.2); color: #fff; border: none;
        font-size: 22px; padding: 10px 16px; cursor: pointer;
        border-radius: 3px; transition: background .2s;
    }
    .csp-nav:hover { background: rgba(255,255,255,.4); }
    .csp-prev { left: 12px; }
    .csp-next { right: 12px; }
    .csp-dots { position: absolute; bottom: 14px; width: 100%; text-align: center; }
    .csp-dot {
        display: inline-block; width: 10px; height: 10px;
        border-radius: 50%; background: rgba(255,255,255,.5);
        margin: 0 4px; cursor: pointer; transition: background .3s;
    }
    .csp-dot-active { background: #fff; }
    </style>

    <script>
    (function(){
        var wrap   = document.getElementById('<?php echo esc_js($uid); ?>');
        if (!wrap) return;
        var track  = wrap.querySelector('.csp-slides-track');
        var slides = wrap.querySelectorAll('.csp-slide');
        var dots   = wrap.querySelectorAll('.csp-dot');
        var total  = slides.length;
        var current = 0;
        var timer;

        function goTo(n) {
            current = (n + total) % total;
            track.style.transform = 'translateX(-' + (current * 100) + '%)';
            dots.forEach(function(d,i){ d.classList.toggle('csp-dot-active', i === current); });
        }

        var prev = wrap.querySelector('.csp-prev');
        var next = wrap.querySelector('.csp-next');
        if (prev) prev.addEventListener('click', function(){ goTo(current - 1); resetTimer(); });
        if (next) next.addEventListener('click', function(){ goTo(current + 1); resetTimer(); });
        dots.forEach(function(d){ d.addEventListener('click', function(){ goTo(+d.dataset.index); resetTimer(); }); });

        function resetTimer(){
            clearInterval(timer);
            timer = setInterval(function(){ goTo(current + 1); }, 5000);
        }
        resetTimer();
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('csp_slider', 'csp_slider_shortcode');

// ─────────────────────────────────────────────
// 6. Show shortcode hint in the admin list table
// ─────────────────────────────────────────────
function csp_add_shortcode_column($columns)
{
    $columns['csp_shortcode'] = __('Shortcode', 'tqb');
    return $columns;
}
add_filter('manage_csp_slider_posts_columns', 'csp_add_shortcode_column');

function csp_render_shortcode_column($column, $post_id)
{
    if ($column === 'csp_shortcode') {
        echo '<code>[csp_slider id="' . $post_id . '"]</code>';
    }
}
add_action('manage_csp_slider_posts_custom_column', 'csp_render_shortcode_column', 10, 2);