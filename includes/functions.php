<?php
/**
 * Public Helper Functions
 *
 * Provides global helper functions for the Shills Simple Currency Switcher plugin.
 * These functions can be used in themes and other plugins.
 *
 * @package ShillsSimpleCurrencySwitcher
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the plugin's main instance.
 *
 * @since 1.0.0
 * @return SHSCS_Main Plugin main instance.
 */
function shscs(): SHSCS_Main {
	return SHSCS_Main::get_instance();
}

/**
 * Get the current active currency code.
 *
 * @since 1.0.0
 * @return string Current currency code (e.g., 'USD', 'EUR').
 */
function shscs_get_currency(): string {
	return SHSCS_Main::get_current_currency();
}

/**
 * Convert an amount from one currency to another.
 *
 * @since 1.0.0
 * @param float       $amount Amount to convert.
 * @param string|null $from   Source currency code (optional, defaults to base currency).
 * @param string|null $to     Target currency code (optional, defaults to current currency).
 * @return float Converted amount.
 */
function shscs_convert( float $amount, ?string $from = null, ?string $to = null ): float {
	if ( null === $from ) {
		$from = get_option( 'shscs_base_currency', 'USD' );
	}
	if ( null === $to ) {
		$to = shscs_get_currency();
	}

	return SHSCS_Main::convert_amount( $amount, $from, $to, false );
}

/**
 * Format a price with the current currency symbol and formatting rules.
 *
 * @since 1.0.0
 * @param float       $amount   Price amount to format.
 * @param string|null $currency Currency code (optional, defaults to current currency).
 * @return string Formatted price string.
 */
function shscs_format_price( float $amount, ?string $currency = null ): string {
	if ( null === $currency ) {
		$currency = shscs_get_currency();
	}

	return SHSCS_Main::format_price( $amount, $currency );
}

/**
 * Check if a currency code is valid and enabled.
 *
 * @since 1.0.0
 * @param string $code Currency code to validate (e.g., 'USD').
 * @return bool True if currency is valid and enabled, false otherwise.
 */
function shscs_is_valid_currency( string $code ): bool {
	return SHSCS_Main::is_valid_currency( $code );
}

/**
 * Get all available currencies.
 *
 * @since 1.0.0
 * @param bool $enabled_only Whether to return only enabled currencies.
 * @return array Array of currency data.
 */
function shscs_get_currencies( bool $enabled_only = false ): array {
	$currency = SHSCS_Main::get_instance()->get_component( 'currency' );
	if ( $currency && method_exists( $currency, 'get_currencies' ) ) {
		return $currency->get_currencies( $enabled_only );
	}
	return array();
}

/**
 * Get the currency symbol for a given currency code.
 *
 * @since 1.0.0
 * @param string $code Currency code (e.g., 'USD').
 * @return string Currency symbol (e.g., '$').
 */
function shscs_get_currency_symbol( string $code ): string {
	return SHSCS_Currency::get_default_symbol( $code );
}

/**
 * Get the base currency set in plugin settings.
 *
 * @since 1.0.0
 * @return string Base currency code.
 */
function shscs_get_base_currency(): string {
	return get_option( 'shscs_base_currency', 'USD' );
}

/**
 * Get the exchange rate between two currencies.
 *
 * @since 1.0.0
 * @param string $from Source currency code.
 * @param string $to   Target currency code.
 * @return float|false Exchange rate or false if not available.
 */
function shscs_get_exchange_rate( string $from, string $to ) {
	$currency = SHSCS_Main::get_instance()->get_component( 'currency' );
	if ( $currency && method_exists( $currency, 'get_rate' ) ) {
		return $currency->get_rate( $from, $to );
	}
	return false;
}

/**
 * Get the currency cookie name.
 *
 * @since 1.0.0
 * @return string Cookie name.
 */
function shscs_get_cookie_name(): string {
	return 'shscs_currency';
}

/**
 * Get the user choice cookie name.
 *
 * @since 1.0.0
 * @return string Cookie name.
 */
function shscs_get_user_choice_cookie_name(): string {
	return 'shscs_user_currency_choice';
}

/**
 * Check if user has manually chosen a currency.
 *
 * @since 1.0.0
 * @return bool True if user has made a manual choice.
 */
function shscs_has_user_chosen(): bool {
	return isset( $_COOKIE['shscs_user_currency_choice'] ) && '1' === $_COOKIE['shscs_user_currency_choice'];
}

/**
 * Get cookie domain for the site.
 *
 * @since 1.0.0
 * @return string Cookie domain.
 */
function shscs_get_cookie_domain(): string {
	if ( defined( 'COOKIE_DOMAIN' ) ) {
		return COOKIE_DOMAIN;
	}
	return '.' . wp_parse_url( home_url(), PHP_URL_HOST );
}

/**
 * Get cookie path.
 *
 * @since 1.0.0
 * @return string Cookie path.
 */
function shscs_get_cookie_path(): string {
	if ( defined( 'COOKIEPATH' ) ) {
		return COOKIEPATH;
	}
	return '/';
}

/**
 * Get current currency from URL parameter or cookie.
 *
 * Priority: URL parameter > Cookie
 *
 * @since 1.0.0
 * @return string Currency code or empty string.
 */
function shscs_get_currency_from_request(): string {
	$currency = filter_input( INPUT_GET, 'shscs_currency', FILTER_SANITIZE_SPECIAL_CHARS );
	if ( $currency && shscs_is_valid_currency( $currency ) ) {
		return $currency;
	}

	if ( isset( $_COOKIE['shscs_currency'] ) ) {
		$currency = sanitize_text_field( wp_unslash( $_COOKIE['shscs_currency'] ) );
		if ( shscs_is_valid_currency( $currency ) ) {
			return $currency;
		}
	}

	return '';
}

/**
 * Check if Vary header should be added based on currency cookie.
 *
 * @deprecated 1.1.0 No longer used with URL parameter approach.
 * @since      1.0.0
 * @return     bool True if Vary header should be added.
 */
function shscs_should_add_vary_header(): bool {
	return false;
}

/**
 * Send Vary and Cache-Control headers to prevent CDN caching.
 *
 * @deprecated 1.1.0 No longer used with URL parameter approach.
 * @since      1.0.0
 */
function shscs_send_vary_header(): void {
	// Deprecated: URL parameter approach replaces cookie-based Vary header.
}