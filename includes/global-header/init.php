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
		'account' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.12 12.78c-.07-.01-.16-.01-.24 0a3.27 3.27 0 1 1 .24 0Z"/><path d="M18.74 19.38A9.96 9.96 0 0 1 12 22a9.96 9.96 0 0 1-6.74-2.62c.1-.94.7-1.86 1.77-2.58 2.74-1.82 7.22-1.82 9.94 0 1.07.72 1.67 1.64 1.77 2.58Z"/><circle cx="12" cy="12" r="10"/></svg>',
		'chevron' => '<svg viewBox="0 0 12 12" aria-hidden="true"><path d="m1.5 4 4.5 4 4.5-4"/></svg>',
		'menu'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>',
		'close'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>',
		'home'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 17h6M3 14.6v-2.47c0-1.15 0-1.72.15-2.25.13-.47.34-.91.63-1.3.33-.44.78-.8 1.69-1.5l2.6-2.02C9.48 3.96 10.18 3.42 10.95 3.2a4 4 0 0 1 2.1 0c.77.21 1.48.76 2.88 1.85l2.6 2.02c.91.71 1.36 1.06 1.69 1.5.29.4.5.84.63 1.3.15.53.15 1.1.15 2.25v2.47c0 2.24 0 3.36-.44 4.22a4 4 0 0 1-1.75 1.75c-.85.44-1.97.44-4.21.44H9.4c-2.24 0-3.36 0-4.22-.44a4 4 0 0 1-1.74-1.75C3 17.96 3 16.84 3 14.6Z"/></svg>',
		'car'     => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 8 2.72 2.27c.18.15.41.23.64.23h11.28c.23 0 .46-.08.64-.23L21 8M6.5 14h.01M17.5 14h.01M8.16 4.5h7.68c.72 0 1.38.38 1.74 1.01l2.9 5.07c.34.6.52 1.29.52 1.98v5.94a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1v-1H6v1a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-5.94c0-.7.18-1.38.53-1.98l2.9-5.07a2 2 0 0 1 1.73-1.01Z"/></svg>',
		'add'     => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>',
		'blog'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 3H8.2c-1.12 0-1.68 0-2.11.22-.38.19-.68.5-.87.87C5 4.52 5 5.08 5 6.2v11.6c0 1.12 0 1.68.22 2.11.19.38.5.68.87.87C6.52 21 7.08 21 8.2 21H10M13 3l6 6M13 3v4.4c0 .56 0 .84.11 1.05.1.19.25.34.44.44.21.11.49.11 1.05.11H19v1M9 17h2.5M9 13h5M9 9h1M14 21l2.03-.41c.17-.03.26-.05.34-.08.08-.03.15-.07.21-.11.07-.05.14-.12.27-.24L21 16a1.41 1.41 0 1 0-2-2l-4.16 4.16c-.13.13-.19.19-.24.26-.05.07-.08.14-.11.21-.03.08-.05.17-.09.35L14 21Z"/></svg>',
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
			<nav aria-label="<?php esc_attr_e( 'Mobile navigation', 'bricks-child' ); ?>">
				<?php autoagora_code_header_render_menu( 'aag-site-header__drawer-menu' ); ?>
			</nav>
		</div>
	</header>

	<nav class="aag-mobile-dock" aria-label="<?php esc_attr_e( 'Mobile shortcuts', 'bricks-child' ); ?>">
		<div class="aag-mobile-dock__inner">
			<?php
			$dock_items = array(
				array( 'label' => __( 'Home', 'bricks-child' ), 'slug' => '', 'icon' => 'home' ),
				array( 'label' => __( 'Used Cars', 'bricks-child' ), 'slug' => 'cars', 'icon' => 'car' ),
				array( 'label' => __( 'Sell My Car', 'bricks-child' ), 'slug' => 'add-listing', 'icon' => 'add', 'primary' => true ),
				array( 'label' => __( 'Blog', 'bricks-child' ), 'slug' => 'blog', 'icon' => 'blog' ),
			);
			?>
			<?php foreach ( $dock_items as $item ) : ?>
				<?php $url = autoagora_code_header_url( $item['slug'] ); ?>
				<a class="aag-mobile-dock__item<?php echo ! empty( $item['primary'] ) ? ' aag-mobile-dock__item--primary' : ''; ?>" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $item['label'] ); ?>"<?php echo autoagora_code_header_is_current_url( $url ) ? ' aria-current="page"' : ''; ?>>
					<?php echo autoagora_code_header_icon( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endforeach; ?>

			<?php if ( is_user_logged_in() ) : ?>
				<details class="aag-site-header__details aag-mobile-dock__account">
					<summary aria-label="<?php esc_attr_e( 'My Account', 'bricks-child' ); ?>"><?php echo autoagora_code_header_icon( 'account' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></summary>
					<div class="aag-site-header__account-menu aag-mobile-dock__account-menu"><?php autoagora_code_header_account_links(); ?></div>
				</details>
			<?php else : ?>
				<a class="aag-mobile-dock__item" href="<?php echo esc_url( autoagora_code_header_url( 'signin' ) ); ?>" aria-label="<?php esc_attr_e( 'Login', 'bricks-child' ); ?>">
					<?php echo autoagora_code_header_icon( 'account' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endif; ?>
		</div>
	</nav>
	<?php
}
add_action( 'wp_body_open', 'autoagora_render_code_header', 5 );
