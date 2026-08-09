<?php
/**
 * Code-owned global header and mobile bottom navigation.
 *
 * The Bricks header remains in the document (hidden with scoped CSS) so existing
 * Bricks before/after-header hooks continue to run during the migration.
 *
 * @package Bricks_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the code-owned header should replace the visible Bricks header.
 */
function autoagora_code_header_is_enabled() {
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() ) {
		return false;
	}

	if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
		return false;
	}

	if ( isset( $_GET['bricks'] ) && 'run' === sanitize_text_field( wp_unslash( $_GET['bricks'] ) ) ) {
		return false;
	}

	/**
	 * Permit an emergency rollback without editing this feature.
	 *
	 * @param bool $enabled Whether the code-owned header is enabled.
	 */
	return (bool) apply_filters( 'autoagora_code_header_enabled', true );
}

/**
 * Add a feature class used to scope the replacement CSS.
 */
function autoagora_code_header_body_class( $classes ) {
	if ( autoagora_code_header_is_enabled() ) {
		$classes[] = 'autoagora-code-header-active';
	}

	return $classes;
}
add_filter( 'body_class', 'autoagora_code_header_body_class' );

/**
 * Register the isolated assets only when the replacement is active.
 */
function autoagora_code_header_enqueue_assets() {
	if ( ! autoagora_code_header_is_enabled() ) {
		return;
	}

	$css_path = get_stylesheet_directory() . '/includes/global-header/global-header.css';
	$js_path  = get_stylesheet_directory() . '/includes/global-header/global-header.js';

	wp_enqueue_style(
		'autoagora-global-header',
		get_stylesheet_directory_uri() . '/includes/global-header/global-header.css',
		array( 'bricks-child-theme-css' ),
		filemtime( $css_path )
	);

	wp_enqueue_script(
		'autoagora-global-header',
		get_stylesheet_directory_uri() . '/includes/global-header/global-header.js',
		array(),
		filemtime( $js_path ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'autoagora_code_header_enqueue_assets', 30 );

/**
 * Main navigation items copied from the active Bricks header.
 */
function autoagora_code_header_menu_items() {
	return array(
		array( 'label' => __( 'Used Cars', 'bricks-child' ), 'slug' => 'cars' ),
		array( 'label' => __( 'Sell my Car', 'bricks-child' ), 'slug' => 'add-listing' ),
		array( 'label' => __( 'Blog', 'bricks-child' ), 'slug' => 'blog' ),
		array( 'label' => __( 'Buyer Requests', 'bricks-child' ), 'slug' => 'buyer-requests' ),
		array( 'label' => __( 'Dealerships', 'bricks-child' ), 'slug' => 'dealerships' ),
		array( 'label' => __( 'Autoportal Blog', 'bricks-child' ), 'slug' => 'autoportal-blog' ),
		array( 'label' => __( 'Contact', 'bricks-child' ), 'slug' => 'contact' ),
		array( 'label' => __( 'Become a Dealer', 'bricks-child' ), 'slug' => 'become-a-dealer' ),
	);
}

/**
 * Render a compact Polylang language switcher.
 *
 * Polylang supplies the translated URL for the current page. When a page has
 * no translation, fall back to that language's homepage rather than outputting
 * an empty or broken link.
 *
 * @param string $modifier_class Optional presentation modifier.
 */
function autoagora_code_header_render_language_switcher( $modifier_class = '' ) {
	if ( ! function_exists( 'pll_the_languages' ) ) {
		return;
	}

	$languages = pll_the_languages(
		array(
			'raw'                    => 1,
			'hide_if_empty'          => 0,
			'hide_if_no_translation' => 0,
			'hide_current'           => 0,
		)
	);

	if ( ! is_array( $languages ) || count( $languages ) < 2 ) {
		return;
	}

	$class_name = 'aag-site-header__language-switcher';
	if ( $modifier_class ) {
		$class_name .= ' ' . $modifier_class;
	}
	?>
	<nav class="<?php echo esc_attr( $class_name ); ?>" aria-label="<?php esc_attr_e( 'Language selector', 'bricks-child' ); ?>">
		<ul>
			<?php foreach ( $languages as $language ) : ?>
				<?php
				$slug = isset( $language['slug'] ) ? sanitize_key( $language['slug'] ) : '';
				$name = isset( $language['name'] ) ? $language['name'] : strtoupper( $slug );
				$url  = isset( $language['url'] ) ? $language['url'] : '';

				if ( ! $url && $slug && function_exists( 'pll_home_url' ) ) {
					$url = pll_home_url( $slug );
				}

				if ( ! $slug || ! $url ) {
					continue;
				}
				?>
				<li>
					<a href="<?php echo esc_url( $url ); ?>" lang="<?php echo esc_attr( $slug ); ?>" hreflang="<?php echo esc_attr( $slug ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Switch to %s', 'bricks-child' ), $name ) ); ?>"<?php echo ! empty( $language['current_lang'] ) ? ' aria-current="true"' : ''; ?>><?php echo esc_html( strtoupper( $slug ) ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
}

/**
 * Return a localized frontend URL using the project's existing resolver.
 */
function autoagora_code_header_url( $slug = '' ) {
	if ( function_exists( 'autoagora_localized_page_url' ) ) {
		return autoagora_localized_page_url( $slug );
	}

	return home_url( $slug ? '/' . trim( $slug, '/' ) . '/' : '/' );
}

/**
 * Check whether a URL represents the current page.
 */
function autoagora_code_header_is_current_url( $url ) {
	$request_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '/';
	$target_path  = wp_parse_url( $url, PHP_URL_PATH );

	return untrailingslashit( (string) $request_path ) === untrailingslashit( (string) $target_path );
}

/**
 * Hardcoded SVGs keep this global component independent of icon-font bundles.
 */
function autoagora_code_header_icon( $name ) {
	$icons = array(
		'account' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.12 12.78C12.05 12.77 11.96 12.77 11.88 12.78C10.12 12.72 8.71997 11.28 8.71997 9.50998C8.71997 7.69998 10.18 6.22998 12 6.22998C13.81 6.22998 15.28 7.69998 15.28 9.50998C15.27 11.28 13.88 12.72 12.12 12.78Z"/><path d="M18.74 19.3801C16.96 21.0101 14.6 22.0001 12 22.0001C9.40001 22.0001 7.04001 21.0101 5.26001 19.3801C5.36001 18.4401 5.96001 17.5201 7.03001 16.8001C9.77001 14.9801 14.25 14.9801 16.97 16.8001C18.04 17.5201 18.64 18.4401 18.74 19.3801Z"/><path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"/></svg>',
		'chevron' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>',
		'menu'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>',
		'close'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>',
		'home'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16.9999H15M3 14.5999V12.1301C3 10.9814 3 10.407 3.14805 9.87807C3.2792 9.40953 3.49473 8.96886 3.78405 8.57768C4.11067 8.13608 4.56404 7.78346 5.47078 7.07822L8.07078 5.056C9.47608 3.96298 10.1787 3.41648 10.9546 3.2064C11.6392 3.02104 12.3608 3.02104 13.0454 3.2064C13.8213 3.41648 14.5239 3.96299 15.9292 5.056L18.5292 7.07822C19.436 7.78346 19.8893 8.13608 20.2159 8.57768C20.5053 8.96886 20.7208 9.40953 20.8519 9.87807C21 10.407 21 10.9814 21 12.1301V14.5999C21 16.8401 21 17.9603 20.564 18.8159C20.1805 19.5685 19.5686 20.1805 18.816 20.564C17.9603 20.9999 16.8402 20.9999 14.6 20.9999H9.4C7.15979 20.9999 6.03969 20.9999 5.18404 20.564C4.43139 20.1805 3.81947 19.5685 3.43597 18.8159C3 17.9603 3 16.8401 3 14.5999Z"/></svg>',
		'car'     => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 8L5.72187 10.2682C5.90158 10.418 6.12811 10.5 6.36205 10.5H17.6379C17.8719 10.5 18.0984 10.418 18.2781 10.2682L21 8M6.5 14H6.51M17.5 14H17.51M8.16065 4.5H15.8394C16.5571 4.5 17.2198 4.88457 17.5758 5.50772L20.473 10.5777C20.8183 11.1821 21 11.8661 21 12.5623V18.5C21 19.0523 20.5523 19.5 20 19.5H19C18.4477 19.5 18 19.0523 18 18.5V17.5H6V18.5C6 19.0523 5.55228 19.5 5 19.5H4C3.44772 19.5 3 19.0523 3 18.5V12.5623C3 11.8661 3.18166 11.1821 3.52703 10.5777L6.42416 5.50772C6.78024 4.88457 7.44293 4.5 8.16065 4.5Z"/></svg>',
		'add'     => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M12 5v14"/></svg>',
		'blog'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 3H8.2C7.0799 3 6.51984 3 6.09202 3.21799C5.71569 3.40973 5.40973 3.71569 5.21799 4.09202C5 4.51984 5 5.0799 5 6.2V17.8C5 18.9201 5 19.4802 5.21799 19.908C5.40973 20.2843 5.71569 20.5903 6.09202 20.782C6.51984 21 7.0799 21 8.2 21H10M13 3L19 9M13 3V7.4C13 7.96005 13 8.24008 13.109 8.45399C13.2049 8.64215 13.3578 8.79513 13.546 8.89101C13.7599 9 14.0399 9 14.6 9H19M19 9V10M9 17H11.5M9 13H14M9 9H10M14 21L16.025 20.595C16.2015 20.5597 16.2898 20.542 16.3721 20.5097C16.4452 20.4811 16.5147 20.4439 16.579 20.399C16.6516 20.3484 16.7152 20.2848 16.8426 20.1574L21 16C21.5523 15.4477 21.5523 14.5523 21 14C20.4477 13.4477 19.5523 13.4477 19 14L14.8426 18.1574C14.7152 18.2848 14.6516 18.3484 14.601 18.421C14.5561 18.4853 14.5189 18.5548 14.4903 18.6279C14.458 18.7102 14.4403 18.7985 14.405 18.975L14 21Z"/></svg>',
		'heart'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"/></svg>',
	);

	return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
}

/**
 * Render the account dropdown content used by desktop and mobile.
 */
function autoagora_code_header_account_links() {
	$current_user = wp_get_current_user();
	$full_name    = trim( $current_user->first_name . ' ' . $current_user->last_name );
	$full_name    = $full_name ?: $current_user->display_name;
	?>
	<div class="aag-site-header__account-name"><?php echo esc_html( $full_name ); ?></div>
	<a href="<?php echo esc_url( autoagora_code_header_url( 'my-account' ) ); ?>"><?php esc_html_e( 'My account', 'bricks-child' ); ?></a>
	<a href="<?php echo esc_url( autoagora_code_header_url( 'my-listings' ) ); ?>"><?php esc_html_e( 'My listings', 'bricks-child' ); ?></a>
	<a href="<?php echo esc_url( autoagora_code_header_url( 'favourite-listings' ) ); ?>" class="aag-site-header__saved-link">
		<span class="aag-site-header__saved-icon"><?php echo autoagora_code_header_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<?php esc_html_e( 'Saved', 'bricks-child' ); ?>
	</a>
	<a href="<?php echo esc_url( wp_logout_url( autoagora_code_header_url() ) ); ?>"><?php esc_html_e( 'Log out', 'bricks-child' ); ?></a>
	<?php
}

/**
 * Render a main-menu list.
 */
function autoagora_code_header_render_menu( $class_name ) {
	?>
	<ul class="<?php echo esc_attr( $class_name ); ?>">
		<?php foreach ( autoagora_code_header_menu_items() as $item ) : ?>
			<?php $url = autoagora_code_header_url( $item['slug'] ); ?>
			<li>
				<a href="<?php echo esc_url( $url ); ?>"<?php echo autoagora_code_header_is_current_url( $url ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item['label'] ); ?></a>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/**
 * Render the full replacement at wp_body_open.
 */
function autoagora_render_code_header() {
	if ( ! autoagora_code_header_is_enabled() ) {
		return;
	}

	$logo_url = content_url( '/uploads/2025/06/autoagora-colored-logo-1024x213.png' );
	?>
	<header id="autoagora-site-header" class="aag-site-header">
		<div class="aag-site-header__desktop">
			<div class="aag-site-header__desktop-inner">
				<a class="aag-site-header__logo" href="<?php echo esc_url( autoagora_code_header_url() ); ?>" aria-label="<?php esc_attr_e( 'Autoagora home', 'bricks-child' ); ?>">
					<img src="<?php echo esc_url( $logo_url ); ?>" width="1024" height="213" alt="Autoagora" decoding="async" fetchpriority="high">
				</a>
				<nav class="aag-site-header__main-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'bricks-child' ); ?>">
					<?php autoagora_code_header_render_menu( 'aag-site-header__menu' ); ?>
				</nav>
				<div class="aag-site-header__desktop-account">
					<?php autoagora_code_header_render_language_switcher(); ?>
					<?php if ( is_user_logged_in() ) : ?>
						<details class="aag-site-header__details">
							<summary>
								<span class="aag-site-header__account-icon"><?php echo autoagora_code_header_icon( 'account' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span><?php esc_html_e( 'My Account', 'bricks-child' ); ?></span>
								<span class="aag-site-header__chevron"><?php echo autoagora_code_header_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							</summary>
							<div class="aag-site-header__account-menu"><?php autoagora_code_header_account_links(); ?></div>
						</details>
					<?php else : ?>
						<div class="aag-site-header__auth-links">
							<a href="<?php echo esc_url( autoagora_code_header_url( 'signin' ) ); ?>"><?php esc_html_e( 'Login', 'bricks-child' ); ?></a>
							<span aria-hidden="true">|</span>
							<a href="<?php echo esc_url( autoagora_code_header_url( 'register' ) ); ?>"><?php esc_html_e( 'Register', 'bricks-child' ); ?></a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="aag-site-header__mobile">
			<a class="aag-site-header__logo" href="<?php echo esc_url( autoagora_code_header_url() ); ?>" aria-label="<?php esc_attr_e( 'Autoagora home', 'bricks-child' ); ?>">
				<img src="<?php echo esc_url( $logo_url ); ?>" width="1024" height="213" alt="Autoagora" decoding="async" fetchpriority="high">
			</a>
			<div class="aag-site-header__mobile-shortcuts">
				<a href="<?php echo esc_url( autoagora_code_header_url( 'cars' ) ); ?>"><?php esc_html_e( 'Used Cars', 'bricks-child' ); ?></a>
				<a href="<?php echo esc_url( autoagora_code_header_url( 'add-listing' ) ); ?>"><?php esc_html_e( 'Sell My Car', 'bricks-child' ); ?></a>
			</div>
			<button type="button" class="aag-site-header__menu-toggle" aria-expanded="false" aria-controls="aag-site-header-drawer" aria-label="<?php esc_attr_e( 'Open menu', 'bricks-child' ); ?>">
				<?php echo autoagora_code_header_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
		</div>

		<div id="aag-site-header-drawer" class="aag-site-header__drawer" hidden>
			<button type="button" class="aag-site-header__drawer-close" aria-label="<?php esc_attr_e( 'Close menu', 'bricks-child' ); ?>">
				<?php echo autoagora_code_header_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
			<div class="aag-site-header__drawer-content">
				<nav aria-label="<?php esc_attr_e( 'Mobile navigation', 'bricks-child' ); ?>">
					<?php autoagora_code_header_render_menu( 'aag-site-header__drawer-menu' ); ?>
				</nav>
				<?php autoagora_code_header_render_language_switcher( 'aag-site-header__language-switcher--drawer' ); ?>
			</div>
		</div>
	</header>
	<div class="aag-site-header-spacer" aria-hidden="true"></div>

	<nav class="aag-mobile-dock" aria-label="<?php esc_attr_e( 'Mobile shortcuts', 'bricks-child' ); ?>">
		<div class="aag-mobile-dock__inner">
			<?php
			$dock_items = array(
				array( 'label' => __( 'Home', 'bricks-child' ), 'slug' => '', 'icon' => 'home' ),
				array( 'label' => __( 'Cars', 'bricks-child' ), 'slug' => 'cars', 'icon' => 'car' ),
				array( 'label' => __( 'Post', 'bricks-child' ), 'slug' => 'add-listing', 'icon' => 'add', 'primary' => true ),
				array( 'label' => __( 'Blog', 'bricks-child' ), 'slug' => 'blog', 'icon' => 'blog' ),
			);
			?>
			<?php foreach ( $dock_items as $item ) : ?>
				<?php
				$url        = autoagora_code_header_url( $item['slug'] );
				$is_current = autoagora_code_header_is_current_url( $url );

				if ( 'cars' === $item['slug'] && ( is_singular( 'car' ) || is_tax( 'car_make' ) ) ) {
					$is_current = true;
				} elseif ( 'blog' === $item['slug'] && is_singular( 'post' ) ) {
					$is_current = true;
				}
				?>
				<a class="aag-mobile-dock__item<?php echo ! empty( $item['primary'] ) ? ' aag-mobile-dock__item--primary' : ''; ?>" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $item['label'] ); ?>"<?php echo $is_current ? ' aria-current="page"' : ''; ?>>
					<span class="aag-mobile-dock__icon"><?php echo autoagora_code_header_icon( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php if ( empty( $item['primary'] ) ) : ?>
						<span class="aag-mobile-dock__label"><?php echo esc_html( $item['label'] ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>

			<?php if ( is_user_logged_in() ) : ?>
				<details class="aag-site-header__details aag-mobile-dock__account">
					<summary aria-label="<?php esc_attr_e( 'Account', 'bricks-child' ); ?>"<?php echo autoagora_code_header_is_current_url( autoagora_code_header_url( 'my-account' ) ) ? ' aria-current="page"' : ''; ?>>
						<span class="aag-mobile-dock__icon"><?php echo autoagora_code_header_icon( 'account' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="aag-mobile-dock__label"><?php esc_html_e( 'Account', 'bricks-child' ); ?></span>
					</summary>
					<div class="aag-site-header__account-menu aag-mobile-dock__account-menu"><?php autoagora_code_header_account_links(); ?></div>
				</details>
			<?php else : ?>
				<a class="aag-mobile-dock__item" href="<?php echo esc_url( autoagora_code_header_url( 'signin' ) ); ?>" aria-label="<?php esc_attr_e( 'Login', 'bricks-child' ); ?>">
					<span class="aag-mobile-dock__icon"><?php echo autoagora_code_header_icon( 'account' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="aag-mobile-dock__label"><?php esc_html_e( 'Account', 'bricks-child' ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</nav>
	<?php
}
add_action( 'wp_body_open', 'autoagora_render_code_header', 5 );
