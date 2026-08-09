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
	if ( function_exists( 'pll_current_language' ) ) {
		$language = pll_current_language( 'slug' );
		if ( is_string( $language ) && $language !== '' ) {
			return $language;
		}
	}

	$available_languages = function_exists( 'pll_languages_list' )
		? pll_languages_list( array( 'fields' => 'slug' ) )
		: array( 'en', 'el', 'ru' );
	$available_languages = is_array( $available_languages ) ? $available_languages : array( 'en', 'el', 'ru' );

	$candidates = array(
		isset( $_REQUEST['lang'] ) ? sanitize_key( wp_unslash( $_REQUEST['lang'] ) ) : '',
		isset( $_COOKIE['pll_language'] ) ? sanitize_key( wp_unslash( $_COOKIE['pll_language'] ) ) : '',
	);
	foreach ( $candidates as $candidate ) {
		if ( $candidate !== '' && in_array( $candidate, $available_languages, true ) ) {
			return $candidate;
		}
	}

	$referer      = wp_get_referer();
	$referer_path = $referer ? wp_parse_url( $referer, PHP_URL_PATH ) : '';
	if ( is_string( $referer_path ) ) {
		$segments = explode( '/', trim( $referer_path, '/' ) );
		if ( ! empty( $segments[0] ) && in_array( $segments[0], $available_languages, true ) ) {
			return $segments[0];
		}
	}

	return substr( determine_locale(), 0, 2 );
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
