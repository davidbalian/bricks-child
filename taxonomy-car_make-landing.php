<?php
/**
 * Managed car_make landing template.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

$landing = autoagora_get_current_car_make_landing();
if (!$landing) {
    status_header(404);
    nocache_headers();
    include get_404_template();
    return;
}

$group = 'car-make-landing-' . sanitize_html_class($landing['slug']);
$listings_id = 'car-make-landing-results';
$filters_shortcode = sprintf(
    '[car_filters filters="make,model,price,mileage,year,fuel,body" mode="ajax" target="%1$s" layout="horizontal" show_button="true" button_text="Search Cars" group="%2$s" landing_make_slug="%3$s" landing_model_slug="%4$s" results_base_url="/cars/"]',
    esc_attr($listings_id),
    esc_attr($group),
    esc_attr($landing['make_slug']),
    esc_attr($landing['model_slug'])
);
$listings_shortcode = sprintf(
    '[car_listings id="%1$s" filter_group="%2$s" posts_per_page="24" orderby="date" order="DESC" card_type="car_card" default_make_slug="%3$s" default_model_slug="%4$s"]',
    esc_attr($listings_id),
    esc_attr($group),
    esc_attr($landing['make_slug']),
    esc_attr($landing['model_slug'])
);

get_header();
?>

<main class="autoagora-car-make-landing-page">
    <div class="bricks-container">
        <div class="bricks-content autoagora-car-make-landing__content">
            <section class="autoagora-car-make-landing__hero">
                <span class="autoagora-car-make-landing__eyebrow">AutoAgora Cyprus</span>
                <h1 class="autoagora-car-make-landing__title"><?php echo esc_html($landing['h1']); ?></h1>
                <p class="autoagora-car-make-landing__subtitle">
                    Browse matching listings first, then refine by price, mileage, year, fuel type, or body style without losing the model-specific context of this page.
                </p>
            </section>

            <section class="autoagora-car-make-landing__browse">
                <div class="autoagora-car-make-landing__filters">
                    <?php echo do_shortcode($filters_shortcode); ?>
                </div>

                <div class="autoagora-car-make-landing__results">
                    <?php echo do_shortcode($listings_shortcode); ?>
                </div>
            </section>

            <section class="autoagora-car-make-landing__copy">
                <div class="autoagora-car-make-landing__section-head">
                    <span class="autoagora-car-make-landing__section-kicker">Why this model</span>
                    <h2>Buying insights for Cyprus shoppers</h2>
                </div>

                <div class="autoagora-car-make-landing__copy-body">
                    <?php foreach ($landing['intro'] as $paragraph) : ?>
                        <p><?php echo esc_html($paragraph); ?></p>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="autoagora-car-make-landing__faq">
                <div class="autoagora-car-make-landing__section-head">
                    <span class="autoagora-car-make-landing__section-kicker">FAQ</span>
                    <h2>Common questions about the <?php echo esc_html($landing['model_name']); ?></h2>
                </div>

                <div class="autoagora-car-make-landing__faq-list">
                    <?php foreach ($landing['faqs'] as $faq) : ?>
                        <details class="autoagora-car-make-landing__faq-item">
                            <summary><?php echo esc_html($faq['question']); ?></summary>
                            <div class="autoagora-car-make-landing__faq-answer">
                                <p><?php echo esc_html($faq['answer']); ?></p>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</main>

<?php
get_footer();
