<?php
/**
 * Shopping centre taxonomy template.
 *
 * Themes can override the frontend with their own taxonomy template if needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main id="primary" class="site-main dsc-template-wrap">
    <?php Directorist_Shopping_Centres::instance()->render_taxonomy_template(); ?>
</main>
<?php
get_footer();
