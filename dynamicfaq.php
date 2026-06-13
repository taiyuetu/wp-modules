<?php
/**
 * dynamic faqs with category and toggled enabled
 */
class Dynamic_FAQ_Plugin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('wp_ajax_save_faq', [$this, 'save_faq']);
        add_action('wp_ajax_delete_faq', [$this, 'delete_faq']);
        add_shortcode('dynamic_faqs', [$this, 'render_faqs_shortcode']);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'Dynamic FAQs',
            'Dynamic FAQs',
            'manage_options',
            'dynamic-faqs',
            [$this, 'render_admin_page'],
            'dashicons-editor-help',
            30
        );
    }

    public function register_settings()
    {
        register_setting('dynamic_faqs_group', 'dynamic_faqs_list');
    }

    public function render_admin_page()
    {
        ?>
        <div class="wrap">
            <?php
            $current_lang = self::get_active_language();
            $option_name = $current_lang ? 'dynamic_faqs_list_' . $current_lang : 'dynamic_faqs_list';
            $groups = self::get_faq_groups($current_lang);
            ?>
            <h1>Manage FAQs
                <?php echo $current_lang ? '(' . strtoupper($current_lang) . ')' : ''; ?>
            </h1>
            <form id="faq-form" method="post">
                <input type="hidden" name="current_lang" value="<?php echo esc_attr($current_lang); ?>">
                <?php
                wp_nonce_field('dynamic_faqs_nonce', 'dynamic_faqs_nonce');
                settings_fields('dynamic_faqs_group');
                ?>
                <div id="faq-container">
                    <?php
                    if (!empty($groups)) {
                        foreach ($groups as $group_index => $group) {
                            $this->render_admin_group($group_index, $group);
                        }
                    } else {
                        $this->render_admin_group(0, ['category' => '', 'faqs' => []]);
                    }
                    ?>
                </div>
                <button type="button" id="add-group-btn" class="button button-primary">Add Category</button>
                <button type="submit" id="save-faqs-btn" class="button button-secondary">Save FAQs</button>
            </form>
        </div>
        <script>
            jQuery(document).ready(function ($) {
                function buildFaqItem(groupIndex) {
                    return `
                    <div class="faq-item">
                        <input type="text" name="faq_question[${groupIndex}][]" placeholder="Question">
                        <textarea name="faq_answer[${groupIndex}][]" placeholder="Answer"></textarea>
                        <button type="button" class="delete-faq-btn">Delete FAQ</button>
                    </div>
                `;
                }

                function buildGroup(groupIndex) {
                    return `
                    <div class="faq-group" data-group-index="${groupIndex}">
                        <div class="faq-group-header">
                            <button type="button" class="faq-group-toggle" aria-expanded="false" aria-label="Toggle category"></button>
                            <input type="text" name="faq_category[]" placeholder="Category / Group name">
                            <button type="button" class="delete-group-btn">Delete Category</button>
                        </div>
                        <div class="faq-group-body">
                            <div class="faq-group-items"></div>
                            <button type="button" class="add-faq-to-group-btn button">Add FAQ to this category</button>
                        </div>
                    </div>
                `;
                }

                $(document).on('click', '.faq-group-toggle', function () {
                    var $group = $(this).closest('.faq-group');
                    var isOpen = $group.hasClass('is-open');
                    $group.toggleClass('is-open', !isOpen);
                    $(this).attr('aria-expanded', !isOpen);
                });

                $('#add-group-btn').on('click', function () {
                    var groupIndex = $('.faq-group').length;
                    $('#faq-container').append(buildGroup(groupIndex));
                });

                $(document).on('click', '.add-faq-to-group-btn', function () {
                    var $group = $(this).closest('.faq-group');
                    var groupIndex = $group.data('group-index');
                    $group.addClass('is-open').find('.faq-group-toggle').attr('aria-expanded', true);
                    $group.find('.faq-group-items').append(buildFaqItem(groupIndex));
                });

                $(document).on('click', '.delete-faq-btn', function () {
                    $(this).closest('.faq-item').remove();
                });

                $(document).on('click', '.delete-group-btn', function () {
                    if ($('.faq-group').length <= 1) {
                        alert('At least one category is required.');
                        return;
                    }
                    $(this).closest('.faq-group').remove();
                    reindexGroups();
                });

                function reindexGroups() {
                    $('.faq-group').each(function (index) {
                        $(this).attr('data-group-index', index);
                        $(this).find('.faq-item input, .faq-item textarea').each(function () {
                            var name = $(this).attr('name');
                            if (name && name.indexOf('faq_question') === 0) {
                                $(this).attr('name', 'faq_question[' + index + '][]');
                            } else if (name && name.indexOf('faq_answer') === 0) {
                                $(this).attr('name', 'faq_answer[' + index + '][]');
                            }
                        });
                    });
                }

                $('#faq-form').on('submit', function (e) {
                    e.preventDefault();
                    var formData = $(this).serialize();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'save_faq',
                            form_data: formData,
                            current_lang: $('input[name="current_lang"]').val(),
                            nonce: '<?php echo wp_create_nonce('dynamic_faqs_save_nonce'); ?>'
                        },
                        success: function (response) {
                            alert('FAQs saved successfully!');
                            location.reload();
                        }
                    });
                });
            });
        </script>
        <style>
            .faq-group {
                margin-bottom: 25px;
                padding: 15px;
                border: 2px solid #c3c4c7;
                background: #f6f7f7;
            }

            .faq-group-header {
                display: flex;
                gap: 10px;
                align-items: center;
                margin-bottom: 0;
            }

            .faq-group.is-open .faq-group-header {
                margin-bottom: 15px;
            }

            .faq-group-header input {
                flex: 1;
                font-size: 16px;
                font-weight: 600;
            }

            .faq-group-toggle {
                flex-shrink: 0;
                width: 28px;
                height: 28px;
                padding: 0;
                border: 1px solid #c3c4c7;
                border-radius: 2px;
                background: #fff;
                cursor: pointer;
                position: relative;
            }

            .faq-group-toggle::before {
                content: '';
                display: block;
                width: 8px;
                height: 8px;
                margin: 8px auto;
                border-right: 2px solid #50575e;
                border-bottom: 2px solid #50575e;
                transform: rotate(45deg);
                transition: transform 0.2s ease;
            }

            .faq-group.is-open .faq-group-toggle::before {
                transform: rotate(-135deg);
                margin-top: 11px;
            }

            .faq-group-body {
                display: none;
            }

            .faq-group.is-open .faq-group-body {
                display: block;
            }

            .faq-item {
                margin-bottom: 15px;
                padding: 10px;
                border: 1px solid #ddd;
                background: #fff;
            }

            .faq-item input,
            .faq-item textarea {
                width: 100%;
                margin-bottom: 10px;
            }
        </style>
        <?php
    }

    private function render_admin_group($group_index, $group)
    {
        $category = isset($group['category']) ? $group['category'] : '';
        $faqs = isset($group['faqs']) ? $group['faqs'] : [];
        ?>
        <div class="faq-group" data-group-index="<?php echo esc_attr($group_index); ?>">
            <div class="faq-group-header">
                <button type="button" class="faq-group-toggle" aria-expanded="false"
                    aria-label="Toggle category"></button>
                <input type="text" name="faq_category[]" placeholder="Category / Group name"
                    value="<?php echo esc_attr($category); ?>">
                <button type="button" class="delete-group-btn">Delete Category</button>
            </div>
            <div class="faq-group-body">
                <div class="faq-group-items">
                    <?php
                    if (!empty($faqs)) {
                        foreach ($faqs as $faq) {
                            ?>
                            <div class="faq-item">
                                <input type="text" name="faq_question[<?php echo esc_attr($group_index); ?>][]"
                                    placeholder="Question" value="<?php echo esc_attr($faq['question']); ?>">
                                <textarea name="faq_answer[<?php echo esc_attr($group_index); ?>][]"
                                    placeholder="Answer"><?php echo esc_textarea($faq['answer']); ?></textarea>
                                <button type="button" class="delete-faq-btn">Delete FAQ</button>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
                <button type="button" class="add-faq-to-group-btn button">Add FAQ to this category</button>
            </div>
        </div>
        <?php
    }

    public function save_faq()
    {
        check_ajax_referer('dynamic_faqs_save_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        parse_str($_POST['form_data'], $form_data);

        $current_lang = isset($_POST['current_lang']) ? sanitize_text_field($_POST['current_lang']) : '';
        $option_name = $current_lang ? 'dynamic_faqs_list_' . $current_lang : 'dynamic_faqs_list';

        $groups = [];
        $categories = isset($form_data['faq_category']) ? $form_data['faq_category'] : [];
        $questions = isset($form_data['faq_question']) ? $form_data['faq_question'] : [];
        $answers = isset($form_data['faq_answer']) ? $form_data['faq_answer'] : [];

        foreach ($categories as $index => $category) {
            $category = sanitize_text_field($category);
            $group_faqs = [];

            if (isset($questions[$index]) && isset($answers[$index])) {
                $count = count($questions[$index]);
                for ($i = 0; $i < $count; $i++) {
                    $question = isset($questions[$index][$i]) ? trim($questions[$index][$i]) : '';
                    if (!empty($question)) {
                        $group_faqs[] = [
                            'question' => sanitize_text_field($questions[$index][$i]),
                            'answer' => wp_kses_post($answers[$index][$i]),
                        ];
                    }
                }
            }

            if (!empty($category) || !empty($group_faqs)) {
                $groups[] = [
                    'category' => $category,
                    'faqs' => $group_faqs,
                ];
            }
        }

        update_option($option_name, $groups);

        wp_send_json_success('FAQs saved for ' . ($current_lang ? strtoupper($current_lang) : 'Default'));
        wp_die();
    }

    public function render_faqs_shortcode()
    {
        $groups = self::get_faq_groups();

        ob_start();
        ?>
        <section class="faq section">
            <div class="faq__inner">
                <?php foreach ($groups as $group) : ?>
                    <?php if (empty($group['faqs'])) {
                        continue;
                    } ?>
                    <div class="faq__category">
                        <?php if (!empty($group['category'])) : ?>
                            <h2 class="faq__category-title"><?php echo esc_html($group['category']); ?></h2>
                        <?php endif; ?>
                        <?php foreach ($group['faqs'] as $faq) : ?>
                            <div class="faq-item">
                                <button type="button" class="faq-item__question">
                                    <span><?php echo esc_html($faq['question']); ?></span>
                                    <span class="faq-item__icon">+</span>
                                </button>
                                <div class="faq-item__answer">
                                    <?php echo wp_kses_post(wpautop($faq['answer'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <script>
            document.querySelectorAll('.faq-item__question').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var item = btn.parentElement;
                    var isOpen = item.classList.contains('open');
                    document.querySelectorAll('.faq-item').forEach(function (i) {
                        i.classList.remove('open');
                    });
                    if (!isOpen) {
                        item.classList.add('open');
                    }
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function enqueue_frontend_scripts()
    {
        wp_enqueue_script('jquery');
    }

  /**
   * Normalize stored FAQ data to grouped format (backward compatible with flat lists).
   *
   * @param array $data Raw option value.
   * @return array
   */
    public static function normalize_faq_groups($data)
    {
        if (empty($data) || !is_array($data)) {
            return [];
        }

        if (isset($data[0]['question']) && !isset($data[0]['faqs'])) {
            return [
                [
                    'category' => '',
                    'faqs' => $data,
                ],
            ];
        }

        return $data;
    }

    public static function get_faq_groups($lang = null)
    {
        if (!$lang) {
            $lang = self::get_active_language();
        }
        $option_name = $lang ? 'dynamic_faqs_list_' . $lang : 'dynamic_faqs_list';
        $data = get_option($option_name, []);

        return self::normalize_faq_groups($data);
    }

    public static function get_faqs($lang = null)
    {
        $groups = self::get_faq_groups($lang);
        $faqs = [];

        foreach ($groups as $group) {
            if (!empty($group['faqs'])) {
                foreach ($group['faqs'] as $faq) {
                    $faqs[] = array_merge($faq, [
                        'category' => isset($group['category']) ? $group['category'] : '',
                    ]);
                }
            }
        }

        return $faqs;
    }

    public static function get_active_language()
    {
        if (!function_exists('pll_current_language')) {
            return false;
        }

        if (is_admin()) {
            if (isset($_GET['lang']) && !empty($_GET['lang']) && $_GET['lang'] !== 'all') {
                return sanitize_text_field($_GET['lang']);
            }
            return pll_default_language('slug');
        }

        return pll_current_language('slug');
    }
}

new Dynamic_FAQ_Plugin();

/**
 * Get FAQs as a flat list (each item includes category).
 *
 * @param string|null $lang Optional language slug (e.g. 'en', 'fr'). Defaults to current language.
 * @return array Array of FAQs with 'question', 'answer', and 'category' keys.
 */
function ruixing_get_faqs($lang = null)
{
    return Dynamic_FAQ_Plugin::get_faqs($lang);
}

/**
 * Get FAQs grouped by category.
 *
 * @param string|null $lang Optional language slug (e.g. 'en', 'fr'). Defaults to current language.
 * @return array Array of groups with 'category' and 'faqs' keys.
 */
function ruixing_get_faq_groups($lang = null)
{
    return Dynamic_FAQ_Plugin::get_faq_groups($lang);
}
