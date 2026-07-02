<?php
/**
 * REST API Endpoints
 *
 * @package ShillsSimpleCurrencySwitcher
 * @since   1.0.0
 */

declare(strict_types=1);

if (!defined("ABSPATH")) {
	exit();
}



/**
 * Class SHSCS_REST
 *
 * Handles all REST API requests including frontend currency switching
 * and backend currency management.
 */
class SHSCS_REST
{
	private static ?self $instance = null;
	private string $namespace = "shscs/v1";

	/**
	 * Get singleton instance
	 */
	public static function get_instance(): self
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - register REST API routes
	 */
	private function __construct()
	{
		add_action("rest_api_init", [$this, "register_routes"]);
	}

	/**
	 * Register all REST API routes
	 */
	public function register_routes(): void
	{
		// Frontend: Get currency switcher settings
		register_rest_route($this->namespace, "/settings", [
			"methods" => WP_REST_Server::READABLE,
			"callback" => [$this, "get_settings"],
			"permission_callback" => "__return_true",
		]);

		// Frontend: Switch currency
		// register_rest_route($this->namespace, "/switch", [
		// 	"methods" => WP_REST_Server::CREATABLE,
		// 	"callback" => [$this, "switch_currency"],
		// 	"permission_callback" => "__return_true",
		// 	"args" => [
		// 		"currency" => [
		// 			"required" => true,
		// 			"type" => "string",
		// 			"sanitize_callback" => "sanitize_text_field",
		// 		],
		// 	],
		// ]);

		// Frontend: Switch currency
		register_rest_route($this->namespace, "/switch", [
			"methods" => WP_REST_Server::CREATABLE,
			"callback" => [$this, "switch_currency"],
			"permission_callback" => "__return_true",
			// "permission_callback" => fn() => current_user_can("manage_options"),
			"args" => [
				"currency" => [
					"required" => true,
					"type" => "string",
					"sanitize_callback" => "sanitize_text_field",
					"validate_callback" => function ($param) {
						return preg_match('/^[A-Z]{3}$/', $param);
					},
				],
			],
		]);

		// Admin: Update exchange rates
		register_rest_route($this->namespace, "/update-rates", [
			"methods" => WP_REST_Server::CREATABLE,
			"callback" => [$this, "update_rates"],
			"permission_callback" => fn() => current_user_can("manage_options"),
		]);

		// Frontend: Get theme color
		register_rest_route($this->namespace, "/theme-color", [
			"methods" => WP_REST_Server::READABLE,
			"callback" => [$this, "get_theme_color"],
			"permission_callback" => "__return_true",
		]);

		// Frontend: Get language-currency mapping
		register_rest_route($this->namespace, "/lang-map", [
			"methods" => WP_REST_Server::READABLE,
			"callback" => [$this, "get_lang_map"],
			"permission_callback" => "__return_true",
		]);

		// Frontend: Sync language with currency
		register_rest_route($this->namespace, "/sync-lang", [
			"methods" => WP_REST_Server::CREATABLE,
			"callback" => [$this, "sync_language_currency"],
			"permission_callback" => "__return_true",
			"args" => [
				"language" => [
					"required" => true,
					"type" => "string",
					"sanitize_callback" => "sanitize_text_field",
				],
			],
		]);

		// Fine-grained language-currency sync endpoint
		register_rest_route($this->namespace, "/sync-currency", [
			"methods" => WP_REST_Server::CREATABLE,
			"callback" => [$this, "sync_currency_via_api"],
			"permission_callback" => "__return_true",
			"args" => [
				"language" => [
					"required" => true,
					"type" => "string",
					"sanitize_callback" => "sanitize_text_field",
				],
				"source" => [
					"type" => "string",
					"default" => "auto",
					"enum" => ["auto", "user"],
					"sanitize_callback" => "sanitize_text_field",
				],
			],
		]);

		// ============================================
		// Admin: Currency CRUD endpoints
		// ============================================

		// Get all currencies / Add new currency
		register_rest_route($this->namespace, "/currencies", [
			[
				"methods" => WP_REST_Server::READABLE,
				"callback" => [$this, "get_currencies_admin"],
				"permission_callback" => fn() => current_user_can("manage_options"),
			],
			[
				"methods" => WP_REST_Server::CREATABLE,
				"callback" => [$this, "add_currency"],
				"permission_callback" => fn() => current_user_can("manage_options"),
			],
		]);




		// Single currency operations: Get, Update, Delete
		register_rest_route($this->namespace, "/currency/(?P<code>[A-Z]{3})", [
			// GET: Get single currency
			[
				"methods" => WP_REST_Server::READABLE,
				"callback" => [$this, "get_currency"],
				"permission_callback" => fn() => current_user_can("manage_options"),
				"args" => [
					"code" => [
						"required" => true,
						"type" => "string",
						"validate_callback" => function ($param) {
							return preg_match("/^[A-Z]{3}$/", $param);
						},
					],
				],
			],
			// POST/PUT: Update currency
			[
				"methods" => WP_REST_Server::EDITABLE,
				"callback" => [$this, "update_currency"],
				"permission_callback" => fn() => current_user_can("manage_options"),
				"args" => [
					"code" => [
						"required" => true,
						"type" => "string",
					],
				],
			],
			// DELETE: Delete currency
			[
				"methods" => WP_REST_Server::DELETABLE,
				"callback" => [$this, "delete_currency"],
				"permission_callback" => fn() => current_user_can("manage_options"),
				"args" => [
					"code" => [
						"required" => true,
						"type" => "string",
						"validate_callback" => function ($param) {
							return preg_match("/^[A-Z]{3}$/", $param);
						},
					],
				],
			],
		]);
	}

	// ============================================
	// Currency CRUD Methods
	// ============================================

	/**
	 * Get all currencies for admin
	 */
	public function get_currencies_admin(): WP_REST_Response
	{
		$currency = SHSCS_Main::get_instance()->get_component("currency");

		if (!$currency) {
			return new WP_REST_Response(
				[
					"success" => false,
					"message" => "Currency component not found",
				],
				500
			);
		}

		return new WP_REST_Response([
			"success" => true,
			"currencies" => $currency->get_currencies(),
			"baseCurrency" => $currency->get_base_currency(),
		], 200);
	}

	/**
	 * Get single currency by code
	 */
	public function get_currency(WP_REST_Request $request): WP_REST_Response
	{
		$code = $request->get_param("code");
		$currency = SHSCS_Main::get_instance()->get_component("currency");

		if (!$currency) {
			return new WP_REST_Response(
				["success" => false, "message" => "Component not found"],
				500
			);
		}

		$curr = $currency->get_currency($code);

		if (!$curr) {
			return new WP_REST_Response(
				["success" => false, "message" => "Currency not found"],
				404
			);
		}

		return new WP_REST_Response(
			["success" => true, "currency" => $curr],
			200
		);
	}

	/**
	 * Add new currency
	 */
	public function add_currency(WP_REST_Request $request): WP_REST_Response
	{
		$currency = SHSCS_Main::get_instance()->get_component("currency");

		if (!$currency) {
			return new WP_REST_Response(
				["success" => false, "message" => "Component not found"],
				500
			);
		}

		$data = [
			"code" => strtoupper(sanitize_text_field($request->get_param("code"))),
			"symbol" => sanitize_text_field($request->get_param("symbol") ?? "$"),
			"rate" => floatval($request->get_param("rate") ?? 1.0),
			"position" => sanitize_text_field($request->get_param("position") ?? "left"),
			"decimals" => intval($request->get_param("decimals") ?? 2),
			//"enabled"  => (bool) $request->get_param("enabled"),
			"enabled" => !empty($request->get_param("enabled")) ? 1 : 0,
		];

		$result = $currency->add_currency($data);

		if (is_wp_error($result)) {
			return new WP_REST_Response(
				[
					"success" => false,
					"message" => $result->get_error_message(),
				],
				400
			);
		}

		return new WP_REST_Response(
			["success" => true, "currency" => $data],
			201
		);
	}

	/**
	 * Update existing currency
	 */
	public function update_currency(WP_REST_Request $request): WP_REST_Response
	{
		$code = $request->get_param("code");
		$currency = SHSCS_Main::get_instance()->get_component("currency");

		if (!$currency) {
			return new WP_REST_Response(
				["success" => false, "message" => "Component not found"],
				500
			);
		}

		$data = [];
		if ($request->has_param("symbol")) {
			$data["symbol"] = sanitize_text_field($request->get_param("symbol"));
		}
		if ($request->has_param("rate")) {
			$data["rate"] = floatval($request->get_param("rate"));
		}
		if ($request->has_param("position")) {
			$data["position"] = sanitize_text_field($request->get_param("position"));
		}
		if ($request->has_param("decimals")) {
			$data["decimals"] = intval($request->get_param("decimals"));
		}
		if ($request->has_param("enabled")) {
			//$data["enabled"] = (bool) $request->get_param("enabled");
			$data["enabled"] = !empty($request->get_param("enabled")) ? 1 : 0;
		}

		$result = $currency->update_currency($code, $data);

		if (is_wp_error($result)) {
			return new WP_REST_Response(
				[
					"success" => false,
					"message" => $result->get_error_message(),
				],
				400
			);
		}

		return new WP_REST_Response(["success" => true], 200);
	}

	/**
	 * Delete currency
	 */
	public function delete_currency(WP_REST_Request $request): WP_REST_Response
	{
		$code = $request->get_param("code");
		$currency = SHSCS_Main::get_instance()->get_component("currency");

		if (!$currency) {
			return new WP_REST_Response(
				[
					"success" => false,
					"message" => "Currency component not found",
				],
				500
			);
		}

		$result = $currency->delete_currency($code);

		if (is_wp_error($result)) {
			return new WP_REST_Response(
				[
					"success" => false,
					"message" => $result->get_error_message(),
				],
				400
			);
		}

		return new WP_REST_Response(
			[
				"success" => true,
				"deleted" => $code,
			],
			200
		);
	}

	// ============================================
	// Frontend Methods
	// ============================================

	/**
	 * Get currency switcher settings for frontend
	 */
	public function get_settings(): WP_REST_Response
	{
		$currency = SHSCS_Main::get_instance()->get_component("currency");

		return new WP_REST_Response(
			[
				"base_currency" => get_option("shscs_base_currency", "USD"),
				"currencies" => $currency ? $currency->get_frontend_data() : [],
				"current" => shscs_get_currency(),
				"theme_mode" => get_option("shscs_theme_mode", "auto"),
				"custom_color" => get_option("shscs_custom_color", "#007cba"),
				"last_update" => get_option("shscs_last_update", 0),
				"multilingual" => [
					"active" => defined("SHSCS_MULTILINGUAL_ACTIVE") && SHSCS_MULTILINGUAL_ACTIVE,
					"wpml" => defined("SHSCS_WPML_ACTIVE") && SHSCS_WPML_ACTIVE,
					"polylang" => defined("SHSCS_POLYLANG_ACTIVE") && SHSCS_POLYLANG_ACTIVE,
					"sync" => get_option("shscs_lang_currency_sync", true),
				],
			],
			200
		);
	}

	/**
	 * Switch currency via cookie
	 */
	public function switch_currency(WP_REST_Request $request)
	{
		$code = $request->get_param("currency");
		$all_currencies = get_option('shscs_currencies', []);

		// Validate currency switch request

		if (!SHSCS_Main::is_valid_currency($code)) {
			$is_valid = false;
			$enabled_currencies = [];

			if (is_array($all_currencies)) {
				foreach ($all_currencies as $currency) {
					if (is_array($currency) && ($currency['code'] ?? '') === $code) {
						$is_valid = true;
						$enabled = !empty($currency['enabled'] ?? false);
						// Check if currency is enabled
						if (!$enabled) {
							return new WP_Error(
								"currency_disabled",
								/* translators: %s: currency code */
								sprintf(__("Currency %s is disabled", "shills-simple-currency-switcher"), $code),
								["status" => 400]
							);
						}
						break;
					}
				}
			}

			if (!$is_valid) {
				return new WP_Error(
					"invalid_currency",
					sprintf(
						/* translators: %1$s: invalid currency code, %2$s: list of available currencies */
						__("Invalid currency: %1\$s. Available currencies: %2\$s", "shills-simple-currency-switcher"),
						$code,
						implode(", ", array_map(function ($c) {
							return is_array($c) ? ($c['code'] ?? 'unknown') : 'invalid';
						}, $all_currencies))
					),
					["status" => 400]
				);
			}
		}

		try {
			// Get default currency symbols using SHSCS_Currency class
			$symbol = SHSCS_Currency::get_default_symbol($code);
			
			setcookie("shscs_currency", $code, [
				"expires" => time() + 30 * DAY_IN_SECONDS,
				"path" => COOKIEPATH ?: "/",
				"domain" => COOKIE_DOMAIN,
				"secure" => is_ssl(),
				"httponly" => false,
				"samesite" => "Lax",
			]);
			// Mark as user manual selection
			setcookie("shscs_user_currency_choice", "1", [
				"expires" => time() + 30 * DAY_IN_SECONDS,
				"path" => COOKIEPATH ?: "/",
				"domain" => COOKIE_DOMAIN,
				"secure" => is_ssl(),
				"httponly" => false,
				"samesite" => "Lax",
			]);


			// Cookie set successfully

			do_action("shscs_currency_switched", $code, $request);

			return new WP_REST_Response(
				[
					"success" => true,
					"currency" => $code,
					"symbol" => $symbol,
					/* translators: %s: target currency code */
					"message" => sprintf(__("Switched to %s", "shills-simple-currency-switcher"), $code)
				],
				200
			);
		} catch (Exception $e) {
			return new WP_REST_Response(
				[
					"success" => false,
					"error" => $e->getMessage()
				],
				500
			);
		}
	}

	/**
	 * Update exchange rates from API
	 */
	public function update_rates(): WP_REST_Response
	{
		$currency = SHSCS_Main::get_instance()->get_component("currency");
		$result = $currency->update_rates_from_api();

		return is_wp_error($result)
			? new WP_REST_Response(
				[
					"success" => false,
					"message" => $result->get_error_message(),
				],
				400
			)
			: new WP_REST_Response(
				[
					"success" => true,
					"updated" => $result,
					"count" => count($result),
				],
				200
			);
	}

	/**
	 * Get theme color for frontend
	 */
	public function get_theme_color(): WP_REST_Response
	{
		$mode = get_option("shscs_theme_mode", "auto");
		$color = "#007cba";

		switch ($mode) {
			case "auto":
				$color = $this->detect_theme_color();
				break;
			case "light":
				$color = "#007cba";
				break;
			case "dark":
				$color = "#2271b1";
				break;
			case "custom":
				$color = get_option("shscs_custom_color", "#007cba");
				break;
		}

		return new WP_REST_Response(
			[
				"mode" => $mode,
				"color" => $color,
				"css" => "--shscs-primary: {$color};",
			],
			200
		);
	}

	/**
	 * Get language-currency mapping
	 */
	public function get_lang_map(): WP_REST_Response
	{
		return new WP_REST_Response(
			[
				"map" => get_option("shscs_lang_currency_map", []),
				"sync" => get_option("shscs_lang_currency_sync", true),
				"allow_override" => get_option("shscs_allow_user_override", false),
			],
			200
		);
	}


	/**
	 * Sync language with currency - Enhanced
	 */
	public function sync_language_currency(WP_REST_Request $request): WP_REST_Response
	{
		$language = sanitize_text_field($request->get_param("language"));
		// Support source param to distinguish auto/manual sync
		$source = sanitize_text_field($request->get_param("source") ?? "auto");
		// Check if auto-sync is enabled in settings
		$sync_enabled = (bool) get_option("shscs_lang_currency_sync", true);

		if (!$sync_enabled) {
			return new WP_REST_Response([
				"success" => false,
				"message" => __("Auto-sync is disabled", "shills-simple-currency-switcher"),
				"synced" => false,
				"reason" => "sync_disabled"
			], 200);
		}


		$map = get_option("shscs_lang_currency_map", []);

		if (!isset($map[$language]) || empty($map[$language])) {
			return new WP_REST_Response(
				[
					"success" => false,
					"message" => __("No mapping", "shills-simple-currency-switcher"),
					"synced" => false,
				],
				200
			);
		}

		$currency = $map[$language];

		// Validate currency is valid and enabled
		if (!SHSCS_Main::is_valid_currency($currency)) {
			// Fallback to base currency
			$base = get_option("shscs_base_currency", "USD");
			if (SHSCS_Main::is_valid_currency($base)) {
				$currency = $base;
			} else {
				return new WP_REST_Response(
					[
						"success" => false,
						"message" => __("Invalid mapped currency", "shills-simple-currency-switcher"),
						"synced" => false,
					],
					200
				);
			}
		}

		// Determine source, decide whether to respect user choice
		$allow_override = (bool) get_option("shscs_allow_user_override", false);
		$current = shscs_get_currency();

		// Skip if auto-sync + allow override + user has manually selected
		if ($source === "auto" && $allow_override) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$user_choice = isset($_COOKIE["shscs_user_currency_choice"]) ? sanitize_text_field(wp_unslash($_COOKIE["shscs_user_currency_choice"])) : null;
			if ($user_choice === "1" && SHSCS_Main::is_valid_currency($current)) {
				return new WP_REST_Response(
					[
						"success" => true,
						"currency" => $current,
						"synced" => false,
						"reason" => "user_override",
					],
					200
				);
			}
		}

		// Set cookie with security params
		$cookie_args = [
			"expires" => time() + 30 * DAY_IN_SECONDS,
			"path" => COOKIEPATH ?: "/",
			"domain" => COOKIE_DOMAIN,
			"secure" => is_ssl(),
			"httponly" => false,
			"samesite" => "Lax",
		];

		setcookie("shscs_currency", $currency, $cookie_args);

		// Update $_COOKIE for code before redirect
		$_COOKIE['shscs_currency'] = $currency;

		// Ensure PHP session is written
		if (function_exists('session_write_close')) {
			session_write_close();
		}

		// Add custom response header for frontend verification
		header("X-SHSCS-Currency: {$currency}");


		// Set user choice flag
		$choice_value = ($source === "user") ? "1" : "0";
		setcookie("shscs_user_currency_choice", $choice_value, $cookie_args);

		// Sync to WooCommerce session if enabled
		if (class_exists("WooCommerce") && WC()->session) {
			WC()->session->set("shscs_currency", $currency);
		}

		// Trigger event for other plugins
		do_action("shscs_language_currency_synced", $language, $currency, $source);

		return new WP_REST_Response(
			[
				"success" => true,
				"currency" => $currency,
				"synced" => true,
				"source" => $source,
			],
			200
		);
	}

	/**
	 * Detect theme color from WordPress theme
	 */
	private function detect_theme_color(): string
	{
		if ($color = get_theme_mod("primary_color")) {
			return sanitize_hex_color($color);
		}

		if (!function_exists("wp_get_global_styles")) {
			return "#007cba";
		}

		$global = wp_get_global_styles();
		if (!empty($global["color"]["palette"]["theme"])) {
			foreach ($global["color"]["palette"]["theme"] as $item) {
				if ($item["slug"] === "primary") {
					return sanitize_hex_color($item["color"]);
				}
			}
		}

		return "#007cba";
	}

	/**
	 * Fine-grained sync endpoint callback
	 */
	public function sync_currency_via_api(WP_REST_Request $request): WP_REST_Response
	{
		// Reuse existing sync_language_currency logic
		return $this->sync_language_currency($request);
	}

}
