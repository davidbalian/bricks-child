<?php
/**
 * Lightweight frontend translations for code-generated marketplace UI.
 *
 * @package Bricks Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the active frontend language slug.
 */
function autoagora_current_language() {
	$available_languages = function_exists( 'pll_languages_list' )
		? pll_languages_list( array( 'fields' => 'slug' ) )
		: array( 'en', 'el', 'ru' );
	$available_languages = is_array( $available_languages ) ? $available_languages : array( 'en', 'el', 'ru' );

	$explicit_language = isset( $_REQUEST['lang'] ) && is_string( $_REQUEST['lang'] )
		? sanitize_key( wp_unslash( $_REQUEST['lang'] ) )
		: '';
	if ( $explicit_language !== '' && in_array( $explicit_language, $available_languages, true ) ) {
		return $explicit_language;
	}

	$request_path = isset( $_SERVER['REQUEST_URI'] )
		? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH )
		: '';
	if ( is_string( $request_path ) ) {
		$request_segments = explode( '/', trim( $request_path, '/' ) );
		if ( ! empty( $request_segments[0] ) && in_array( $request_segments[0], $available_languages, true ) ) {
			return $request_segments[0];
		}
	}

	$cookie_language = isset( $_COOKIE['pll_language'] ) && is_string( $_COOKIE['pll_language'] )
		? sanitize_key( wp_unslash( $_COOKIE['pll_language'] ) )
		: '';
	$is_neutral_singular = ! is_admin()
		&& function_exists( 'is_singular' )
		&& is_singular( array( 'car', 'buyer_request', 'dealer_profile' ) );

	if ( $is_neutral_singular && $cookie_language !== '' && in_array( $cookie_language, $available_languages, true ) ) {
		return $cookie_language;
	}

	$script_name          = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : '';
	$is_transport_request = wp_doing_ajax() || $script_name === 'admin-post.php';
	$referer              = wp_get_referer();
	if ( $referer && ( $is_transport_request || $is_neutral_singular ) ) {
		$referer_query = wp_parse_url( $referer, PHP_URL_QUERY );
		if ( is_string( $referer_query ) ) {
			parse_str( $referer_query, $referer_args );
			$referer_language = isset( $referer_args['lang'] ) && is_string( $referer_args['lang'] )
				? sanitize_key( $referer_args['lang'] )
				: '';
			if ( $referer_language !== '' && in_array( $referer_language, $available_languages, true ) ) {
				return $referer_language;
			}
		}

		$referer_path = wp_parse_url( $referer, PHP_URL_PATH );
		if ( is_string( $referer_path ) ) {
			$referer_segments = explode( '/', trim( $referer_path, '/' ) );
			if ( ! empty( $referer_segments[0] ) && in_array( $referer_segments[0], $available_languages, true ) ) {
				return $referer_segments[0];
			}
		}
	}

	if ( function_exists( 'pll_current_language' ) ) {
		$language = pll_current_language( 'slug' );
		if ( is_string( $language ) && $language !== '' ) {
			return $language;
		}
	}

	if ( $cookie_language !== '' && in_array( $cookie_language, $available_languages, true ) ) {
		return $cookie_language;
	}

	$locale_language = substr( determine_locale(), 0, 2 );
	return in_array( $locale_language, $available_languages, true ) ? $locale_language : 'en';
}

/**
 * Return code-owned translations for the active language.
 */
function autoagora_translation_catalog( $language = null ) {
	$language = $language ?: autoagora_current_language();
	static $loaded_catalogs = array();

	if ( isset( $loaded_catalogs[ $language ] ) ) {
		return $loaded_catalogs[ $language ];
	}

	$catalogs = array(
		'el' => array(
			get_stylesheet_directory() . '/includes/i18n/translations/el.php',
			get_stylesheet_directory() . '/includes/i18n/translations/el-frontend.php',
			get_stylesheet_directory() . '/includes/i18n/translations/el-overrides.php',
		),
		'ru' => array(
			get_stylesheet_directory() . '/includes/i18n/translations/ru.php',
			get_stylesheet_directory() . '/includes/i18n/translations/ru-overrides.php',
		),
	);

	if ( ! isset( $catalogs[ $language ] ) ) {
		$loaded_catalogs[ $language ] = array();
		return $loaded_catalogs[ $language ];
	}

	$translations = array();
	foreach ( $catalogs[ $language ] as $catalog_path ) {
		if ( ! file_exists( $catalog_path ) ) {
			continue;
		}

		$catalog = require $catalog_path;
		if ( is_array( $catalog ) ) {
			$translations = array_merge( $translations, $catalog );
		}
	}

	$loaded_catalogs[ $language ] = $translations;
	return $loaded_catalogs[ $language ];
}

/**
 * Whether the current request belongs to the public site.
 *
 * WordPress serves both frontend and wp-admin AJAX through admin-ajax.php.
 * The referer check keeps frontend form responses translated without changing
 * AJAX feedback inside wp-admin screens.
 */
function autoagora_i18n_is_frontend_request() {
	if ( ! is_admin() ) {
		return true;
	}

	if ( ! wp_doing_ajax() ) {
		return false;
	}

	$referer = wp_get_referer();
	if ( ! $referer ) {
		return true;
	}

	$referer_path = wp_parse_url( $referer, PHP_URL_PATH );
	$admin_path   = wp_parse_url( admin_url( '/' ), PHP_URL_PATH );

	if ( ! is_string( $referer_path ) || ! is_string( $admin_path ) ) {
		return true;
	}

	return strpos( trailingslashit( $referer_path ), trailingslashit( $admin_path ) ) !== 0;
}

/**
 * Translate strings in the child theme's existing gettext calls.
 */
function autoagora_translate_child_theme_string( $translation, $text, $domain ) {
	if ( $domain !== 'bricks-child' || ! autoagora_i18n_is_frontend_request() ) {
		return $translation;
	}

	$catalog = autoagora_translation_catalog();
	return isset( $catalog[ $text ] ) ? $catalog[ $text ] : $translation;
}
add_filter( 'gettext_bricks-child', 'autoagora_translate_child_theme_string', 10, 3 );

/**
 * Translate singular/plural strings in the child theme.
 */
function autoagora_translate_child_theme_plural( $translation, $single, $plural, $number ) {
	if ( ! autoagora_i18n_is_frontend_request() ) {
		return $translation;
	}

	$catalog = autoagora_translation_catalog();
	$key     = (int) $number === 1 ? $single : $plural;

	return isset( $catalog[ $key ] ) ? $catalog[ $key ] : $translation;
}
add_filter( 'ngettext_bricks-child', 'autoagora_translate_child_theme_plural', 10, 4 );

/**
 * Expose the same code-owned catalog to frontend JavaScript.
 *
 * Scripts use the English source string as the stable lookup key. English
 * therefore needs no catalog payload, while Greek and Russian receive the
 * active merged catalog.
 */
function autoagora_enqueue_frontend_i18n() {
	$script_path = get_stylesheet_directory() . '/includes/i18n/frontend.js';
	$script_url  = get_stylesheet_directory_uri() . '/includes/i18n/frontend.js';

	wp_enqueue_script(
		'autoagora-i18n',
		$script_url,
		array(),
		file_exists( $script_path ) ? filemtime( $script_path ) : null,
		false
	);

	wp_localize_script(
		'autoagora-i18n',
		'autoagoraI18nData',
		array(
			'language' => autoagora_current_language(),
			'strings'  => autoagora_translation_catalog(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'autoagora_enqueue_frontend_i18n', 1 );

/**
 * Resolve a translated page URL while retaining an English fallback.
 */
function autoagora_localized_page_url( $english_slug = '' ) {
	$english_slug = trim( (string) $english_slug, '/' );
	$language = autoagora_current_language();

	if ( $english_slug === '' ) {
		if ( function_exists( 'pll_home_url' ) ) {
			$home_url = pll_home_url( $language );
			if ( is_string( $home_url ) && $home_url !== '' ) {
				return $home_url;
			}
		}
		return home_url( '/' );
	}

	$source_page = get_page_by_path( $english_slug );
	if ( $source_page instanceof WP_Post && function_exists( 'pll_get_post' ) ) {
		$translated_id = pll_get_post( $source_page->ID, $language );
		if ( $translated_id ) {
			return get_permalink( $translated_id );
		}
	}

	return home_url( '/' . $english_slug . '/' );
}

/**
 * Return a frontend URL for language-neutral marketplace content.
 *
 * Cars, buyer requests, and dealer profiles are user-generated records rather
 * than separately translated posts. Keep their canonical permalink intact for
 * SEO, but carry the selected interface language while the visitor browses.
 * If a post does have a real Polylang translation, prefer that permalink.
 *
 * @param int         $post_id  Post ID.
 * @param string|null $language Optional language slug. Defaults to the current language.
 * @return string
 */
function autoagora_localized_content_url( $post_id, $language = null ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		return '';
	}

	$canonical_url = get_permalink( $post_id );
	if ( ! is_string( $canonical_url ) || $canonical_url === '' ) {
		return '';
	}

	$language = sanitize_key( $language ?: autoagora_current_language() );
	$default_language = function_exists( 'pll_default_language' )
		? pll_default_language( 'slug' )
		: 'en';
	$default_language = is_string( $default_language ) && $default_language !== '' ? $default_language : 'en';

	if ( $language === '' || $language === $default_language ) {
		return $canonical_url;
	}

	if ( function_exists( 'pll_get_post' ) ) {
		$translated_id = pll_get_post( $post_id, $language );
		if ( $translated_id && (int) $translated_id !== $post_id ) {
			$translated_url = get_permalink( $translated_id );
			if ( is_string( $translated_url ) && $translated_url !== '' ) {
				return $translated_url;
			}
		}
	}

	$neutral_post_types = (array) apply_filters(
		'autoagora_language_neutral_post_types',
		array( 'car', 'buyer_request', 'dealer_profile' )
	);

	if ( ! in_array( get_post_type( $post_id ), $neutral_post_types, true ) ) {
		return $canonical_url;
	}

	return add_query_arg( 'lang', $language, $canonical_url );
}
