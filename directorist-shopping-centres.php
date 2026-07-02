<?php
/**
 * Plugin Name: Directorist Shopping Centres
 * Description: Adds dynamic shopping centre tiles and centre deal pages for Directorist listings.
 * Version: 1.0.0
 * Author: InStoreOnly
 * Text Domain: directorist-shopping-centres
 * Requires Plugins: directorist
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Directorist_Shopping_Centres {
    const VERSION         = '1.0.0';
    const TAXONOMY        = 'at_biz_dir-shopping-centre';
    const TERM_IMAGE_META = '_dsc_image_id';

    private static $instance = null;
    private $syncing_listing = false;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate() {
        self::register_taxonomy_static();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    private function __construct() {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
        add_filter( 'parent_file', [ $this, 'set_active_admin_parent_file' ] );
        add_filter( 'submenu_file', [ $this, 'set_active_admin_submenu_file' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( self::TAXONOMY . '_add_form_fields', [ $this, 'add_term_image_field' ] );
        add_action( self::TAXONOMY . '_edit_form_fields', [ $this, 'edit_term_image_field' ] );
        add_action( 'created_' . self::TAXONOMY, [ $this, 'save_term_image' ] );
        add_action( 'edited_' . self::TAXONOMY, [ $this, 'save_term_image' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_frontend_assets' ], 5 );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_frontend_assets' ] );
        add_shortcode( 'directorist_shopping_centres', [ $this, 'shopping_centres_shortcode' ] );
        add_shortcode( 'directorist_shopping_centre_deals', [ $this, 'centre_deals_shortcode' ] );
        add_filter( 'template_include', [ $this, 'template_include' ] );
        add_filter( 'body_class', [ $this, 'body_class' ] );
        add_action( 'atbdp_listing_inserted', [ $this, 'sync_listing_from_directorist_meta' ], 30 );
        add_action( 'atbdp_listing_updated', [ $this, 'sync_listing_from_directorist_meta' ], 30 );
        add_action( 'save_post_at_biz_dir', [ $this, 'sync_listing_from_directorist_meta' ], 99 );
        add_action( 'elementor/elements/categories_registered', [ $this, 'register_elementor_category' ] );
        add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widget' ] );
    }

    public function register_taxonomy() {
        self::register_taxonomy_static();
    }

    public static function register_taxonomy_static() {
        $post_type = defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir';

        register_taxonomy(
            self::TAXONOMY,
            [ $post_type ],
            [
                'labels'            => [
                    'name'                       => __( 'Shopping Centres', 'directorist-shopping-centres' ),
                    'singular_name'              => __( 'Shopping Centre', 'directorist-shopping-centres' ),
                    'menu_name'                  => __( 'All Shopping Centres', 'directorist-shopping-centres' ),
                    'search_items'               => __( 'Search Shopping Centres', 'directorist-shopping-centres' ),
                    'all_items'                  => __( 'All Shopping Centres', 'directorist-shopping-centres' ),
                    'edit_item'                  => __( 'Edit Shopping Centre', 'directorist-shopping-centres' ),
                    'update_item'                => __( 'Update Shopping Centre', 'directorist-shopping-centres' ),
                    'add_new_item'               => __( 'Add New Shopping Centre', 'directorist-shopping-centres' ),
                    'new_item_name'              => __( 'New Shopping Centre Name', 'directorist-shopping-centres' ),
                    'popular_items'              => __( 'Popular Shopping Centres', 'directorist-shopping-centres' ),
                    'separate_items_with_commas' => __( 'Separate shopping centres with commas', 'directorist-shopping-centres' ),
                    'add_or_remove_items'        => __( 'Add or remove shopping centres', 'directorist-shopping-centres' ),
                    'choose_from_most_used'      => __( 'Choose from most used shopping centres', 'directorist-shopping-centres' ),
                ],
                'public'            => true,
                'show_ui'           => true,
                'show_in_menu'      => false,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'hierarchical'      => false,
                'query_var'         => true,
                'rewrite'           => [
                    'slug'       => 'shopping-centre',
                    'with_front' => false,
                ],
            ]
        );
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=at_biz_dir',
            __( 'All Shopping Centres', 'directorist-shopping-centres' ),
            __( 'All Shopping Centres', 'directorist-shopping-centres' ),
            'manage_categories',
            $this->get_shopping_centres_menu_slug()
        );

        add_submenu_page(
            'edit.php?post_type=at_biz_dir',
            __( 'Shopping Centre Tools', 'directorist-shopping-centres' ),
            __( 'Shopping Centre Tools', 'directorist-shopping-centres' ),
            'manage_options',
            'directorist-shopping-centres',
            [ $this, 'render_admin_page' ]
        );
    }

    public function set_active_admin_parent_file( $parent_file ) {
        if ( $this->is_shopping_centre_admin_screen() ) {
            return 'edit.php?post_type=at_biz_dir';
        }

        return $parent_file;
    }

    public function set_active_admin_submenu_file( $submenu_file ) {
        if ( $this->is_shopping_centre_admin_screen() ) {
            return $this->get_shopping_centres_menu_slug();
        }

        return $submenu_file;
    }

    private function get_shopping_centres_menu_slug() {
        return 'edit-tags.php?taxonomy=' . self::TAXONOMY . '&post_type=at_biz_dir';
    }

    private function is_shopping_centre_admin_screen() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( $screen && self::TAXONOMY === $screen->taxonomy ) {
            return true;
        }

        return isset( $_GET['taxonomy'] ) && self::TAXONOMY === sanitize_key( wp_unslash( $_GET['taxonomy'] ) );
    }

    public function register_elementor_category( $elements_manager ) {
        $elements_manager->add_category(
            'directorist-shopping-centres',
            [
                'title' => __( 'Directorist Shopping Centres', 'directorist-shopping-centres' ),
                'icon'  => 'fa fa-plug',
            ]
        );
    }

    public function register_elementor_widget( $widgets_manager ) {
        if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
            return;
        }

        require_once plugin_dir_path( __FILE__ ) . 'includes/class-elementor-shopping-centres-widget.php';

        $widget = new Directorist_Shopping_Centres_Elementor_Widget();
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( $widget );
            return;
        }

        if ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( $widget );
        }
    }

    public function render_admin_page() {
        $synced = null;

        if ( isset( $_POST['dsc_sync_listings'] ) && check_admin_referer( 'dsc_sync_listings_action', 'dsc_sync_listings_nonce' ) ) {
            $synced = $this->sync_all_existing_listings();
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Shopping Centre Tools', 'directorist-shopping-centres' ); ?></h1>

            <?php if ( null !== $synced ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php printf( esc_html__( 'Synced %d Directorist listings with shopping centre terms.', 'directorist-shopping-centres' ), absint( $synced ) ); ?></p>
                </div>
            <?php endif; ?>

            <p><?php esc_html_e( 'Use this extension to create shopping centres, assign Directorist listings to them, and show dynamic centre tiles on any page.', 'directorist-shopping-centres' ); ?></p>

            <h2><?php esc_html_e( 'Shortcodes', 'directorist-shopping-centres' ); ?></h2>
            <p><code>[directorist_shopping_centres]</code> <?php esc_html_e( 'shows the homepage shopping centre tiles.', 'directorist-shopping-centres' ); ?></p>
            <p><code>[directorist_shopping_centre_deals centre="westfield-bondi-junction"]</code> <?php esc_html_e( 'shows deals for one centre inside a page.', 'directorist-shopping-centres' ); ?></p>

            <h2><?php esc_html_e( 'Elementor Widget', 'directorist-shopping-centres' ); ?></h2>
            <p><?php esc_html_e( 'In Elementor, search for "Shopping Centres". Use the Content tab for title, columns, limit, and empty-centre behavior. Use the Style tab for heading, grid, card, image, centre name, and deal count styling.', 'directorist-shopping-centres' ); ?></p>

            <h2><?php esc_html_e( 'Manage Centres', 'directorist-shopping-centres' ); ?></h2>
            <p>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . self::TAXONOMY . '&post_type=at_biz_dir' ) ); ?>">
                    <?php esc_html_e( 'Open Shopping Centres', 'directorist-shopping-centres' ); ?>
                </a>
            </p>

            <h2><?php esc_html_e( 'Sync Existing Listings', 'directorist-shopping-centres' ); ?></h2>
            <p><?php esc_html_e( 'If older listings already use the Directorist custom field "Shopping Centre / Venue", this will create matching shopping centre terms and assign those listings.', 'directorist-shopping-centres' ); ?></p>
            <form method="post">
                <?php wp_nonce_field( 'dsc_sync_listings_action', 'dsc_sync_listings_nonce' ); ?>
                <button type="submit" class="button" name="dsc_sync_listings" value="1">
                    <?php esc_html_e( 'Sync Existing Listings', 'directorist-shopping-centres' ); ?>
                </button>
            </form>
        </div>
        <?php
    }

    public function enqueue_admin_assets( $hook_suffix ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || self::TAXONOMY !== $screen->taxonomy ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'directorist-shopping-centres-admin',
            plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
            [ 'jquery' ],
            self::VERSION,
            true
        );
    }

    public function add_term_image_field() {
        ?>
        <div class="form-field term-dsc-image-wrap">
            <label for="dsc-image-id"><?php esc_html_e( 'Shopping Centre Image', 'directorist-shopping-centres' ); ?></label>
            <input type="hidden" id="dsc-image-id" name="dsc_image_id" value="">
            <div class="dsc-term-image-preview"></div>
            <button type="button" class="button dsc-select-image"><?php esc_html_e( 'Select Image', 'directorist-shopping-centres' ); ?></button>
            <button type="button" class="button dsc-remove-image"><?php esc_html_e( 'Remove', 'directorist-shopping-centres' ); ?></button>
        </div>
        <?php
    }

    public function edit_term_image_field( $term ) {
        $image_id  = absint( get_term_meta( $term->term_id, self::TERM_IMAGE_META, true ) );
        $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
        ?>
        <tr class="form-field term-dsc-image-wrap">
            <th scope="row"><label for="dsc-image-id"><?php esc_html_e( 'Shopping Centre Image', 'directorist-shopping-centres' ); ?></label></th>
            <td>
                <input type="hidden" id="dsc-image-id" name="dsc_image_id" value="<?php echo esc_attr( $image_id ); ?>">
                <div class="dsc-term-image-preview">
                    <?php if ( $image_url ) : ?>
                        <img src="<?php echo esc_url( $image_url ); ?>" alt="" style="max-width:160px;height:auto;">
                    <?php endif; ?>
                </div>
                <button type="button" class="button dsc-select-image"><?php esc_html_e( 'Select Image', 'directorist-shopping-centres' ); ?></button>
                <button type="button" class="button dsc-remove-image"><?php esc_html_e( 'Remove', 'directorist-shopping-centres' ); ?></button>
            </td>
        </tr>
        <?php
    }

    public function save_term_image( $term_id ) {
        if ( isset( $_POST['dsc_image_id'] ) ) {
            update_term_meta( $term_id, self::TERM_IMAGE_META, absint( $_POST['dsc_image_id'] ) );
        }
    }

    public function register_frontend_assets() {
        wp_register_style(
            'directorist-shopping-centres',
            plugin_dir_url( __FILE__ ) . 'assets/css/frontend.css',
            [],
            self::VERSION
        );
    }

    public function maybe_enqueue_frontend_assets() {
        if ( is_tax( self::TAXONOMY ) || $this->current_post_has_shortcode() ) {
            $this->enqueue_frontend_assets();
        }
    }

    private function current_post_has_shortcode() {
        if ( ! is_singular() ) {
            return false;
        }

        $post = get_post();
        if ( ! $post instanceof WP_Post ) {
            return false;
        }

        if (
            has_shortcode( $post->post_content, 'directorist_shopping_centres' )
            || has_shortcode( $post->post_content, 'directorist_shopping_centre_deals' )
        ) {
            return true;
        }

        $elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
        if ( ! is_string( $elementor_data ) || '' === $elementor_data ) {
            return false;
        }

        return false !== strpos( $elementor_data, 'directorist-shopping-centres' )
            || false !== strpos( $elementor_data, 'directorist_shopping_centres' )
            || false !== strpos( $elementor_data, 'directorist_shopping_centre_deals' );
    }

    private function enqueue_frontend_assets() {
        if ( ! wp_style_is( 'directorist-shopping-centres', 'registered' ) ) {
            $this->register_frontend_assets();
        }

        wp_enqueue_style( 'directorist-shopping-centres' );
    }

    public function shopping_centres_shortcode( $atts ) {
        $this->enqueue_frontend_assets();

        $atts = shortcode_atts(
            [
                'title'      => __( 'Shop deals by shopping centre', 'directorist-shopping-centres' ),
                'columns'    => 3,
                'hide_empty' => 0,
                'number'     => 0,
            ],
            $atts,
            'directorist_shopping_centres'
        );

        $terms = get_terms(
            [
                'taxonomy'   => self::TAXONOMY,
                'hide_empty' => (bool) absint( $atts['hide_empty'] ),
                'number'     => absint( $atts['number'] ),
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return '<div class="dsc-empty">' . esc_html__( 'No shopping centres found yet.', 'directorist-shopping-centres' ) . '</div>';
        }

        ob_start();
        ?>
        <section class="dsc-centres" style="--dsc-columns: <?php echo esc_attr( max( 1, absint( $atts['columns'] ) ) ); ?>">
            <?php if ( '' !== $atts['title'] ) : ?>
                <header class="dsc-section-header">
                    <h2><?php echo esc_html( $atts['title'] ); ?></h2>
                </header>
            <?php endif; ?>

            <div class="dsc-centres__grid">
                <?php foreach ( $terms as $term ) : ?>
                    <?php echo $this->render_centre_tile( $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    public function centre_deals_shortcode( $atts ) {
        $this->enqueue_frontend_assets();

        $atts = shortcode_atts(
            [
                'centre' => '',
                'title'  => '',
            ],
            $atts,
            'directorist_shopping_centre_deals'
        );

        $term = $this->get_term_from_shortcode_value( $atts['centre'] );
        if ( ! $term ) {
            return '<div class="dsc-empty">' . esc_html__( 'Shopping centre not found.', 'directorist-shopping-centres' ) . '</div>';
        }

        return $this->render_term_page( $term, $atts['title'] );
    }

    private function get_term_from_shortcode_value( $value ) {
        $value = sanitize_text_field( $value );
        if ( '' === $value ) {
            return null;
        }

        $term = get_term_by( 'slug', sanitize_title( $value ), self::TAXONOMY );
        if ( ! $term ) {
            $term = get_term_by( 'name', $value, self::TAXONOMY );
        }

        return $term instanceof WP_Term ? $term : null;
    }

    private function render_centre_tile( WP_Term $term ) {
        $link       = get_term_link( $term );
        $image_url  = $this->get_term_image_url( $term, 'large' );
        $deal_count = $this->get_deal_count_for_term( $term );

        if ( is_wp_error( $link ) ) {
            return '';
        }

        ob_start();
        ?>
        <a class="dsc-centre-card" href="<?php echo esc_url( $link ); ?>">
            <span class="dsc-centre-card__image" <?php echo $image_url ? 'style="background-image:url(' . esc_url( $image_url ) . ')"' : ''; ?>></span>
            <span class="dsc-centre-card__body">
                <span class="dsc-centre-card__name"><?php echo esc_html( $term->name ); ?></span>
                <span class="dsc-centre-card__count">
                    <?php
                    printf(
                        esc_html( _n( '%d current deal', '%d current deals', $deal_count, 'directorist-shopping-centres' ) ),
                        absint( $deal_count )
                    );
                    ?>
                </span>
            </span>
        </a>
        <?php
        return ob_get_clean();
    }

    public function template_include( $template ) {
        if ( is_tax( self::TAXONOMY ) ) {
            $plugin_template = plugin_dir_path( __FILE__ ) . 'templates/taxonomy-shopping-centre.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }

        return $template;
    }

    public function body_class( $classes ) {
        if ( is_tax( self::TAXONOMY ) ) {
            $classes[] = 'directorist-shopping-centre-page';
        }

        return $classes;
    }

    public function render_taxonomy_template() {
        $term = get_queried_object();
        if ( ! $term instanceof WP_Term ) {
            return;
        }

        echo $this->render_term_page( $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function render_term_page( WP_Term $term, $custom_title = '' ) {
        $this->enqueue_frontend_assets();

        $deals     = $this->get_deals_for_term( $term );
        $image_url = $this->get_term_image_url( $term, 'full' );
        $title     = $custom_title ? $custom_title : sprintf( __( '%s Deals', 'directorist-shopping-centres' ), $term->name );

        ob_start();
        ?>
        <section class="dsc-centre-page">
            <header class="dsc-centre-hero">
                <?php if ( $image_url ) : ?>
                    <div class="dsc-centre-hero__image" style="background-image:url(<?php echo esc_url( $image_url ); ?>)"></div>
                <?php endif; ?>
                <div class="dsc-centre-hero__content">
                    <p class="dsc-eyebrow"><?php esc_html_e( 'Shopping Centre', 'directorist-shopping-centres' ); ?></p>
                    <h1><?php echo esc_html( $title ); ?></h1>
                    <?php if ( $term->description ) : ?>
                        <p><?php echo esc_html( $term->description ); ?></p>
                    <?php endif; ?>
                </div>
            </header>

            <div class="dsc-deals">
                <div class="dsc-deals__header">
                    <h2><?php esc_html_e( 'Current in-store deals, A to Z', 'directorist-shopping-centres' ); ?></h2>
                    <span>
                        <?php
                        printf(
                            esc_html( _n( '%d participating store', '%d participating stores', count( $deals ), 'directorist-shopping-centres' ) ),
                            absint( count( $deals ) )
                        );
                        ?>
                    </span>
                </div>

                <?php if ( empty( $deals ) ) : ?>
                    <div class="dsc-empty"><?php esc_html_e( 'No current deals found for this shopping centre.', 'directorist-shopping-centres' ); ?></div>
                <?php else : ?>
                    <div class="dsc-deal-list">
                        <?php foreach ( $deals as $listing_id ) : ?>
                            <?php echo $this->render_deal_row( $listing_id, $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private function render_deal_row( $listing_id, WP_Term $term ) {
        $store_name = $this->get_listing_store_name( $listing_id );
        $offer      = $this->get_listing_offer_text( $listing_id );
        $image_url  = $this->get_listing_image_url( $listing_id );
        $permalink  = get_permalink( $listing_id );

        ob_start();
        ?>
        <a class="dsc-deal-row" href="<?php echo esc_url( $permalink ); ?>">
            <span class="dsc-deal-row__image" <?php echo $image_url ? 'style="background-image:url(' . esc_url( $image_url ) . ')"' : ''; ?>></span>
            <span class="dsc-deal-row__content">
                <span class="dsc-deal-row__store"><?php echo esc_html( $store_name ); ?></span>
                <span class="dsc-deal-row__offer"><?php echo esc_html( $offer ); ?></span>
                <span class="dsc-deal-row__centre"><?php echo esc_html( $term->name ); ?></span>
            </span>
            <span class="dsc-deal-row__action"><?php esc_html_e( 'View deal', 'directorist-shopping-centres' ); ?></span>
        </a>
        <?php
        return ob_get_clean();
    }

    private function get_deal_count_for_term( WP_Term $term ) {
        return count( $this->get_deals_for_term( $term ) );
    }

    private function get_deals_for_term( WP_Term $term ) {
        $post_type = defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir';
        $ids       = [];

        $tax_query = new WP_Query(
            [
                'post_type'              => $post_type,
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'tax_query'              => [
                    [
                        'taxonomy' => self::TAXONOMY,
                        'field'    => 'term_id',
                        'terms'    => [ $term->term_id ],
                    ],
                ],
            ]
        );

        $ids = array_merge( $ids, $tax_query->posts );

        $meta_query = [ 'relation' => 'OR' ];
        foreach ( $this->centre_meta_keys() as $meta_key ) {
            $meta_query[] = [
                'key'     => $meta_key,
                'value'   => $term->name,
                'compare' => '=',
            ];
        }

        $meta_query_result = new WP_Query(
            [
                'post_type'              => $post_type,
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => $meta_query,
            ]
        );

        $ids = array_values( array_unique( array_map( 'absint', array_merge( $ids, $meta_query_result->posts ) ) ) );

        usort(
            $ids,
            function ( $a, $b ) {
                return strcasecmp( $this->get_listing_store_name( $a ), $this->get_listing_store_name( $b ) );
            }
        );

        return apply_filters( 'directorist_shopping_centres_deal_ids', $ids, $term );
    }

    private function get_listing_store_name( $listing_id ) {
        $store_name = $this->first_post_meta_value(
            $listing_id,
            [
                '_store_name',
                'store_name',
                '_business_name',
                'business_name',
            ]
        );

        return $store_name ? $store_name : get_the_title( $listing_id );
    }

    private function get_listing_offer_text( $listing_id ) {
        $offer = $this->first_post_meta_value(
            $listing_id,
            [
                '_deal_subtitle',
                'deal_subtitle',
                '_short_summary',
                'short_summary',
            ]
        );

        if ( $offer ) {
            return $offer;
        }

        $excerpt = get_the_excerpt( $listing_id );
        return $excerpt ? wp_strip_all_tags( $excerpt ) : get_the_title( $listing_id );
    }

    private function get_listing_image_url( $listing_id ) {
        if ( function_exists( 'directorist_get_listing_preview_image' ) ) {
            $preview_id = directorist_get_listing_preview_image( $listing_id );
            if ( $preview_id ) {
                $preview_url = wp_get_attachment_image_url( $preview_id, 'medium_large' );
                if ( $preview_url ) {
                    return $preview_url;
                }
            }
        }

        if ( has_post_thumbnail( $listing_id ) ) {
            return get_the_post_thumbnail_url( $listing_id, 'medium_large' );
        }

        if ( function_exists( 'directorist_get_listing_gallery_images' ) ) {
            $gallery = directorist_get_listing_gallery_images( $listing_id );
            if ( ! empty( $gallery[0] ) ) {
                return wp_get_attachment_image_url( absint( $gallery[0] ), 'medium_large' );
            }
        }

        return '';
    }

    private function get_term_image_url( WP_Term $term, $size = 'large' ) {
        $image_id = absint( get_term_meta( $term->term_id, self::TERM_IMAGE_META, true ) );
        return $image_id ? wp_get_attachment_image_url( $image_id, $size ) : '';
    }

    public function sync_listing_from_directorist_meta( $listing_id ) {
        if ( $this->syncing_listing || wp_is_post_revision( $listing_id ) || wp_is_post_autosave( $listing_id ) ) {
            return;
        }

        $post_type = defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir';
        if ( get_post_type( $listing_id ) !== $post_type ) {
            return;
        }

        $requested_term = $this->shopping_centre_term_from_request();
        if ( $requested_term instanceof WP_Term ) {
            $this->syncing_listing = true;
            wp_set_object_terms( $listing_id, [ $requested_term->term_id ], self::TAXONOMY, false );
            $this->update_listing_centre_meta( $listing_id, $requested_term->name );
            $this->syncing_listing = false;
            return;
        }

        if ( $this->request_cleared_shopping_centre_terms() ) {
            $this->delete_listing_centre_meta( $listing_id );
            return;
        }

        $centre_name = $this->first_post_meta_value( $listing_id, $this->centre_meta_keys() );
        if ( ! $centre_name ) {
            return;
        }

        $term = term_exists( $centre_name, self::TAXONOMY );
        if ( ! $term ) {
            $term = wp_insert_term( $centre_name, self::TAXONOMY );
        }

        if ( is_wp_error( $term ) || empty( $term['term_id'] ) ) {
            return;
        }

        $this->syncing_listing = true;
        wp_set_object_terms( $listing_id, [ absint( $term['term_id'] ) ], self::TAXONOMY, false );
        $this->syncing_listing = false;
    }

    public function sync_all_existing_listings() {
        $post_type = defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir';
        $query     = new WP_Query(
            [
                'post_type'      => $post_type,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]
        );

        $synced = 0;
        foreach ( $query->posts as $listing_id ) {
            $before = wp_get_object_terms( $listing_id, self::TAXONOMY, [ 'fields' => 'ids' ] );
            $this->sync_listing_from_directorist_meta( $listing_id );
            $after = wp_get_object_terms( $listing_id, self::TAXONOMY, [ 'fields' => 'ids' ] );

            if ( $before !== $after ) {
                $synced++;
            }
        }

        return $synced;
    }

    private function shopping_centre_term_from_request() {
        if ( ! is_admin() || ! isset( $_POST['tax_input'] ) || ! is_array( $_POST['tax_input'] ) || ! array_key_exists( self::TAXONOMY, $_POST['tax_input'] ) ) {
            return null;
        }

        $raw_values = $this->normalize_requested_taxonomy_values( wp_unslash( $_POST['tax_input'][ self::TAXONOMY ] ) );
        foreach ( $raw_values as $raw_value ) {
            $term = $this->get_shopping_centre_term_from_value( $raw_value );
            if ( $term instanceof WP_Term ) {
                return $term;
            }
        }

        return null;
    }

    private function request_cleared_shopping_centre_terms() {
        if ( ! is_admin() || ! isset( $_POST['tax_input'] ) || ! is_array( $_POST['tax_input'] ) || ! array_key_exists( self::TAXONOMY, $_POST['tax_input'] ) ) {
            return false;
        }

        return [] === $this->normalize_requested_taxonomy_values( wp_unslash( $_POST['tax_input'][ self::TAXONOMY ] ) );
    }

    private function normalize_requested_taxonomy_values( $value ) {
        if ( is_array( $value ) ) {
            $values = $value;
        } else {
            $values = explode( ',', (string) $value );
        }

        $values = array_map(
            static function ( $item ) {
                return trim( (string) $item );
            },
            $values
        );

        return array_values( array_filter( $values, static function ( $item ) {
            return '' !== $item;
        } ) );
    }

    private function get_shopping_centre_term_from_value( $value ) {
        if ( is_numeric( $value ) ) {
            $term = get_term( absint( $value ), self::TAXONOMY );
            if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
                return $term;
            }
        }

        $term = get_term_by( 'name', $value, self::TAXONOMY );
        if ( $term instanceof WP_Term ) {
            return $term;
        }

        $term = get_term_by( 'slug', sanitize_title( $value ), self::TAXONOMY );
        return $term instanceof WP_Term ? $term : null;
    }

    private function update_listing_centre_meta( $listing_id, $centre_name ) {
        foreach ( $this->centre_meta_keys() as $key ) {
            update_post_meta( $listing_id, $key, $centre_name );
        }
    }

    private function delete_listing_centre_meta( $listing_id ) {
        foreach ( $this->centre_meta_keys() as $key ) {
            delete_post_meta( $listing_id, $key );
        }
    }

    private function centre_meta_keys() {
        return apply_filters(
            'directorist_shopping_centres_meta_keys',
            [
                '_shopping_centre_venue',
                'shopping_centre_venue',
                '_shopping_centre_name',
                'shopping_centre_name',
            ]
        );
    }

    private function first_post_meta_value( $post_id, array $keys ) {
        foreach ( $keys as $key ) {
            $value = get_post_meta( $post_id, $key, true );
            if ( is_array( $value ) ) {
                $value = reset( $value );
            }

            $value = is_scalar( $value ) ? trim( (string) $value ) : '';
            if ( '' !== $value ) {
                return $value;
            }
        }

        return '';
    }
}

register_activation_hook( __FILE__, [ 'Directorist_Shopping_Centres', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Directorist_Shopping_Centres', 'deactivate' ] );

Directorist_Shopping_Centres::instance();
