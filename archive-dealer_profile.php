<?php
/**
 * Dealer profiles archive.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$city_slug = sanitize_title((string) get_query_var('dealer_city'));
$city_options = function_exists('autoagora_dealer_profile_city_options')
    ? autoagora_dealer_profile_city_options()
    : array();
$city_label = $city_options[$city_slug] ?? '';
$archive_title = $city_label !== ''
    ? sprintf(__('Car dealers in %s', 'bricks-child'), $city_label)
    : __('Car dealers in Cyprus', 'bricks-child');
$archive_intro = $city_label !== ''
    ? sprintf(__('Browse dealership profiles in %s with location details, websites, social links, and active AutoAgora listings where available.', 'bricks-child'), $city_label)
    : __('Browse dealership profiles across Cyprus with location details, websites, social links, and active AutoAgora listings where available.', 'bricks-child');
?>

<main class="dealer-profile-archive">
    <section class="dealer-profile-archive__header">
        <div>
            <h1><?php echo esc_html($archive_title); ?></h1>
            <p><?php echo esc_html($archive_intro); ?></p>
        </div>
        <a class="btn btn-primary" href="<?php echo esc_url(home_url('/cars/')); ?>">
            <?php esc_html_e('Browse cars', 'bricks-child'); ?>
        </a>
    </section>

    <?php if (!empty($city_options)) : ?>
        <nav class="dealer-profile-city-nav" aria-label="<?php esc_attr_e('Dealer profile city filters', 'bricks-child'); ?>">
            <a class="<?php echo $city_slug === '' ? 'is-active' : ''; ?>" href="<?php echo esc_url(trailingslashit(home_url('/dealers/'))); ?>">
                <?php esc_html_e('All Cyprus', 'bricks-child'); ?>
            </a>
            <?php foreach ($city_options as $slug => $label) : ?>
                <a class="<?php echo $city_slug === $slug ? 'is-active' : ''; ?>" href="<?php echo esc_url(autoagora_dealer_profile_city_archive_url($slug)); ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <p class="dealer-profile-archive__count">
        <?php
        global $wp_query;
        echo esc_html(
            sprintf(
                /* translators: %d: profile count */
                _n('%d dealer profile found', '%d dealer profiles found', (int) $wp_query->found_posts, 'bricks-child'),
                (int) $wp_query->found_posts
            )
        );
        ?>
    </p>

    <?php if (have_posts()) : ?>
        <div class="dealer-profile-grid">
            <?php
            while (have_posts()) :
                the_post();
                if (function_exists('autoagora_render_dealer_profile_card')) {
                    autoagora_render_dealer_profile_card(get_the_ID());
                }
            endwhile;
            ?>
        </div>

        <div class="dealer-profile-pagination">
            <?php
            echo paginate_links(
                array(
                    'prev_text' => __('Previous', 'bricks-child'),
                    'next_text' => __('Next', 'bricks-child'),
                    'type'      => 'list',
                )
            );
            ?>
        </div>
    <?php else : ?>
        <div class="dealer-profile-empty">
            <h2><?php esc_html_e('No dealer profiles here yet', 'bricks-child'); ?></h2>
            <p><?php esc_html_e('Try another city or browse all dealers across Cyprus.', 'bricks-child'); ?></p>
            <?php if ($city_slug !== '') : ?>
                <a class="btn btn-secondary" href="<?php echo esc_url(trailingslashit(home_url('/dealers/'))); ?>">
                    <?php esc_html_e('View all dealers', 'bricks-child'); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<?php
get_footer();
