<?php
defined( 'ABSPATH' ) || exit;

class PLSEO_Admin {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_notices', [ $this, 'show_notices' ] );
        add_action( 'wp_ajax_plseo_ping_sitemap', [ $this, 'ajax_ping_sitemap' ] );
    }

    public function register_menu(): void {
        add_menu_page(
            __( 'Polylang SEO', 'polylang-seo' ),
            __( 'Polylang SEO', 'polylang-seo' ),
            'manage_options',
            'polylang-seo',
            [ $this, 'render_general_page' ],
            'dashicons-search',
            80
        );

        $pages = [
            [ 'slug' => 'polylang-seo', 'title' => __( 'General', 'polylang-seo' ), 'callback' => 'render_general_page' ],
            [ 'slug' => 'plseo-sitemap', 'title' => __( 'Sitemap', 'polylang-seo' ), 'callback' => 'render_sitemap_page' ],
            [ 'slug' => 'plseo-social', 'title' => __( 'Social / OG', 'polylang-seo' ), 'callback' => 'render_social_page' ],
            [ 'slug' => 'plseo-schema', 'title' => __( 'Structured Data', 'polylang-seo' ), 'callback' => 'render_schema_page' ],
            [ 'slug' => 'plseo-robots', 'title' => __( 'Robots & Index', 'polylang-seo' ), 'callback' => 'render_robots_page' ],
            [ 'slug' => 'plseo-performance', 'title' => __( 'Performance', 'polylang-seo' ), 'callback' => 'render_performance_page' ],
            [ 'slug' => 'plseo-tools', 'title' => __( 'Tools', 'polylang-seo' ), 'callback' => 'render_tools_page' ],
        ];

        foreach ( $pages as $page ) {
            add_submenu_page(
                'polylang-seo',
                $page['title'],
                $page['title'],
                'manage_options',
                $page['slug'],
                [ $this, $page['callback'] ]
            );
        }
    }

    public function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, 'polylang-seo' ) && false === strpos( $hook, 'plseo' ) ) {
            return;
        }

        wp_enqueue_style( 'plseo-admin', PLSEO_URL . 'admin/assets/css/admin.css', [], PLSEO_VERSION );
        wp_enqueue_script( 'plseo-admin', PLSEO_URL . 'admin/assets/js/admin.js', [ 'jquery' ], PLSEO_VERSION, true );

        wp_localize_script(
            'plseo-admin',
            'plseoAdmin',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'plseo_admin_nonce' ),
                'i18n'    => [
                    'pinging'  => __( 'Pinging...', 'polylang-seo' ),
                    'pingDone' => __( 'Search engines notified.', 'polylang-seo' ),
                    'pingFail' => __( 'Ping failed. Try again.', 'polylang-seo' ),
                ],
            ]
        );
    }

    public function show_notices(): void {
        $screen = get_current_screen();
        if ( ! $screen || ( false === strpos( $screen->id, 'polylang-seo' ) && false === strpos( $screen->id, 'plseo' ) ) ) {
            return;
        }

        if ( get_transient( 'plseo_settings_saved' ) ) {
            delete_transient( 'plseo_settings_saved' );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'polylang-seo' ) . '</p></div>';
        }
    }

    public function ajax_ping_sitemap(): void {
        check_ajax_referer( 'plseo_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'polylang-seo' ) ], 403 );
        }

        PLSEO_Sitemap::get_instance()->ping_search_engines();
        wp_send_json_success( [ 'message' => __( 'Search engines notified.', 'polylang-seo' ) ] );
    }

    public function render_general_page(): void {
        $this->render_settings_page( 'general' );
    }

    public function render_sitemap_page(): void {
        $this->render_settings_page( 'sitemap' );
    }

    public function render_social_page(): void {
        $this->render_settings_page( 'social' );
    }

    public function render_schema_page(): void {
        $this->render_settings_page( 'schema' );
    }

    public function render_robots_page(): void {
        $this->render_settings_page( 'robots' );
    }

    public function render_performance_page(): void {
        $this->render_settings_page( 'performance' );
    }

    public function render_tools_page(): void {
        if ( isset( $_POST['plseo_action'] ) && 'flush_rewrite' === sanitize_text_field( wp_unslash( $_POST['plseo_action'] ) ) ) {
            check_admin_referer( 'plseo_flush_rewrite' );
            flush_rewrite_rules();
            echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Rewrite rules flushed.', 'polylang-seo' ) . '</p></div>';
        }

        include PLSEO_DIR . 'admin/views/header.php';
        ?>
        <div class="plseo-card">
            <h2><?php esc_html_e( 'Sitemap Tools', 'polylang-seo' ); ?></h2>
            <p>
                <a href="<?php echo esc_url( home_url( 'sitemap.xml' ) ); ?>" target="_blank" class="button">
                    <?php esc_html_e( 'View Sitemap Index', 'polylang-seo' ); ?>
                </a>
                <button id="plseo-ping-btn" type="button" class="button button-primary">
                    <?php esc_html_e( 'Ping Search Engines', 'polylang-seo' ); ?>
                </button>
                <span id="plseo-ping-msg"></span>
            </p>
        </div>

        <div class="plseo-card">
            <h2><?php esc_html_e( 'Maintenance', 'polylang-seo' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'plseo_flush_rewrite' ); ?>
                <input type="hidden" name="plseo_action" value="flush_rewrite" />
                <button type="submit" class="button"><?php esc_html_e( 'Flush Rewrite Rules', 'polylang-seo' ); ?></button>
            </form>
        </div>
        <?php
    }

    private function render_settings_page( string $tab ): void {
        if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'plseo_settings' ) ) {
            $this->save_settings( $tab );
            set_transient( 'plseo_settings_saved', true, 30 );
        }

        $template = PLSEO_DIR . 'admin/views/settings-' . $tab . '.php';
        include file_exists( $template ) ? $template : PLSEO_DIR . 'admin/views/settings-general.php';
    }

    private function save_settings( string $tab ): void {
        foreach ( $this->get_tab_fields( $tab ) as $key => $type ) {
            $option_key = 'plseo_' . $key;

            switch ( $type ) {
                case 'checkbox':
                    update_option( $option_key, isset( $_POST[ $option_key ] ) );
                    break;
                case 'multicheck':
                    $value = isset( $_POST[ $option_key ] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST[ $option_key ] ) ) : [];
                    update_option( $option_key, $value );
                    break;
                case 'url':
                    update_option( $option_key, esc_url_raw( wp_unslash( $_POST[ $option_key ] ?? '' ) ) );
                    break;
                case 'textarea':
                    update_option( $option_key, sanitize_textarea_field( wp_unslash( $_POST[ $option_key ] ?? '' ) ) );
                    break;
                case 'number':
                    update_option( $option_key, absint( $_POST[ $option_key ] ?? 0 ) );
                    break;
                case 'post_ids':
                    $ids = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) wp_unslash( $_POST[ $option_key ] ?? '' ) ) ) );
                    update_option( $option_key, array_values( $ids ) );
                    break;
                case 'cpt_archive_seo':
                    $value = isset( $_POST[ $option_key ] ) && is_array( $_POST[ $option_key ] ) ? wp_unslash( $_POST[ $option_key ] ) : [];
                    update_option( $option_key, $this->sanitize_cpt_archive_seo_settings( $value ) );
                    break;
                default:
                    update_option( $option_key, sanitize_text_field( wp_unslash( $_POST[ $option_key ] ?? '' ) ) );
                    break;
            }
        }
    }

    private function get_tab_fields( string $tab ): array {
        $fields = [
            'general'     => [
                'title_separator'      => 'text',
                'homepage_title'       => 'text',
                'homepage_description' => 'textarea',
                'title_format_single'  => 'text',
                'title_format_archive' => 'text',
                'title_format_search'  => 'text',
                'title_format_404'     => 'text',
                'enabled_post_types'   => 'multicheck',
                'enabled_taxonomies'   => 'multicheck',
                'cpt_archive_seo'      => 'cpt_archive_seo',
            ],
            'sitemap'     => [
                'sitemap_enabled'            => 'checkbox',
                'sitemap_include_posts'      => 'checkbox',
                'sitemap_include_pages'      => 'checkbox',
                'sitemap_include_cpt'        => 'multicheck',
                'sitemap_include_taxonomies' => 'checkbox',
                'sitemap_images'             => 'checkbox',
                'sitemap_hreflang'           => 'checkbox',
                'sitemap_x_default'          => 'checkbox',
                'sitemap_ping_google'        => 'checkbox',
                'sitemap_ping_bing'          => 'checkbox',
                'sitemap_posts_per_type'     => 'number',
                'sitemap_exclude_ids'        => 'post_ids',
            ],
            'social'      => [
                'og_enabled'        => 'checkbox',
                'og_default_image'  => 'url',
                'fb_app_id'         => 'text',
                'fb_admins'         => 'text',
                'social_facebook'   => 'url',
                'social_twitter'    => 'url',
                'social_linkedin'   => 'url',
                'twitter_enabled'   => 'checkbox',
                'twitter_card_type' => 'text',
                'twitter_site'      => 'text',
                'twitter_creator'   => 'text',
            ],
            'schema'      => [
                'schema_enabled'        => 'checkbox',
                'schema_org_type'       => 'text',
                'schema_org_name'       => 'text',
                'schema_org_url'        => 'url',
                'schema_org_logo'       => 'url',
                'schema_breadcrumb'     => 'checkbox',
                'schema_article'        => 'checkbox',
                'schema_local_business' => 'checkbox',
                'schema_lb_type'        => 'text',
                'schema_lb_address'     => 'textarea',
                'schema_lb_phone'       => 'text',
                'schema_lb_hours'       => 'text',
            ],
            'robots'      => [
                'noindex_search'       => 'checkbox',
                'noindex_404'          => 'checkbox',
                'noindex_author'       => 'checkbox',
                'noindex_date'         => 'checkbox',
                'noindex_attachment'   => 'checkbox',
                'noindex_empty_taxonomy' => 'checkbox',
                'robots_txt_additions' => 'textarea',
                'canonical_enabled'    => 'checkbox',
                'canonical_force_https' => 'checkbox',
                'canonical_trailing_slash' => 'checkbox',
                'canonical_strip_params' => 'checkbox',
                'redirect_404_home'    => 'checkbox',
                'redirect_old_slugs'   => 'checkbox',
            ],
            'performance' => [
                'remove_rsd_link'    => 'checkbox',
                'remove_shortlink'   => 'checkbox',
                'remove_oembed'      => 'checkbox',
                'preconnect_domains' => 'textarea',
                'dns_prefetch'       => 'textarea',
            ],
        ];

        return $fields[ $tab ] ?? [];
    }

    private function sanitize_cpt_archive_seo_settings( array $raw ): array {
        $allowed_fields = [
            'inherit_defaults',
            'title',
            'description',
            'canonical',
            'og_title',
            'og_description',
            'og_image',
            'twitter_image',
        ];

        $sanitized = [];

        foreach ( $raw as $post_type => $settings ) {
            if ( ! is_string( $post_type ) || ! post_type_exists( $post_type ) || ! is_array( $settings ) ) {
                continue;
            }

            foreach ( $allowed_fields as $field ) {
                if ( 'inherit_defaults' === $field ) {
                    $sanitized[ $post_type ][ $field ] = isset( $settings[ $field ] ) ? '1' : '';
                    continue;
                }

                $value = $settings[ $field ] ?? '';
                if ( ! is_string( $value ) ) {
                    $value = '';
                }

                if ( in_array( $field, [ 'canonical', 'og_image', 'twitter_image' ], true ) ) {
                    $sanitized[ $post_type ][ $field ] = esc_url_raw( $value );
                } elseif ( in_array( $field, [ 'description', 'og_description' ], true ) ) {
                    $sanitized[ $post_type ][ $field ] = sanitize_textarea_field( $value );
                } else {
                    $sanitized[ $post_type ][ $field ] = sanitize_text_field( $value );
                }
            }
        }

        return $sanitized;
    }
}
