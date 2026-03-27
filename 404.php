<?php
/**
 * 404 template — search + category shortcuts (no soft-redirect for junk URLs).
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

status_header(404);
nocache_headers();

get_header();

$cars_base    = trailingslashit(home_url('/cars/'));
$suv_url      = esc_url(add_query_arg(array('body_type' => 'SUV'), $cars_base));
$hatch_url    = esc_url(add_query_arg(array('body_type' => 'Hatchback'), $cars_base));
$electric_url = esc_url(add_query_arg(array('fuel_type' => 'Electric'), $cars_base));
?>
<div class="autoagora-404">
    <div class="autoagora-404-inner">
        <h1 class="autoagora-404-title"><?php esc_html_e('Oops! That car has been sold or the link is broken.', 'bricks-child'); ?></h1>
        <p class="autoagora-404-lede"><?php esc_html_e('Let’s find you something else — search below or jump into a popular category.', 'bricks-child'); ?></p>

        <div class="autoagora-404-search">
            <?php echo do_shortcode('[homepage_filters]'); ?>
        </div>

        <p class="autoagora-404-cats-label"><?php esc_html_e('Browse by category', 'bricks-child'); ?></p>
        <div class="autoagora-404-cats">
            <a class="autoagora-404-cat" href="<?php echo $suv_url; ?>"><?php esc_html_e('SUV', 'bricks-child'); ?></a>
            <a class="autoagora-404-cat" href="<?php echo $hatch_url; ?>"><?php esc_html_e('Hatchback', 'bricks-child'); ?></a>
            <a class="autoagora-404-cat" href="<?php echo $electric_url; ?>"><?php esc_html_e('Electric', 'bricks-child'); ?></a>
        </div>
    </div>
</div>
<style>
.autoagora-404 {
    max-width: 720px;
    margin: 0 auto;
    padding: 2.5rem 1.25rem 4rem;
}
.autoagora-404-inner {
    background: #fff;
    border-radius: 12px;
    padding: 2rem 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.autoagora-404-title {
    font-size: 1.5rem;
    line-height: 1.3;
    margin: 0 0 0.75rem;
    color: #2a3546;
    font-weight: 600;
}
.autoagora-404-lede {
    margin: 0 0 1.5rem;
    color: #475569;
    line-height: 1.5;
}
.autoagora-404-search {
    margin-bottom: 1.75rem;
}
.autoagora-404-cats-label {
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin: 0 0 0.75rem;
}
.autoagora-404-cats {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.autoagora-404-cat {
    display: inline-flex;
    align-items: center;
    padding: 0.55rem 1rem;
    border-radius: 999px;
    background: #0d86e3;
    color: #fff !important;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem;
    transition: opacity 0.15s, background 0.15s;
}
.autoagora-404-cat:hover {
    opacity: 0.92;
    color: #fff !important;
}
</style>
<?php
get_footer();
