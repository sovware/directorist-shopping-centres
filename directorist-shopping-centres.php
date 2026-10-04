<?php
/**
 * Plugin Name: Directorist Shopping Centres
 * Description: Shopping centre discovery, grouped deals, search, and an admin-friendly listing workflow for Directorist.
 * Version: 1.3.2
 * Author: InStoreOnly
 * Text Domain: directorist-shopping-centres
 * Requires Plugins: directorist
 */

defined( 'ABSPATH' ) || exit;

final class Directorist_Shopping_Centres {
    const VERSION                 = '1.3.2';
    const REMEDIATION_VERSION     = '1.2.2';
    const TAXONOMY                = 'at_biz_dir-shopping-centre';
    const LEGACY_TAXONOMY         = 'at_biz_dir-tags';
    const LEGACY_ROOT_SLUG        = 'shopping-centre-venue';
    const TERM_IMAGE_META         = '_dsc_image_id';
    const TERM_ADDRESS_META       = '_dsc_address_line';
    const TERM_SUBURB_META        = '_dsc_suburb';
    const TERM_STATE_META         = '_dsc_state';
    const TERM_POSTCODE_META      = '_dsc_postcode';
    const TERM_LEGACY_TAG_META    = '_dsc_legacy_tag_id';
    const TERM_PUBLIC_META        = '_dsc_public_visibility';
    const ARCHIVE_SETTINGS_OPTION = 'dsc_shopping_centres_archive_settings';

    const META_IN_CENTRE       = '_dsc_in_shopping_centre';
    const META_CENTRE_ID       = '_dsc_shopping_centre_id';
    const META_SHOP_NUMBER     = '_dsc_shop_number';
    const META_LEVEL_PRECINCT  = '_dsc_level_precinct';
    const META_CENTRE_ADDRESS  = '_dsc_canonical_centre_address';

    private static $instance = null;
    private $syncing_listing = false;
    private $deal_count_cache = [];

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate() {
        self::register_taxonomy_static();
        // A fresh activation must not run the staging remediation routine on
        // the next admin request. Legacy centre import remains an explicit,
        // reviewable action in Shopping Centre Tools.
        update_option( 'dsc_remediation_version', self::REMEDIATION_VERSION );
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    private function __construct() {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
        add_action( 'admin_init', [ $this, 'maybe_run_remediation_migration' ], 30 );
        add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_filter( 'parent_file', [ $this, 'set_active_admin_parent_file' ] );
        add_filter( 'submenu_file', [ $this, 'set_active_admin_submenu_file' ] );

        add_action( self::TAXONOMY . '_add_form_fields', [ $this, 'add_term_fields' ] );
        add_action( self::TAXONOMY . '_edit_form_fields', [ $this, 'edit_term_fields' ] );
        add_action( 'created_' . self::TAXONOMY, [ $this, 'save_term_fields' ] );
        add_action( 'edited_' . self::TAXONOMY, [ $this, 'save_term_fields' ] );
        add_filter( 'manage_edit-' . self::TAXONOMY . '_columns', [ $this, 'add_visibility_column' ] );
        add_filter( 'manage_' . self::TAXONOMY . '_custom_column', [ $this, 'render_visibility_column' ], 10, 3 );

        add_action( 'save_post_at_biz_dir', [ $this, 'sync_listing_from_directorist_meta' ], 99 );
        add_action( 'atbdp_listing_inserted', [ $this, 'sync_listing_from_directorist_meta' ], 30 );
        add_action( 'atbdp_listing_updated', [ $this, 'sync_listing_from_directorist_meta' ], 30 );

        add_action( 'wp_enqueue_scripts', [ $this, 'register_frontend_assets' ], 5 );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_frontend_assets' ] );
        add_shortcode( 'directorist_shopping_centres', [ $this, 'shopping_centres_shortcode' ] );
        add_shortcode( 'directorist_shopping_centres_archive', [ $this, 'shopping_centres_archive_shortcode' ] );
        add_shortcode( 'directorist_shopping_centre_deals', [ $this, 'centre_deals_shortcode' ] );
        add_filter( 'template_include', [ $this, 'template_include' ] );
        add_filter( 'body_class', [ $this, 'body_class' ] );
        add_action( 'template_redirect', [ $this, 'protect_hidden_centre_pages' ], 2 );
        add_action( 'template_redirect', [ $this, 'maybe_redirect_exact_centre_search' ], 3 );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        add_action( 'elementor/elements/categories_registered', [ $this, 'register_elementor_category' ] );
        add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widget' ] );

        add_filter( 'directorist_option', [ $this, 'enforce_public_cleanup_options' ], 20, 2 );
        add_filter( 'get_post_metadata', [ $this, 'use_canonical_centre_address_on_frontend' ], 20, 5 );
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
                    'name'          => __( 'Shopping Centres', 'directorist-shopping-centres' ),
                    'singular_name' => __( 'Shopping Centre', 'directorist-shopping-centres' ),
                    'menu_name'     => __( 'All Shopping Centres', 'directorist-shopping-centres' ),
                    'search_items'  => __( 'Search Shopping Centres', 'directorist-shopping-centres' ),
                    'all_items'     => __( 'All Shopping Centres', 'directorist-shopping-centres' ),
                    'edit_item'     => __( 'Edit Shopping Centre', 'directorist-shopping-centres' ),
                    'update_item'   => __( 'Update Shopping Centre', 'directorist-shopping-centres' ),
                    'add_new_item'  => __( 'Add New Shopping Centre', 'directorist-shopping-centres' ),
                    'new_item_name' => __( 'New Shopping Centre Name', 'directorist-shopping-centres' ),
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
        return $this->is_shopping_centre_admin_screen() ? 'edit.php?post_type=at_biz_dir' : $parent_file;
    }

    public function set_active_admin_submenu_file( $submenu_file ) {
        return $this->is_shopping_centre_admin_screen() ? $this->get_shopping_centres_menu_slug() : $submenu_file;
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
        if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
            return;
        }

        require_once plugin_dir_path( __FILE__ ) . 'includes/class-elementor-shopping-centres-widget.php';
        $widget = new Directorist_Shopping_Centres_Elementor_Widget();

        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( $widget );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( $widget );
        }
    }

    private function get_archive_settings_defaults() {
        return [
            'show_empty'      => 1,
            'per_page'        => 12,
            'search_enabled'  => 1,
            'sort'            => 'name',
            'show_deal_count' => 1,
        ];
    }

    private function sanitize_archive_settings( $settings ) {
        $settings = is_array( $settings ) ? $settings : [];
        $per_page = isset( $settings['per_page'] ) ? absint( $settings['per_page'] ) : 12;
        $sort     = isset( $settings['sort'] ) ? sanitize_key( $settings['sort'] ) : 'name';

        return [
            'show_empty'      => empty( $settings['show_empty'] ) ? 0 : 1,
            'per_page'        => in_array( $per_page, [ 0, 6, 12, 24 ], true ) ? $per_page : 12,
            'search_enabled'  => empty( $settings['search_enabled'] ) ? 0 : 1,
            'sort'            => in_array( $sort, [ 'name', 'deal_count', 'newest' ], true ) ? $sort : 'name',
            'show_deal_count' => empty( $settings['show_deal_count'] ) ? 0 : 1,
        ];
    }

    private function get_archive_settings() {
        $settings = get_option( self::ARCHIVE_SETTINGS_OPTION, [] );
        $settings = wp_parse_args( is_array( $settings ) ? $settings : [], $this->get_archive_settings_defaults() );
        return $this->sanitize_archive_settings( $settings );
    }

    private function should_show_empty_centres() {
        $settings = $this->get_archive_settings();
        return ! empty( $settings['show_empty'] );
    }

    public function render_admin_page() {
        $synced                 = null;
        $migration_report       = null;
        $archive_settings_saved = false;
        if ( isset( $_POST['dsc_sync_listings'] ) && check_admin_referer( 'dsc_sync_listings_action', 'dsc_sync_listings_nonce' ) ) {
            $synced = $this->sync_all_existing_listings();
        }
        if ( isset( $_POST['dsc_migrate_legacy_centres'] ) && check_admin_referer( 'dsc_migrate_legacy_centres_action', 'dsc_migrate_legacy_centres_nonce' ) ) {
            $migration_report = $this->migrate_legacy_shopping_centre_tags();
        }
        if ( isset( $_POST['dsc_save_archive_settings'] ) && check_admin_referer( 'dsc_save_archive_settings_action', 'dsc_save_archive_settings_nonce' ) ) {
            $submitted_settings = isset( $_POST['dsc_archive_settings'] ) ? wp_unslash( $_POST['dsc_archive_settings'] ) : [];
            update_option( self::ARCHIVE_SETTINGS_OPTION, $this->sanitize_archive_settings( $submitted_settings ) );
            $archive_settings_saved = true;
        }

        $missing_units     = $this->get_missing_unit_listings();
        $migration_preview = $this->get_legacy_migration_preview();
        $archive_settings  = $this->get_archive_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Shopping Centre Tools', 'directorist-shopping-centres' ); ?></h1>
            <?php if ( null !== $synced ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php printf( esc_html__( 'Synced %d Directorist listings.', 'directorist-shopping-centres' ), absint( $synced ) ); ?></p></div>
            <?php endif; ?>
            <?php if ( $archive_settings_saved ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Shopping Centre archive settings saved.', 'directorist-shopping-centres' ); ?></p></div>
            <?php endif; ?>
            <?php if ( is_array( $migration_report ) ) : ?>
                <?php $notice_class = empty( $migration_report['errors'] ) ? 'notice-success' : 'notice-warning'; ?>
                <div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible"><p>
                    <?php
                    printf(
                        esc_html__( 'Legacy centre import complete: %1$d created, %2$d existing centres reused, and %3$d listings linked. The original tags were kept.', 'directorist-shopping-centres' ),
                        absint( $migration_report['created'] ),
                        absint( $migration_report['existing'] ),
                        absint( $migration_report['assigned_listings'] )
                    );
                    ?>
                </p>
                <?php if ( ! empty( $migration_report['errors'] ) ) : ?>
                    <p><?php echo esc_html( implode( ' ', $migration_report['errors'] ) ); ?></p>
                <?php endif; ?>
                </div>
            <?php endif; ?>

            <p><?php esc_html_e( 'Manage canonical centre records here. Listings assigned to a centre inherit its discovery address while their existing address metadata remains untouched.', 'directorist-shopping-centres' ); ?></p>

            <h2><?php esc_html_e( 'Administrator quick guide', 'directorist-shopping-centres' ); ?></h2>
            <ul>
                <li><?php esc_html_e( 'Homepage banners: landscape 16:9, minimum 1600×900, preferred 1920×1080. Edit only the Homepage Banner Carousel gallery.', 'directorist-shopping-centres' ); ?></li>
                <li><?php esc_html_e( 'Shopping Centre images: landscape 16:9. Add the centre address before assigning listings.', 'directorist-shopping-centres' ); ?></li>
                <li><?php esc_html_e( 'Deal and Daily Slider images: portrait 1080×1920. Daily Slider requires both the global and listing toggle.', 'directorist-shopping-centres' ); ?></li>
                <li><?php esc_html_e( 'In a listing, choose Yes for Shopping Centre, select the managed centre, then enter the Shop/Unit Number. Level/Precinct is optional.', 'directorist-shopping-centres' ); ?></li>
                <li><?php esc_html_e( 'Use Operating / Open Hours for store hours and place the optional Booking Page URL in the upper business/contact section.', 'directorist-shopping-centres' ); ?></li>
                <li><?php esc_html_e( 'A radio field means one choice only; the blue circle is its selection control.', 'directorist-shopping-centres' ); ?></li>
            </ul>

            <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . self::TAXONOMY . '&post_type=at_biz_dir' ) ); ?>"><?php esc_html_e( 'Manage Shopping Centres', 'directorist-shopping-centres' ); ?></a></p>
            <p><a class="button" href="<?php echo esc_url( home_url( '/shopping-centres/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View Shopping Centres Archive', 'directorist-shopping-centres' ); ?></a></p>

            <h2><?php esc_html_e( 'Archive display settings', 'directorist-shopping-centres' ); ?></h2>
            <p><?php esc_html_e( 'These settings control the complete Shopping Centres archive. The homepage Shopping Centres widget keeps its separate Elementor controls.', 'directorist-shopping-centres' ); ?></p>
            <form method="post">
                <?php wp_nonce_field( 'dsc_save_archive_settings_action', 'dsc_save_archive_settings_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Empty centres', 'directorist-shopping-centres' ); ?></th>
                        <td><label><input type="checkbox" name="dsc_archive_settings[show_empty]" value="1" <?php checked( ! empty( $archive_settings['show_empty'] ) ); ?>> <?php esc_html_e( 'Show publicly visible centres with no current deals', 'directorist-shopping-centres' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dsc-archive-per-page"><?php esc_html_e( 'Centres per page', 'directorist-shopping-centres' ); ?></label></th>
                        <td><select id="dsc-archive-per-page" name="dsc_archive_settings[per_page]">
                            <?php foreach ( [ 6 => '6', 12 => '12', 24 => '24', 0 => __( 'All', 'directorist-shopping-centres' ) ] as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( absint( $archive_settings['per_page'] ), $value ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Archive search', 'directorist-shopping-centres' ); ?></th>
                        <td><label><input type="checkbox" name="dsc_archive_settings[search_enabled]" value="1" <?php checked( ! empty( $archive_settings['search_enabled'] ) ); ?>> <?php esc_html_e( 'Enable search by centre name, suburb, state, or postcode', 'directorist-shopping-centres' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dsc-archive-sort"><?php esc_html_e( 'Sort order', 'directorist-shopping-centres' ); ?></label></th>
                        <td><select id="dsc-archive-sort" name="dsc_archive_settings[sort]">
                            <option value="name" <?php selected( $archive_settings['sort'], 'name' ); ?>><?php esc_html_e( 'Name (A–Z)', 'directorist-shopping-centres' ); ?></option>
                            <option value="deal_count" <?php selected( $archive_settings['sort'], 'deal_count' ); ?>><?php esc_html_e( 'Current deal count (high to low)', 'directorist-shopping-centres' ); ?></option>
                            <option value="newest" <?php selected( $archive_settings['sort'], 'newest' ); ?>><?php esc_html_e( 'Recently added', 'directorist-shopping-centres' ); ?></option>
                        </select></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Deal count', 'directorist-shopping-centres' ); ?></th>
                        <td><label><input type="checkbox" name="dsc_archive_settings[show_deal_count]" value="1" <?php checked( ! empty( $archive_settings['show_deal_count'] ) ); ?>> <?php esc_html_e( 'Show the current-deal count on archive cards', 'directorist-shopping-centres' ); ?></label></td>
                    </tr>
                </table>
                <p class="submit"><button type="submit" class="button button-primary" name="dsc_save_archive_settings" value="1"><?php esc_html_e( 'Save Archive Settings', 'directorist-shopping-centres' ); ?></button></p>
            </form>

            <h2><?php esc_html_e( 'Legacy Shopping Centre import', 'directorist-shopping-centres' ); ?></h2>
            <?php if ( ! empty( $migration_preview['error'] ) ) : ?>
                <p><?php echo esc_html( $migration_preview['error'] ); ?></p>
            <?php else : ?>
                <p><?php esc_html_e( 'Import the old Shopping Centre / Venue tags into the managed Shopping Centre system. Only actual centres nested below the Australian state groups are included; state headings and generic venue types are excluded. Existing centres are reused, and the legacy tags remain untouched.', 'directorist-shopping-centres' ); ?></p>
                <ul>
                    <li><?php printf( esc_html__( 'Eligible legacy centres: %d', 'directorist-shopping-centres' ), absint( $migration_preview['eligible'] ) ); ?></li>
                    <li><?php printf( esc_html__( 'Already represented in the managed system: %d', 'directorist-shopping-centres' ), absint( $migration_preview['existing'] ) ); ?></li>
                    <li><?php printf( esc_html__( 'New managed centres to create: %d', 'directorist-shopping-centres' ), absint( $migration_preview['new'] ) ); ?></li>
                    <li><?php printf( esc_html__( 'Legacy centre tags with a reusable image: %d', 'directorist-shopping-centres' ), absint( $migration_preview['with_images'] ) ); ?></li>
                </ul>
                <form method="post">
                    <?php wp_nonce_field( 'dsc_migrate_legacy_centres_action', 'dsc_migrate_legacy_centres_nonce' ); ?>
                    <button type="submit" class="button button-primary" name="dsc_migrate_legacy_centres" value="1" onclick="return confirm('<?php echo esc_js( __( 'Import the eligible legacy Shopping Centre tags now? Existing centres and legacy tags will not be deleted.', 'directorist-shopping-centres' ) ); ?>');"><?php esc_html_e( 'Import / Sync Legacy Centres', 'directorist-shopping-centres' ); ?></button>
                </form>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Existing listings needing a Shop/Unit Number', 'directorist-shopping-centres' ); ?></h2>
            <?php if ( empty( $missing_units ) ) : ?>
                <p><?php esc_html_e( 'No assigned listings need remediation.', 'directorist-shopping-centres' ); ?></p>
            <?php else : ?>
                <table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Listing', 'directorist-shopping-centres' ); ?></th><th><?php esc_html_e( 'Centre', 'directorist-shopping-centres' ); ?></th><th><?php esc_html_e( 'Action', 'directorist-shopping-centres' ); ?></th></tr></thead><tbody>
                    <?php foreach ( $missing_units as $item ) : ?>
                        <tr><td><?php echo esc_html( get_the_title( $item['listing_id'] ) ); ?></td><td><?php echo esc_html( $item['centre'] ); ?></td><td><a href="<?php echo esc_url( get_edit_post_link( $item['listing_id'] ) ); ?>"><?php esc_html_e( 'Complete listing', 'directorist-shopping-centres' ); ?></a></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Synchronization', 'directorist-shopping-centres' ); ?></h2>
            <p><?php esc_html_e( 'Synchronize legacy Shopping Centre text metadata with managed taxonomy assignments.', 'directorist-shopping-centres' ); ?></p>
            <form method="post">
                <?php wp_nonce_field( 'dsc_sync_listings_action', 'dsc_sync_listings_nonce' ); ?>
                <button type="submit" class="button" name="dsc_sync_listings" value="1"><?php esc_html_e( 'Sync Existing Listings', 'directorist-shopping-centres' ); ?></button>
            </form>
        </div>
        <?php
    }

    public function enqueue_admin_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }

        if ( self::TAXONOMY === $screen->taxonomy ) {
            wp_enqueue_media();
        }

        if ( self::TAXONOMY === $screen->taxonomy || 'at_biz_dir' === $screen->post_type ) {
            wp_enqueue_script(
                'directorist-shopping-centres-admin',
                plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
                [ 'jquery' ],
                self::VERSION,
                true
            );
        }
    }

    public function add_term_fields() {
        wp_nonce_field( 'dsc_save_term', 'dsc_term_nonce' );
        $this->render_term_image_control( false );
        $this->render_add_term_text_field( 'dsc_address_line', __( 'Address line', 'directorist-shopping-centres' ), __( 'Street address used for search, discovery, and directions.', 'directorist-shopping-centres' ) );
        $this->render_add_term_text_field( 'dsc_suburb', __( 'Suburb', 'directorist-shopping-centres' ) );
        $this->render_add_term_text_field( 'dsc_state', __( 'State', 'directorist-shopping-centres' ) );
        $this->render_add_term_text_field( 'dsc_postcode', __( 'Postcode', 'directorist-shopping-centres' ) );
        $this->render_add_visibility_field();
    }

    public function edit_term_fields( $term ) {
        wp_nonce_field( 'dsc_save_term', 'dsc_term_nonce' );
        $this->render_term_image_control( true, $term );
        $this->render_edit_term_text_field( $term, 'dsc_address_line', self::TERM_ADDRESS_META, __( 'Address line', 'directorist-shopping-centres' ), __( 'Street address used for search, discovery, and directions.', 'directorist-shopping-centres' ) );
        $this->render_edit_term_text_field( $term, 'dsc_suburb', self::TERM_SUBURB_META, __( 'Suburb', 'directorist-shopping-centres' ) );
        $this->render_edit_term_text_field( $term, 'dsc_state', self::TERM_STATE_META, __( 'State', 'directorist-shopping-centres' ) );
        $this->render_edit_term_text_field( $term, 'dsc_postcode', self::TERM_POSTCODE_META, __( 'Postcode', 'directorist-shopping-centres' ) );
        $this->render_edit_visibility_field( $term );
    }

    private function render_term_image_control( $editing, $term = null ) {
        $image_id  = $editing && $term ? absint( get_term_meta( $term->term_id, self::TERM_IMAGE_META, true ) ) : 0;
        $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

        if ( $editing ) : ?>
            <tr class="form-field term-dsc-image-wrap"><th scope="row"><label for="dsc-image-id"><?php esc_html_e( 'Shopping Centre Image', 'directorist-shopping-centres' ); ?></label></th><td>
        <?php else : ?>
            <div class="form-field term-dsc-image-wrap"><label for="dsc-image-id"><?php esc_html_e( 'Shopping Centre Image', 'directorist-shopping-centres' ); ?></label>
        <?php endif; ?>
            <input type="hidden" id="dsc-image-id" name="dsc_image_id" value="<?php echo esc_attr( $image_id ); ?>">
            <div class="dsc-term-image-preview"><?php if ( $image_url ) : ?><img src="<?php echo esc_url( $image_url ); ?>" alt="" style="max-width:160px;height:auto;"><?php endif; ?></div>
            <button type="button" class="button dsc-select-image"><?php esc_html_e( 'Select 16:9 Image', 'directorist-shopping-centres' ); ?></button>
            <button type="button" class="button dsc-remove-image"><?php esc_html_e( 'Remove', 'directorist-shopping-centres' ); ?></button>
            <p class="description"><?php esc_html_e( 'Use a dedicated landscape 16:9 image. Portrait deal artwork is not suitable here.', 'directorist-shopping-centres' ); ?></p>
        <?php if ( $editing ) : ?></td></tr><?php else : ?></div><?php endif;
    }

    private function render_add_term_text_field( $name, $label, $description = '' ) {
        ?>
        <div class="form-field"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label><input type="text" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value=""><?php if ( $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?></div>
        <?php
    }

    private function render_edit_term_text_field( $term, $name, $meta_key, $label, $description = '' ) {
        ?>
        <tr class="form-field"><th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input type="text" class="regular-text" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( get_term_meta( $term->term_id, $meta_key, true ) ); ?>"><?php if ( $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?></td></tr>
        <?php
    }

    private function render_add_visibility_field() {
        ?>
        <div class="form-field term-dsc-public-wrap">
            <label for="dsc-public-visibility"><input type="checkbox" id="dsc-public-visibility" name="dsc_public_visibility" value="visible" checked> <?php esc_html_e( 'Show this Shopping Centre publicly', 'directorist-shopping-centres' ); ?></label>
            <p class="description"><?php esc_html_e( 'Uncheck this while preparing a centre or rolling out one state at a time. Hidden centres never appear publicly; empty-centre behavior is controlled in Shopping Centre Tools.', 'directorist-shopping-centres' ); ?></p>
        </div>
        <?php
    }

    private function render_edit_visibility_field( WP_Term $term ) {
        ?>
        <tr class="form-field term-dsc-public-wrap"><th scope="row"><?php esc_html_e( 'Public visibility', 'directorist-shopping-centres' ); ?></th><td>
            <label for="dsc-public-visibility"><input type="checkbox" id="dsc-public-visibility" name="dsc_public_visibility" value="visible" <?php checked( $this->is_centre_public( $term ) ); ?>> <?php esc_html_e( 'Show this Shopping Centre publicly', 'directorist-shopping-centres' ); ?></label>
            <p class="description"><?php esc_html_e( 'Uncheck this while preparing a centre or rolling out one state at a time. The record, image, address and linked listings are preserved.', 'directorist-shopping-centres' ); ?></p>
        </td></tr>
        <?php
    }

    public function save_term_fields( $term_id ) {
        if ( ! isset( $_POST['dsc_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dsc_term_nonce'] ) ), 'dsc_save_term' ) || ! current_user_can( 'manage_categories' ) ) {
            return;
        }

        $map = [
            'dsc_image_id'     => self::TERM_IMAGE_META,
            'dsc_address_line' => self::TERM_ADDRESS_META,
            'dsc_suburb'       => self::TERM_SUBURB_META,
            'dsc_state'        => self::TERM_STATE_META,
            'dsc_postcode'     => self::TERM_POSTCODE_META,
        ];

        foreach ( $map as $request_key => $meta_key ) {
            if ( ! isset( $_POST[ $request_key ] ) ) {
                continue;
            }
            $value = 'dsc_image_id' === $request_key ? absint( $_POST[ $request_key ] ) : sanitize_text_field( wp_unslash( $_POST[ $request_key ] ) );
            update_term_meta( $term_id, $meta_key, $value );
        }

        update_term_meta( $term_id, self::TERM_PUBLIC_META, isset( $_POST['dsc_public_visibility'] ) ? 'visible' : 'hidden' );
    }

    public function add_visibility_column( $columns ) {
        $columns['dsc_public_visibility'] = __( 'Public', 'directorist-shopping-centres' );
        return $columns;
    }

    public function render_visibility_column( $content, $column_name, $term_id ) {
        if ( 'dsc_public_visibility' !== $column_name ) {
            return $content;
        }

        $term = get_term( $term_id, self::TAXONOMY );
        return $term instanceof WP_Term && $this->is_centre_public( $term ) ? esc_html__( 'Visible', 'directorist-shopping-centres' ) : esc_html__( 'Hidden', 'directorist-shopping-centres' );
    }

    public function add_listing_location_metabox() {
        add_meta_box(
            'dsc-listing-location',
            __( 'Store / Location', 'directorist-shopping-centres' ),
            [ $this, 'render_listing_location_metabox' ],
            'at_biz_dir',
            'normal',
            'high'
        );
    }

    public function render_listing_location_metabox( $post ) {
        $terms        = get_terms( [ 'taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'orderby' => 'name' ] );
        $assigned     = wp_get_object_terms( $post->ID, self::TAXONOMY );
        $centre_id    = absint( get_post_meta( $post->ID, self::META_CENTRE_ID, true ) );
        $centre_id    = $centre_id ?: ( ! empty( $assigned[0] ) ? absint( $assigned[0]->term_id ) : 0 );
        $in_centre    = get_post_meta( $post->ID, self::META_IN_CENTRE, true );
        $in_centre    = $in_centre ?: ( $centre_id ? 'yes' : 'no' );
        $shop_number  = get_post_meta( $post->ID, self::META_SHOP_NUMBER, true );
        $level        = get_post_meta( $post->ID, self::META_LEVEL_PRECINCT, true );
        $address      = $centre_id ? $this->get_formatted_address( get_term( $centre_id, self::TAXONOMY ) ) : '';

        wp_nonce_field( 'dsc_save_listing_location', 'dsc_listing_location_nonce' );
        ?>
        <div class="dsc-listing-location" data-dsc-listing-location>
            <p><strong><?php esc_html_e( 'Are you in a Shopping Centre?', 'directorist-shopping-centres' ); ?></strong></p>
            <p class="dsc-radio-row">
                <label><input type="radio" name="dsc_in_shopping_centre" value="yes" <?php checked( $in_centre, 'yes' ); ?>> <?php esc_html_e( 'Yes', 'directorist-shopping-centres' ); ?></label>
                <label><input type="radio" name="dsc_in_shopping_centre" value="no" <?php checked( $in_centre, 'no' ); ?>> <?php esc_html_e( 'No', 'directorist-shopping-centres' ); ?></label>
            </p>
            <div data-dsc-centre-fields>
                <p><label for="dsc-shopping-centre"><strong><?php esc_html_e( 'Managed Shopping Centre', 'directorist-shopping-centres' ); ?></strong></label></p>
                <select id="dsc-shopping-centre" name="dsc_shopping_centre_id" class="widefat" data-dsc-centre-select data-addresses="<?php echo esc_attr( wp_json_encode( $this->term_address_map( $terms ) ) ); ?>">
                    <option value=""><?php esc_html_e( 'Select a Shopping Centre', 'directorist-shopping-centres' ); ?></option>
                    <?php if ( ! is_wp_error( $terms ) ) : foreach ( $terms as $term ) : ?><option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $centre_id, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option><?php endforeach; endif; ?>
                </select>
                <p><label for="dsc-shop-number"><strong><?php esc_html_e( 'Shop / Unit Number', 'directorist-shopping-centres' ); ?></strong></label><input type="text" id="dsc-shop-number" name="dsc_shop_number" class="widefat" value="<?php echo esc_attr( $shop_number ); ?>" data-dsc-required></p>
                <p><label for="dsc-level-precinct"><strong><?php esc_html_e( 'Level / Precinct', 'directorist-shopping-centres' ); ?></strong> <span class="description"><?php esc_html_e( '(optional)', 'directorist-shopping-centres' ); ?></span></label><input type="text" id="dsc-level-precinct" name="dsc_level_precinct" class="widefat" value="<?php echo esc_attr( $level ); ?>"></p>
                <p class="description"><strong><?php esc_html_e( 'Canonical centre address:', 'directorist-shopping-centres' ); ?></strong> <span data-dsc-canonical-address><?php echo esc_html( $address ?: __( 'Add the address to the Shopping Centre record.', 'directorist-shopping-centres' ) ); ?></span></p>
            </div>
            <div data-dsc-standalone-fields>
                <p class="description"><?php esc_html_e( 'Use the listing Address/Location fields above for a standalone business. Those fields remain the discovery address when Shopping Centre is No.', 'directorist-shopping-centres' ); ?></p>
            </div>
        </div>
        <?php
    }

    private function term_address_map( $terms ) {
        $map = [];
        if ( is_wp_error( $terms ) ) {
            return $map;
        }
        foreach ( $terms as $term ) {
            $map[ $term->term_id ] = $this->get_formatted_address( $term );
        }
        return $map;
    }

    public function save_listing_location_metabox( $post_id ) {
        if ( $this->syncing_listing || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['dsc_listing_location_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dsc_listing_location_nonce'] ) ), 'dsc_save_listing_location' ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $in_centre = isset( $_POST['dsc_in_shopping_centre'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['dsc_in_shopping_centre'] ) ) ? 'yes' : 'no';
        update_post_meta( $post_id, self::META_IN_CENTRE, $in_centre );

        if ( 'no' === $in_centre ) {
            delete_post_meta( $post_id, self::META_CENTRE_ID );
            delete_post_meta( $post_id, self::META_SHOP_NUMBER );
            delete_post_meta( $post_id, self::META_LEVEL_PRECINCT );
            delete_post_meta( $post_id, self::META_CENTRE_ADDRESS );
            $this->syncing_listing = true;
            wp_set_object_terms( $post_id, [], self::TAXONOMY, false );
            $this->delete_listing_centre_meta( $post_id );
            $this->syncing_listing = false;
            return;
        }

        $centre_id = isset( $_POST['dsc_shopping_centre_id'] ) ? absint( $_POST['dsc_shopping_centre_id'] ) : 0;
        $term      = $centre_id ? get_term( $centre_id, self::TAXONOMY ) : null;
        if ( ! $term instanceof WP_Term ) {
            return;
        }

        update_post_meta( $post_id, self::META_CENTRE_ID, $centre_id );
        update_post_meta( $post_id, self::META_SHOP_NUMBER, isset( $_POST['dsc_shop_number'] ) ? sanitize_text_field( wp_unslash( $_POST['dsc_shop_number'] ) ) : '' );
        update_post_meta( $post_id, self::META_LEVEL_PRECINCT, isset( $_POST['dsc_level_precinct'] ) ? sanitize_text_field( wp_unslash( $_POST['dsc_level_precinct'] ) ) : '' );
        update_post_meta( $post_id, self::META_CENTRE_ADDRESS, $this->get_formatted_address( $term ) );

        $this->syncing_listing = true;
        wp_set_object_terms( $post_id, [ $centre_id ], self::TAXONOMY, false );
        $this->update_listing_centre_meta( $post_id, $term->name );
        $this->syncing_listing = false;
    }

    public function register_frontend_assets() {
        $css_version = filemtime( plugin_dir_path( __FILE__ ) . 'assets/css/frontend.css' ) ?: self::VERSION;
        $js_version  = filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/frontend.js' ) ?: self::VERSION;
        wp_register_style( 'directorist-shopping-centres', plugin_dir_url( __FILE__ ) . 'assets/css/frontend.css', [], $css_version );
        wp_register_script( 'directorist-shopping-centres', plugin_dir_url( __FILE__ ) . 'assets/js/frontend.js', [], $js_version, true );
    }

    public function maybe_enqueue_frontend_assets() {
        if ( ! is_admin() ) {
            $this->enqueue_frontend_assets();
        }
    }

    private function enqueue_frontend_assets() {
        if ( ! wp_style_is( 'directorist-shopping-centres', 'registered' ) ) {
            $this->register_frontend_assets();
        }
        wp_enqueue_style( 'directorist-shopping-centres' );
        wp_enqueue_script( 'directorist-shopping-centres' );
        wp_localize_script(
            'directorist-shopping-centres',
            'directoristShoppingCentres',
            [
                'restUrl'   => esc_url_raw( rest_url( 'directorist-shopping-centres/v1/search' ) ),
                'minChars'  => 3,
                'groupName' => __( 'Shopping Centres', 'directorist-shopping-centres' ),
                'noResults' => __( 'No Shopping Centres found', 'directorist-shopping-centres' ),
            ]
        );
    }

    public function shopping_centres_shortcode( $atts ) {
        $atts = shortcode_atts(
            [
                'title'      => __( 'Shop deals by shopping centre', 'directorist-shopping-centres' ),
                'hide_empty' => 1,
                'number'     => 0,
            ],
            $atts,
            'directorist_shopping_centres'
        );
        $terms = get_terms( [ 'taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'number' => 0, 'orderby' => 'name', 'order' => 'ASC' ] );
        if ( ! is_wp_error( $terms ) ) {
            $hide_empty = (bool) absint( $atts['hide_empty'] );
            $terms = array_values( array_filter( $terms, function ( $term ) use ( $hide_empty ) {
                return $this->is_centre_public( $term ) && ( ! $hide_empty || $this->get_deal_count_for_term( $term ) > 0 );
            } ) );
        }
        if ( ! is_wp_error( $terms ) && absint( $atts['number'] ) ) {
            $terms = array_slice( $terms, 0, absint( $atts['number'] ) );
        }
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return '<div class="dsc-empty">' . esc_html__( 'No shopping centres found yet.', 'directorist-shopping-centres' ) . '</div>';
        }

        ob_start(); ?>
        <section class="dsc-centres" aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'Shopping Centres', 'directorist-shopping-centres' ); ?>" data-dsc-slider>
            <header class="dsc-section-header">
                <?php if ( '' !== $atts['title'] ) : ?><h2><?php echo esc_html( $atts['title'] ); ?></h2><?php endif; ?>
                <div class="dsc-section-actions"><button type="button" class="dsc-slider-arrow" data-dsc-prev aria-label="<?php esc_attr_e( 'Previous Shopping Centres', 'directorist-shopping-centres' ); ?>">&larr;</button><button type="button" class="dsc-slider-arrow" data-dsc-next aria-label="<?php esc_attr_e( 'Next Shopping Centres', 'directorist-shopping-centres' ); ?>">&rarr;</button></div>
            </header>
            <div class="dsc-centres__viewport" data-dsc-viewport tabindex="0"><div class="dsc-centres__track" data-dsc-track><?php foreach ( $terms as $term ) { echo $this->render_centre_tile( $term ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
            <p class="dsc-view-all"><a href="<?php echo esc_url( home_url( '/shopping-centres/' ) ); ?>"><?php esc_html_e( 'View All Shopping Centres', 'directorist-shopping-centres' ); ?> <span aria-hidden="true">&rarr;</span></a></p>
        </section>
        <?php return ob_get_clean();
    }

    public function shopping_centres_archive_shortcode() {
        $settings    = $this->get_archive_settings();
        $page        = max( 1, get_query_var( 'paged' ), isset( $_GET['centre_page'] ) ? absint( $_GET['centre_page'] ) : 1 );
        $per_page    = absint( $settings['per_page'] );
        $search      = ! empty( $settings['search_enabled'] ) && isset( $_GET['dsc_q'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['dsc_q'] ) ) ) : '';
        $all_terms   = $this->get_public_centres( $search, $settings );
        $total       = is_wp_error( $all_terms ) ? 0 : count( $all_terms );
        $terms       = is_wp_error( $all_terms ) || 0 === $per_page ? $all_terms : array_slice( $all_terms, ( $page - 1 ) * $per_page, $per_page );
        $archive_url = get_permalink();

        ob_start(); ?>
        <section class="dsc-archive">
            <header class="dsc-title-banner"><div><p><?php esc_html_e( 'Discover local deals', 'directorist-shopping-centres' ); ?></p><h1><?php esc_html_e( 'Shopping Centres', 'directorist-shopping-centres' ); ?></h1></div></header>
            <div class="dsc-archive__content">
                <?php if ( ! empty( $settings['search_enabled'] ) ) : ?>
                    <form class="dsc-centre-search" action="<?php echo esc_url( $archive_url ); ?>" method="get" role="search">
                        <label class="screen-reader-text" for="dsc-centre-search-input"><?php esc_html_e( 'Search Shopping Centres', 'directorist-shopping-centres' ); ?></label>
                        <input id="dsc-centre-search-input" type="search" name="dsc_q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by centre name, suburb, state or postcode', 'directorist-shopping-centres' ); ?>">
                        <button type="submit"><?php esc_html_e( 'Search', 'directorist-shopping-centres' ); ?></button>
                        <?php if ( '' !== $search ) : ?><a href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'Clear', 'directorist-shopping-centres' ); ?></a><?php endif; ?>
                    </form>
                <?php endif; ?>
                <?php if ( is_wp_error( $terms ) || empty( $terms ) ) : ?><div class="dsc-empty"><?php echo '' !== $search ? esc_html__( 'No shopping centres match your search.', 'directorist-shopping-centres' ) : ( ! empty( $settings['show_empty'] ) ? esc_html__( 'No shopping centres found yet.', 'directorist-shopping-centres' ) : esc_html__( 'No shopping centres with current deals found yet.', 'directorist-shopping-centres' ) ); ?></div><?php else : ?>
                    <div class="dsc-archive__grid"><?php foreach ( $terms as $term ) { echo $this->render_centre_tile( $term, ! empty( $settings['show_deal_count'] ) ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php if ( $per_page > 0 && $total > $per_page ) : ?><nav class="dsc-pagination" aria-label="<?php esc_attr_e( 'Shopping Centre pages', 'directorist-shopping-centres' ); ?>"><?php echo wp_kses_post( paginate_links( [ 'base' => add_query_arg( 'centre_page', '%#%', $archive_url ), 'format' => '', 'current' => $page, 'total' => (int) ceil( $total / $per_page ), 'add_args' => '' !== $search ? [ 'dsc_q' => $search ] : [] ] ) ); ?></nav><?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
        <?php return ob_get_clean();
    }

    /**
     * Return publicly visible centres using the configured empty-centre,
     * search, and sort behavior.
     */
    private function get_public_centres( $search = '', $settings = null ) {
        $settings = is_array( $settings ) ? $this->sanitize_archive_settings( $settings ) : $this->get_archive_settings();
        $terms = get_terms( [ 'taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'number' => 0, 'orderby' => 'name', 'order' => 'ASC' ] );
        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        $needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $search ) ) : strtolower( trim( (string) $search ) );
        $terms = array_values(
            array_filter(
                $terms,
                function ( $term ) use ( $needle, $settings ) {
                    if ( ! $this->is_centre_public( $term ) || ( empty( $settings['show_empty'] ) && $this->get_deal_count_for_term( $term ) < 1 ) ) {
                        return false;
                    }
                    if ( '' === $needle ) {
                        return true;
                    }
                    $haystack = implode( ' ', [ $term->name, $this->get_formatted_address( $term ) ] );
                    $haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );
                    return false !== strpos( $haystack, $needle );
                }
            )
        );

        if ( 'deal_count' === $settings['sort'] ) {
            usort(
                $terms,
                function ( $a, $b ) {
                    $count_difference = $this->get_deal_count_for_term( $b ) <=> $this->get_deal_count_for_term( $a );
                    return 0 !== $count_difference ? $count_difference : strcasecmp( $a->name, $b->name );
                }
            );
        } elseif ( 'newest' === $settings['sort'] ) {
            usort( $terms, static function ( $a, $b ) { return $b->term_id <=> $a->term_id; } );
        }

        return $terms;
    }

    public function centre_deals_shortcode( $atts ) {
        $atts = shortcode_atts( [ 'centre' => '', 'title' => '' ], $atts, 'directorist_shopping_centre_deals' );
        $term = $this->get_term_from_shortcode_value( $atts['centre'] );
        return $term ? $this->render_term_page( $term, $atts['title'] ) : '<div class="dsc-empty">' . esc_html__( 'Shopping centre not found.', 'directorist-shopping-centres' ) . '</div>';
    }

    private function get_term_from_shortcode_value( $value ) {
        $value = sanitize_text_field( $value );
        $term  = get_term_by( 'slug', sanitize_title( $value ), self::TAXONOMY );
        if ( ! $term ) {
            $term = get_term_by( 'name', $value, self::TAXONOMY );
        }
        return $term instanceof WP_Term && $this->is_centre_public( $term ) && ( $this->should_show_empty_centres() || $this->get_deal_count_for_term( $term ) > 0 ) ? $term : null;
    }

    /**
     * Existing terms remain public until an administrator explicitly hides
     * them, so enabling this control does not remove current data from view.
     */
    private function is_centre_public( WP_Term $term ) {
        return 'hidden' !== get_term_meta( $term->term_id, self::TERM_PUBLIC_META, true );
    }

    public function protect_hidden_centre_pages() {
        if ( ! is_tax( self::TAXONOMY ) ) {
            return;
        }

        $term = get_queried_object();
        if ( $term instanceof WP_Term && $this->is_centre_public( $term ) && ( $this->should_show_empty_centres() || $this->get_deal_count_for_term( $term ) > 0 ) ) {
            return;
        }

        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
    }

    private function render_centre_tile( WP_Term $term, $show_deal_count = true ) {
        $link       = get_term_link( $term );
        $image_url  = $this->get_term_image_url( $term, 'large' );
        $deal_count = $this->get_deal_count_for_term( $term );
        if ( is_wp_error( $link ) ) {
            return '';
        }

        ob_start(); ?>
        <article class="dsc-centre-card" role="group">
            <a href="<?php echo esc_url( $link ); ?>" class="dsc-centre-card__link">
                <span class="dsc-centre-card__image<?php echo $image_url ? '' : ' dsc-centre-card__image--fallback'; ?>"><?php if ( $image_url ) : ?><img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy"><?php else : ?><span aria-hidden="true">ISO</span><?php endif; ?></span>
                <span class="dsc-centre-card__body"><span class="dsc-centre-card__name"><?php echo esc_html( $term->name ); ?></span><?php if ( $show_deal_count ) : ?><span class="dsc-centre-card__count"><?php printf( esc_html( _n( '%d current deal', '%d current deals', $deal_count, 'directorist-shopping-centres' ) ), absint( $deal_count ) ); ?></span><?php endif; ?><?php $address = $this->get_formatted_address( $term ); if ( $address ) : ?><span class="dsc-centre-card__address"><?php echo esc_html( $address ); ?></span><?php endif; ?></span>
            </a>
        </article>
        <?php return ob_get_clean();
    }

    public function template_include( $template ) {
        if ( is_tax( self::TAXONOMY ) ) {
            $plugin_template = plugin_dir_path( __FILE__ ) . 'templates/taxonomy-shopping-centre.php';
            return file_exists( $plugin_template ) ? $plugin_template : $template;
        }
        return $template;
    }

    public function body_class( $classes ) {
        if ( is_tax( self::TAXONOMY ) ) {
            $classes[] = 'directorist-shopping-centre-page';
        }
        if ( is_page( 'shopping-centres' ) ) {
            $classes[] = 'directorist-shopping-centres-archive';
        }
        return $classes;
    }

    public function render_taxonomy_template() {
        $term = get_queried_object();
        if ( $term instanceof WP_Term ) {
            echo $this->render_term_page( $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    public function render_term_page( WP_Term $term, $custom_title = '' ) {
        $deals       = $this->get_deals_for_term( $term );
        $groups      = $this->group_deals_by_store( $deals );
        $image_url   = $this->get_term_image_url( $term, 'full' );
        $title       = $custom_title ?: sprintf( __( '%s Deals', 'directorist-shopping-centres' ), $term->name );
        $address     = $this->get_formatted_address( $term );
        $directions  = $address ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $address ) : '';

        ob_start(); ?>
        <section class="dsc-centre-page">
            <header class="dsc-title-banner dsc-centre-title"><div><p><?php esc_html_e( 'Shopping Centre', 'directorist-shopping-centres' ); ?></p><h1><?php echo esc_html( $title ); ?></h1></div></header>
            <div class="dsc-centre-hero">
                <div class="dsc-centre-hero__image<?php echo $image_url ? '' : ' dsc-centre-card__image--fallback'; ?>"><?php if ( $image_url ) : ?><img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="eager"><?php else : ?><span aria-hidden="true">ISO</span><?php endif; ?></div>
                <div class="dsc-centre-hero__content">
                    <h2><?php echo esc_html( $term->name ); ?></h2>
                    <?php if ( $address ) : ?><p class="dsc-centre-address"><?php echo esc_html( $address ); ?></p><p><a class="dsc-directions" href="<?php echo esc_url( $directions ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get directions', 'directorist-shopping-centres' ); ?> <span aria-hidden="true">&rarr;</span></a></p><?php endif; ?>
                    <?php if ( $term->description ) : ?><p><?php echo esc_html( $term->description ); ?></p><?php endif; ?>
                </div>
            </div>
            <div class="dsc-deals">
                <div class="dsc-deals__header"><h2><?php esc_html_e( 'Current in-store deals, by store', 'directorist-shopping-centres' ); ?></h2><span><?php printf( esc_html( _n( '%d participating store', '%d participating stores', count( $groups ), 'directorist-shopping-centres' ) ), absint( count( $groups ) ) ); ?></span></div>
                <?php if ( empty( $groups ) ) : ?><div class="dsc-empty"><?php esc_html_e( 'No current deals found for this shopping centre.', 'directorist-shopping-centres' ); ?></div><?php else : ?>
                    <div class="dsc-store-groups"><?php foreach ( $groups as $group ) : ?><section class="dsc-store-group"><header><h3><?php echo esc_html( $group['name'] ); ?></h3><span><?php printf( esc_html( _n( '%d deal', '%d deals', count( $group['deals'] ), 'directorist-shopping-centres' ) ), absint( count( $group['deals'] ) ) ); ?></span></header><div class="dsc-deal-list"><?php foreach ( $group['deals'] as $listing_id ) { echo $this->render_deal_row( $listing_id, $term ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></section><?php endforeach; ?></div>
                <?php endif; ?>
            </div>
        </section>
        <?php return ob_get_clean();
    }

    private function group_deals_by_store( array $deal_ids ) {
        $groups = [];
        foreach ( $deal_ids as $listing_id ) {
            $store_id   = absint( $this->first_post_meta_value( $listing_id, [ '_store_listing_id', 'store_listing_id', '_linked_store_listing', 'linked_store_listing', '_deal_store_id', 'deal_store_id' ] ) );
            $store_name = $store_id && 'publish' === get_post_status( $store_id ) ? get_the_title( $store_id ) : $this->get_listing_store_name( $listing_id );
            $key        = $store_id ? 'listing:' . $store_id : 'name:' . sanitize_title( remove_accents( $store_name ) );
            if ( ! isset( $groups[ $key ] ) ) {
                $groups[ $key ] = [ 'name' => $store_name, 'listing_id' => $store_id, 'deals' => [] ];
            }
            $groups[ $key ]['deals'][] = $listing_id;
        }
        uasort( $groups, static function ( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );
        return $groups;
    }

    private function render_deal_row( $listing_id, WP_Term $term ) {
        $offer     = $this->get_listing_offer_text( $listing_id );
        $image_url = $this->get_listing_image_url( $listing_id );
        ob_start(); ?>
        <a class="dsc-deal-row" href="<?php echo esc_url( get_permalink( $listing_id ) ); ?>"><span class="dsc-deal-row__image"><?php if ( $image_url ) : ?><img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy"><?php endif; ?></span><span class="dsc-deal-row__content"><span class="dsc-deal-row__store"><?php echo esc_html( get_the_title( $listing_id ) ); ?></span><span class="dsc-deal-row__offer"><?php echo esc_html( $offer ); ?></span><span class="dsc-deal-row__centre"><?php echo esc_html( $term->name ); ?></span></span><span class="dsc-deal-row__action"><?php esc_html_e( 'View deal', 'directorist-shopping-centres' ); ?></span></a>
        <?php return ob_get_clean();
    }

    private function get_deal_count_for_term( WP_Term $term ) {
        if ( ! array_key_exists( $term->term_id, $this->deal_count_cache ) ) {
            $this->deal_count_cache[ $term->term_id ] = count( $this->get_deals_for_term( $term ) );
        }
        return $this->deal_count_cache[ $term->term_id ];
    }

    private function get_deals_for_term( WP_Term $term ) {
        $post_type = defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir';
        $ids       = get_objects_in_term( $term->term_id, self::TAXONOMY );
        $ids       = is_wp_error( $ids ) ? [] : array_values(
            array_filter(
                array_map( 'absint', $ids ),
                static function ( $listing_id ) use ( $post_type ) {
                    return $post_type === get_post_type( $listing_id ) && 'publish' === get_post_status( $listing_id );
                }
            )
        );

        // Query legacy text assignments directly so REST request parameters and
        // third-party front-end query filters cannot broaden the result set.
        global $wpdb;
        $meta_keys    = $this->centre_meta_keys();
        $placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
        $sql          = "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key IN ({$placeholders}) AND pm.meta_value = %s AND p.post_type = %s AND p.post_status = 'publish'";
        $legacy_ids   = $wpdb->get_col( $wpdb->prepare( $sql, array_merge( $meta_keys, [ $term->name, $post_type ] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $ids          = array_values( array_unique( array_map( 'absint', array_merge( $ids, $legacy_ids ) ) ) );
        usort( $ids, function ( $a, $b ) { return strcasecmp( $this->get_listing_store_name( $a ), $this->get_listing_store_name( $b ) ); } );
        return apply_filters( 'directorist_shopping_centres_deal_ids', $ids, $term );
    }

    private function get_listing_store_name( $listing_id ) {
        $name = $this->first_post_meta_value( $listing_id, [ '_store_name', 'store_name', '_business_name', 'business_name' ] );
        return $name ?: get_the_title( $listing_id );
    }

    private function get_listing_offer_text( $listing_id ) {
        $offer = $this->first_post_meta_value( $listing_id, [ '_deal_subtitle', 'deal_subtitle', '_short_summary', 'short_summary' ] );
        if ( $offer ) {
            return $offer;
        }
        $excerpt = get_the_excerpt( $listing_id );
        return $excerpt ? wp_strip_all_tags( $excerpt ) : get_the_title( $listing_id );
    }

    private function get_listing_image_url( $listing_id ) {
        if ( function_exists( 'directorist_get_listing_preview_image' ) ) {
            $id = directorist_get_listing_preview_image( $listing_id );
            if ( $id ) {
                $url = wp_get_attachment_image_url( $id, 'medium_large' );
                if ( $url ) {
                    return $url;
                }
            }
        }
        return has_post_thumbnail( $listing_id ) ? get_the_post_thumbnail_url( $listing_id, 'medium_large' ) : '';
    }

    private function get_term_image_url( WP_Term $term, $size = 'large' ) {
        $id = absint( get_term_meta( $term->term_id, self::TERM_IMAGE_META, true ) );
        return $id ? wp_get_attachment_image_url( $id, $size ) : '';
    }

    public function get_formatted_address( $term ) {
        if ( ! $term instanceof WP_Term ) {
            return '';
        }
        $parts = [
            get_term_meta( $term->term_id, self::TERM_ADDRESS_META, true ),
            get_term_meta( $term->term_id, self::TERM_SUBURB_META, true ),
            get_term_meta( $term->term_id, self::TERM_STATE_META, true ),
            get_term_meta( $term->term_id, self::TERM_POSTCODE_META, true ),
        ];
        return implode( ', ', array_filter( array_map( 'trim', $parts ) ) );
    }

    public function register_rest_routes() {
        register_rest_route(
            'directorist-shopping-centres/v1',
            '/search',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'rest_search_centres' ],
                'permission_callback' => '__return_true',
                'args'                => [ 'q' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ], 'limit' => [ 'default' => 8, 'sanitize_callback' => 'absint' ] ],
            ]
        );
    }

    public function rest_search_centres( WP_REST_Request $request ) {
        $query = trim( sanitize_text_field( $request->get_param( 'q' ) ) );
        if ( function_exists( 'mb_strlen' ) ? mb_strlen( $query ) < 3 : strlen( $query ) < 3 ) {
            return rest_ensure_response( [] );
        }
        $limit   = min( 12, max( 1, absint( $request->get_param( 'limit' ) ) ) );
        $terms   = $this->get_public_centres( $query );
        $results = [];
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $link = get_term_link( $term );
                $results[] = [ 'id' => $term->term_id, 'name' => $term->name, 'address' => $this->get_formatted_address( $term ), 'url' => is_wp_error( $link ) ? '' : $link, 'image' => $this->get_term_image_url( $term, 'medium' ), 'deal_count' => $this->get_deal_count_for_term( $term ) ];
                if ( count( $results ) >= $limit ) {
                    break;
                }
            }
        }
        return rest_ensure_response( $results );
    }

    public function maybe_redirect_exact_centre_search() {
        if ( is_admin() || empty( $_GET['q'] ) || headers_sent() ) {
            return;
        }
        $query = trim( sanitize_text_field( wp_unslash( $_GET['q'] ) ) );
        if ( '' === $query ) {
            return;
        }
        $term = get_term_by( 'name', $query, self::TAXONOMY );
        if ( ! $term instanceof WP_Term || ! $this->is_centre_public( $term ) || ( ! $this->should_show_empty_centres() && $this->get_deal_count_for_term( $term ) < 1 ) ) {
            return;
        }
        $link = get_term_link( $term );
        if ( ! is_wp_error( $link ) ) {
            wp_safe_redirect( $link );
            exit;
        }
    }

    public function enforce_public_cleanup_options( $value, $name ) {
        if ( 'disable_contact_owner' === $name ) {
            return true;
        }
        return $value;
    }

    public function use_canonical_centre_address_on_frontend( $value, $post_id, $meta_key, $single, $meta_type ) {
        if ( is_admin() || 'post' !== $meta_type || '_address' !== $meta_key || 'at_biz_dir' !== get_post_type( $post_id ) ) {
            return $value;
        }
        if ( 'yes' !== get_post_meta( $post_id, self::META_IN_CENTRE, true ) ) {
            return $value;
        }
        $address = get_post_meta( $post_id, self::META_CENTRE_ADDRESS, true );
        if ( '' === trim( (string) $address ) ) {
            return $value;
        }
        return $single ? $address : [ $address ];
    }

    public function sync_listing_from_directorist_meta( $listing_id ) {
        if ( $this->syncing_listing || wp_is_post_revision( $listing_id ) || wp_is_post_autosave( $listing_id ) || 'at_biz_dir' !== get_post_type( $listing_id ) ) {
            return;
        }
        $terms = wp_get_object_terms( $listing_id, self::TAXONOMY );
        if ( ! is_wp_error( $terms ) && ! empty( $terms[0] ) ) {
            $this->syncing_listing = true;
            update_post_meta( $listing_id, self::META_IN_CENTRE, 'yes' );
            update_post_meta( $listing_id, self::META_CENTRE_ID, $terms[0]->term_id );
            update_post_meta( $listing_id, self::META_CENTRE_ADDRESS, $this->get_formatted_address( $terms[0] ) );
            $this->update_listing_centre_meta( $listing_id, $terms[0]->name );
            $this->syncing_listing = false;
            return;
        }
        $centre_name = $this->first_post_meta_value( $listing_id, $this->centre_meta_keys() );
        if ( ! $centre_name ) {
            return;
        }
        $term = get_term_by( 'name', $centre_name, self::TAXONOMY );
        if ( ! $term ) {
            $created = wp_insert_term( $centre_name, self::TAXONOMY );
            $term    = ! is_wp_error( $created ) ? get_term( $created['term_id'], self::TAXONOMY ) : null;
        }
        if ( $term instanceof WP_Term ) {
            $this->syncing_listing = true;
            wp_set_object_terms( $listing_id, [ $term->term_id ], self::TAXONOMY, false );
            update_post_meta( $listing_id, self::META_IN_CENTRE, 'yes' );
            update_post_meta( $listing_id, self::META_CENTRE_ID, $term->term_id );
            update_post_meta( $listing_id, self::META_CENTRE_ADDRESS, $this->get_formatted_address( $term ) );
            $this->syncing_listing = false;
        }
    }

    public function sync_all_existing_listings() {
        $ids = get_posts( [ 'post_type' => 'at_biz_dir', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ] );
        foreach ( $ids as $id ) {
            $this->sync_listing_from_directorist_meta( $id );
        }
        return count( $ids );
    }

    public function get_legacy_migration_preview() {
        $candidates = $this->get_legacy_centre_candidates();
        if ( is_wp_error( $candidates ) ) {
            return [
                'eligible'    => 0,
                'existing'    => 0,
                'new'         => 0,
                'with_images' => 0,
                'error'       => $candidates->get_error_message(),
            ];
        }

        $preview = [
            'eligible'    => count( $candidates ),
            'existing'    => 0,
            'new'         => 0,
            'with_images' => 0,
            'error'       => '',
        ];

        foreach ( $candidates as $candidate ) {
            $target = $this->find_managed_centre_for_legacy_term( $candidate['term'] );
            $preview[ $target instanceof WP_Term ? 'existing' : 'new' ]++;
            if ( $this->get_legacy_term_image_id( $candidate['term']->term_id ) ) {
                $preview['with_images']++;
            }
        }

        return $preview;
    }

    public function migrate_legacy_shopping_centre_tags() {
        $candidates = $this->get_legacy_centre_candidates();
        $report     = [
            'eligible'            => is_wp_error( $candidates ) ? 0 : count( $candidates ),
            'created'             => 0,
            'existing'            => 0,
            'assigned_listings'   => 0,
            'skipped_assignments' => 0,
            'with_images'         => 0,
            'errors'              => [],
            'created_term_ids'    => [],
        ];

        if ( is_wp_error( $candidates ) ) {
            $report['errors'][] = $candidates->get_error_message();
            return $report;
        }

        $this->create_rollback_snapshot();

        foreach ( $candidates as $candidate ) {
            $legacy_term = $candidate['term'];
            $target      = $this->find_managed_centre_for_legacy_term( $legacy_term );

            if ( ! $target instanceof WP_Term ) {
                $created = wp_insert_term(
                    $legacy_term->name,
                    self::TAXONOMY,
                    [
                        'slug'        => $legacy_term->slug,
                        'description' => $legacy_term->description,
                    ]
                );
                if ( is_wp_error( $created ) ) {
                    $report['errors'][] = sprintf( __( '%1$s: %2$s', 'directorist-shopping-centres' ), $legacy_term->name, $created->get_error_message() );
                    continue;
                }
                $target = get_term( absint( $created['term_id'] ), self::TAXONOMY );
                if ( ! $target instanceof WP_Term ) {
                    $report['errors'][] = sprintf( __( '%s could not be loaded after import.', 'directorist-shopping-centres' ), $legacy_term->name );
                    continue;
                }
                $report['created']++;
                $report['created_term_ids'][] = $target->term_id;
            } else {
                $report['existing']++;
                if ( '' === trim( (string) $target->description ) && '' !== trim( (string) $legacy_term->description ) ) {
                    wp_update_term( $target->term_id, self::TAXONOMY, [ 'description' => $legacy_term->description ] );
                }
            }

            $source_ids = array_map( 'absint', get_term_meta( $target->term_id, self::TERM_LEGACY_TAG_META, false ) );
            if ( ! in_array( $legacy_term->term_id, $source_ids, true ) ) {
                add_term_meta( $target->term_id, self::TERM_LEGACY_TAG_META, $legacy_term->term_id, false );
            }

            if ( '' === trim( (string) get_term_meta( $target->term_id, self::TERM_STATE_META, true ) ) ) {
                update_term_meta( $target->term_id, self::TERM_STATE_META, $candidate['state'] );
            }

            $image_id = $this->get_legacy_term_image_id( $legacy_term->term_id );
            if ( $image_id ) {
                $report['with_images']++;
                if ( ! absint( get_term_meta( $target->term_id, self::TERM_IMAGE_META, true ) ) ) {
                    update_term_meta( $target->term_id, self::TERM_IMAGE_META, $image_id );
                }
            }

            $listing_ids = get_objects_in_term( $legacy_term->term_id, self::LEGACY_TAXONOMY );
            if ( is_wp_error( $listing_ids ) ) {
                $report['errors'][] = sprintf( __( '%1$s listings: %2$s', 'directorist-shopping-centres' ), $legacy_term->name, $listing_ids->get_error_message() );
                continue;
            }

            foreach ( array_map( 'absint', $listing_ids ) as $listing_id ) {
                if ( 'at_biz_dir' !== get_post_type( $listing_id ) ) {
                    continue;
                }
                $assigned = wp_get_object_terms( $listing_id, self::TAXONOMY );
                if ( ! is_wp_error( $assigned ) && ! empty( $assigned ) ) {
                    $report['skipped_assignments']++;
                    continue;
                }

                $result = wp_set_object_terms( $listing_id, [ $target->term_id ], self::TAXONOMY, false );
                if ( is_wp_error( $result ) ) {
                    $report['errors'][] = sprintf( __( 'Listing %1$d: %2$s', 'directorist-shopping-centres' ), $listing_id, $result->get_error_message() );
                    continue;
                }
                update_post_meta( $listing_id, self::META_IN_CENTRE, 'yes' );
                update_post_meta( $listing_id, self::META_CENTRE_ID, $target->term_id );
                update_post_meta( $listing_id, self::META_CENTRE_ADDRESS, $this->get_formatted_address( $target ) );
                $this->update_listing_centre_meta( $listing_id, $target->name );
                $report['assigned_listings']++;
            }
        }

        update_option( 'dsc_legacy_migration_' . gmdate( 'Ymd_His' ), $report, false );

        return $report;
    }

    private function get_legacy_centre_candidates() {
        if ( ! taxonomy_exists( self::LEGACY_TAXONOMY ) ) {
            return new WP_Error( 'dsc_legacy_taxonomy_missing', __( 'The legacy Directorist Tags taxonomy is not available.', 'directorist-shopping-centres' ) );
        }

        $root = get_term_by( 'slug', self::LEGACY_ROOT_SLUG, self::LEGACY_TAXONOMY );
        if ( ! $root instanceof WP_Term ) {
            return new WP_Error( 'dsc_legacy_root_missing', __( 'The legacy Shopping Centre / Venue tag group was not found.', 'directorist-shopping-centres' ) );
        }

        $all_terms = get_terms(
            [
                'taxonomy'   => self::LEGACY_TAXONOMY,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );
        if ( is_wp_error( $all_terms ) ) {
            return $all_terms;
        }

        $terms_by_slug = [];
        foreach ( $all_terms as $legacy_term ) {
            $terms_by_slug[ $legacy_term->slug ] = $legacy_term;
        }

        $state_groups = [
            'nsw-centres' => 'NSW',
            'vic-centres' => 'VIC',
            'qld-centres' => 'QLD',
            'wa-centres'  => 'WA',
            'sa-centres'  => 'SA',
            'tas-centres' => 'TAS',
            'act-centres' => 'ACT',
            'nt-centres'  => 'NT',
        ];
        $state_groups = apply_filters( 'directorist_shopping_centres_legacy_state_groups', $state_groups );
        $candidates   = [];

        foreach ( $state_groups as $group_slug => $state ) {
            $group = isset( $terms_by_slug[ $group_slug ] ) ? $terms_by_slug[ $group_slug ] : null;
            if ( ! $group instanceof WP_Term || absint( $group->parent ) !== absint( $root->term_id ) ) {
                continue;
            }
            foreach ( $all_terms as $term ) {
                if ( absint( $term->parent ) !== absint( $group->term_id ) ) {
                    continue;
                }
                $candidates[] = [
                    'term'  => $term,
                    'state' => sanitize_text_field( $state ),
                ];
            }
        }

        usort(
            $candidates,
            static function ( $left, $right ) {
                return strcasecmp( $left['term']->name, $right['term']->name );
            }
        );

        return $candidates;
    }

    private function find_managed_centre_for_legacy_term( WP_Term $legacy_term ) {
        $aliases = apply_filters(
            'directorist_shopping_centres_legacy_aliases',
            [
                'broadway-shopping-centre' => 'broadway-sydney',
                'westfield-sydney-cbd'      => 'westfield-sydney',
            ]
        );
        $slug    = isset( $aliases[ $legacy_term->slug ] ) ? sanitize_title( $aliases[ $legacy_term->slug ] ) : $legacy_term->slug;
        $target  = get_term_by( 'slug', $slug, self::TAXONOMY );

        if ( ! $target instanceof WP_Term ) {
            $target = get_term_by( 'name', $legacy_term->name, self::TAXONOMY );
        }

        return $target instanceof WP_Term ? $target : null;
    }

    private function get_legacy_term_image_id( $legacy_term_id ) {
        $meta_keys = apply_filters(
            'directorist_shopping_centres_legacy_image_meta_keys',
            [ self::TERM_IMAGE_META, 'image_id', '_image_id', 'attachment_id', '_thumbnail_id' ]
        );

        foreach ( $meta_keys as $meta_key ) {
            $image_id = absint( get_term_meta( $legacy_term_id, $meta_key, true ) );
            if ( $image_id && wp_attachment_is_image( $image_id ) ) {
                return $image_id;
            }
        }

        return 0;
    }

    private function get_missing_unit_listings() {
        $ids     = get_posts( [ 'post_type' => 'at_biz_dir', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => [ 'relation' => 'AND', [ 'key' => self::META_IN_CENTRE, 'value' => 'yes' ], [ 'relation' => 'OR', [ 'key' => self::META_SHOP_NUMBER, 'compare' => 'NOT EXISTS' ], [ 'key' => self::META_SHOP_NUMBER, 'value' => '' ] ] ] ] );
        $results = [];
        foreach ( $ids as $id ) {
            $term      = wp_get_object_terms( $id, self::TAXONOMY );
            $results[] = [ 'listing_id' => $id, 'centre' => ! empty( $term[0] ) ? $term[0]->name : '' ];
        }
        return $results;
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
        return apply_filters( 'directorist_shopping_centres_meta_keys', [ '_shopping_centre_venue', 'shopping_centre_venue', '_shopping_centre_name', 'shopping_centre_name' ] );
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

    public function maybe_run_remediation_migration() {
        if ( ! current_user_can( 'manage_options' ) || self::REMEDIATION_VERSION === get_option( 'dsc_remediation_version' ) ) {
            return;
        }
        $this->create_rollback_snapshot();
        $this->create_archive_page();
        $this->migrate_homepage_carousel();
        $this->normalize_directory_builders();
        $this->sync_all_existing_listings();
        $options = (array) get_option( 'atbdp_option', [] );
        $options['disable_contact_owner'] = true;
        $options['enable_claim_listing']  = true;
        update_option( 'atbdp_option', $options );
        update_option( 'dsc_remediation_version', self::REMEDIATION_VERSION );
        flush_rewrite_rules( false );
        if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }

    private function create_rollback_snapshot() {
        $directories = [];
        foreach ( [ 2076, 2078, 2077, 2092 ] as $id ) {
            $directories[ $id ] = [ 'submission_form_fields' => get_term_meta( $id, 'submission_form_fields', true ), 'single_listings_contents' => get_term_meta( $id, 'single_listings_contents', true ), 'enable_claim_listing' => get_term_meta( $id, 'enable_claim_listing', true ) ];
        }
        $terms = [];
        foreach ( get_terms( [ 'taxonomy' => self::TAXONOMY, 'hide_empty' => false ] ) as $term ) {
            $terms[ $term->term_id ] = get_term_meta( $term->term_id );
        }
        $listings = [];
        foreach ( get_posts( [ 'post_type' => 'at_biz_dir', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => [ [ 'taxonomy' => self::TAXONOMY, 'operator' => 'EXISTS' ] ] ] ) as $id ) {
            $listings[ $id ] = get_post_meta( $id );
        }
        $snapshot = [ 'created_utc' => gmdate( 'c' ), 'homepage_id' => 53, 'homepage_elementor_data' => get_post_meta( 53, '_elementor_data', true ), 'directories' => $directories, 'shopping_centre_terms' => $terms, 'directorist_options' => get_option( 'atbdp_option', [] ), 'assigned_listing_meta' => $listings ];
        update_option( 'dsc_remediation_rollback_' . gmdate( 'Ymd_His' ), $snapshot, false );
    }

    private function create_archive_page() {
        $page = get_page_by_path( 'shopping-centres' );
        if ( $page ) {
            return;
        }
        wp_insert_post( [ 'post_title' => __( 'Shopping Centres', 'directorist-shopping-centres' ), 'post_name' => 'shopping-centres', 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => '[directorist_shopping_centres_archive]' ] );
    }

    private function migrate_homepage_carousel() {
        $json = get_post_meta( 53, '_elementor_data', true );
        if ( ! is_string( $json ) || '' === $json ) {
            return;
        }
        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            return;
        }

        $updated = $this->replace_homepage_section( $data );
        if ( ! $updated ) {
            $updated = $this->repair_homepage_carousel_widget( $data );
        }
        if ( ! $updated ) {
            return;
        }
        update_post_meta( 53, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
        delete_post_meta( 53, '_elementor_css' );
    }

    private function replace_homepage_section( &$elements ) {
        foreach ( $elements as &$element ) {
            if ( isset( $element['id'] ) && '06c8c9c' === $element['id'] ) {
                if ( 'section' !== ( $element['elType'] ?? '' ) ) {
                    return false;
                }
                $settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : [];
                $images   = isset( $settings['background_slideshow_gallery'] ) && is_array( $settings['background_slideshow_gallery'] ) ? $settings['background_slideshow_gallery'] : [];
                if ( empty( $images ) ) {
                    return false;
                }
                $carousel = [];
                foreach ( $images as $image ) {
                    $id  = absint( $image['id'] ?? 0 );
                    $url = $image['url'] ?? ( $id ? wp_get_attachment_image_url( $id, 'full' ) : '' );
                    if ( $id || $url ) {
                        $carousel[] = [ 'id' => $id, 'url' => esc_url_raw( $url ) ];
                    }
                }
                $element = [
                    'id'       => '06c8c9c',
                    'elType'   => 'section',
                    'settings' => [ '_title' => 'Homepage Banner Carousel — edit images only', 'layout' => 'full_width', 'gap' => 'no', 'css_classes' => 'iso-homepage-banner-section' ],
                    'elements' => [ [ 'id' => 'd5c1001', 'elType' => 'column', 'settings' => [ '_column_size' => 100, '_inline_size' => null ], 'elements' => [ [ 'id' => 'd5c1002', 'elType' => 'widget', 'widgetType' => 'image-carousel', 'settings' => [ '_title' => 'Homepage Banner Carousel — 16:9 landscape images, min 1600×900', 'carousel' => $carousel, 'slides_to_show' => '1', 'slides_to_scroll' => '1', 'navigation' => 'both', 'autoplay' => 'yes', 'pause_on_hover' => 'yes', 'pause_on_interaction' => 'yes', 'autoplay_speed' => 5000, 'infinite' => 'yes', 'speed' => 500, 'thumbnail_size' => 'full', 'image_stretch' => 'yes', 'lazyload' => '' ], 'elements' => [] ] ], 'isInner' => false ] ],
                    'isInner'  => false,
                ];
                return true;
            }
            if ( ! empty( $element['elements'] ) && $this->replace_homepage_section( $element['elements'] ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Repair the already-migrated Elementor carousel without replacing its
     * administrator-managed gallery. Elementor's image-size control is named
     * `thumbnail_size`; using `image_size` silently falls back to 150x150.
     * Normal src loading also avoids a blank first paint when Swiper's lazy
     * loader is delayed or unavailable.
     */
    private function repair_homepage_carousel_widget( &$elements ) {
        foreach ( $elements as &$element ) {
            $settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : [];
            $is_homepage_carousel = 'image-carousel' === ( $element['widgetType'] ?? '' )
                && ( 'd5c1002' === ( $element['id'] ?? '' ) || false !== strpos( (string) ( $settings['_title'] ?? '' ), 'Homepage Banner Carousel' ) );

            if ( $is_homepage_carousel ) {
                $element['settings']['thumbnail_size'] = 'full';
                $element['settings']['lazyload']       = '';
                $element['settings']['image_stretch']  = 'yes';
                unset( $element['settings']['image_size'] );
                return true;
            }

            if ( ! empty( $element['elements'] ) && $this->repair_homepage_carousel_widget( $element['elements'] ) ) {
                return true;
            }
        }

        return false;
    }

    private function normalize_directory_builders() {
        foreach ( [ 2076, 2078, 2077, 2092 ] as $directory_id ) {
            $term = get_term( $directory_id );
            if ( ! $term || is_wp_error( $term ) ) {
                continue;
            }
            $form   = get_term_meta( $directory_id, 'submission_form_fields', true );
            $single = get_term_meta( $directory_id, 'single_listings_contents', true );

            if ( is_array( $form ) ) {
                $form_value = &$form;
                if ( isset( $form['value'] ) && is_array( $form['value'] ) ) {
                    $form_value = &$form['value'];
                }
                $is_event = false !== stripos( $term->name, 'event' );
                if ( ! empty( $form_value['fields'] ) && is_array( $form_value['fields'] ) ) {
                    foreach ( $form_value['fields'] as $key => &$field ) {
                        $identity = strtolower( implode( ' ', [ $key, $field['field_key'] ?? '', $field['widget_name'] ?? '', $field['label'] ?? '' ] ) );
                        if ( ! $is_event && ( false !== strpos( $identity, 'business_hours' ) || false !== strpos( $identity, 'business hours' ) ) ) {
                            $field['label'] = __( 'Operating / Open Hours', 'directorist-shopping-centres' );
                        }
                        if ( 'radio' === strtolower( trim( (string) ( $field['label'] ?? '' ) ) ) ) {
                            $field['label']       = __( 'Selection', 'directorist-shopping-centres' );
                            $field['description'] = __( 'Choose one option. The blue circle is the selection control.', 'directorist-shopping-centres' );
                        }
                    }
                    unset( $field );
                }
                $this->move_form_field_to_preferred_group( $form_value, 'booking_button', [ 'business', 'contact', 'store', 'location' ], 0 );
                if ( ! $is_event ) {
                    $this->move_form_field_to_preferred_group( $form_value, 'business_hours', [ 'store', 'location', 'business', 'contact' ], 1 );
                    if ( ! empty( $form_value['groups'] ) && is_array( $form_value['groups'] ) ) {
                        foreach ( $form_value['groups'] as &$group ) {
                            if ( in_array( 'business_hours', $group['fields'] ?? [], true ) ) {
                                $group['label'] = __( 'Store / Location', 'directorist-shopping-centres' );
                                break;
                            }
                        }
                        unset( $group );
                    }
                }
                update_term_meta( $directory_id, 'submission_form_fields', $form );
                unset( $form_value );
            }

            if ( is_array( $single ) ) {
                $single_value = &$single;
                if ( isset( $single['value'] ) && is_array( $single['value'] ) ) {
                    $single_value = &$single['value'];
                }
                $single_value['groups'] = array_values( array_filter( $single_value['groups'] ?? [], static function ( $group ) {
                    $identity = strtolower( implode( ' ', [ $group['id'] ?? '', $group['widget_name'] ?? '', $group['label'] ?? '', $group['custom_block_id'] ?? '' ] ) );
                    return false === strpos( $identity, 'contact listing' ) && false === strpos( $identity, 'contact owner' ) && false === strpos( $identity, 'claim' ) && false === strpos( $identity, 'iso-book-online' );
                } ) );
                $booking_key = '';
                foreach ( $single_value['fields'] ?? [] as $key => $field ) {
                    $identity = strtolower( implode( ' ', [ $key, $field['original_widget_key'] ?? '', $field['widget_name'] ?? '', $field['label'] ?? '' ] ) );
                    if ( false !== strpos( $identity, 'booking_button' ) || false !== strpos( $identity, 'book online' ) ) {
                        $booking_key = $key;
                        break;
                    }
                }
                if ( $booking_key ) {
                    foreach ( $single_value['groups'] as &$group ) {
                        $group['fields'] = array_values( array_diff( $group['fields'] ?? [], [ $booking_key ] ) );
                    }
                    unset( $group );
                    array_splice( $single_value['groups'], min( 1, count( $single_value['groups'] ) ), 0, [ [ 'label' => __( 'Book Online', 'directorist-shopping-centres' ), 'fields' => [ $booking_key ], 'type' => 'general_group', 'icon' => 'las la-calendar-check', 'custom_block_id' => 'iso-book-online', 'custom_block_classes' => 'iso-book-online-section' ] ] );
                }
                update_term_meta( $directory_id, 'single_listings_contents', $single );
                unset( $single_value );
            }
            update_term_meta( $directory_id, 'enable_claim_listing', true );
        }
    }

    private function move_form_field_to_preferred_group( &$form, $field_key, array $labels, $position ) {
        if ( empty( $form['fields'][ $field_key ] ) || empty( $form['groups'] ) ) {
            return;
        }
        foreach ( $form['groups'] as &$group ) {
            $group['fields'] = array_values( array_diff( $group['fields'] ?? [], [ $field_key ] ) );
        }
        unset( $group );
        $target = 0;
        foreach ( $form['groups'] as $index => $group ) {
            $label = strtolower( $group['label'] ?? '' );
            foreach ( $labels as $needle ) {
                if ( false !== strpos( $label, $needle ) ) {
                    $target = $index;
                    break 2;
                }
            }
        }
        $fields = $form['groups'][ $target ]['fields'] ?? [];
        array_splice( $fields, min( $position, count( $fields ) ), 0, [ $field_key ] );
        $form['groups'][ $target ]['fields'] = $fields;
    }
}

register_activation_hook( __FILE__, [ 'Directorist_Shopping_Centres', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Directorist_Shopping_Centres', 'deactivate' ] );
Directorist_Shopping_Centres::instance();
