<?php
/**
 * Plugin Name: Shills Simple Currency Switcher
 * Description: Simple and efficient currency switcher, supports multi-currency management, real-time exchange rate conversion, WooCommerce forced price switching, WPML/Polylang multilingual integration, front-end widgets
 * Version: 1.0.0
 * Author: shawn.hills
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: shills-simple-currency-switcher
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * 
 * @package ShillsSimpleCurrencySwitcher
 * @author  shawn.hills
 * @version 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined("ABSPATH")) {
      exit();
}

// Define plugin constants
define("SHSCS_VERSION", "1.0.0");
define("SHSCS_PLUGIN_FILE", __FILE__);
define("SHSCS_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("SHSCS_PLUGIN_URL", plugin_dir_url(__FILE__));
define("SHSCS_DIST_URL", SHSCS_PLUGIN_URL . "assets/dist/");
define("SHSCS_DIST_DIR", SHSCS_PLUGIN_DIR . "assets/dist/");
define("SHSCS_INCLUDES_DIR", SHSCS_PLUGIN_DIR . "includes/");
define("SHSCS_LANGUAGES_DIR", SHSCS_PLUGIN_DIR . "languages/");

// Ensure correct cookie scope
if (!defined('COOKIE_DOMAIN')) {
	$shscs_home_host = wp_parse_url(home_url(), PHP_URL_HOST);
    define('COOKIE_DOMAIN', '.' . ($shscs_home_host ?: ''));
}
if (!defined('COOKIEPATH')) {
    define('COOKIEPATH', '/');
}

/**
 * Class SHSCS_Main
 *
 * Main plugin class responsible for initialization, activation hooks,
 * and dependency loading.
 */
class SHSCS_Main
{
      /**
       * Single instance of the class
       *
       * @var SHSCS_Main|null
       */
      private static ?self $instance = null;

      /**
       * Plugin components instances
       *
       * @var array
       */
      private array $components = [];

      /**
       * Get single instance
       *
       * @return SHSCS_Main
       */
      public static function get_instance(): self
      {
            if (null === self::$instance) {
                  self::$instance = new self();
            }
            return self::$instance;
      }

      /**
       * Constructor
       */
      private function __construct()
      {
            $this->init();
      }

      /**
       * Initialize plugin
       *
       * @return void
       */
      private function init(): void
      {
            // Load text domain for internationalization - early on plugins_loaded
            add_action("plugins_loaded", [$this, "load_textdomain"], 1);

            // Register activation/deactivation hooks
            register_activation_hook(SHSCS_PLUGIN_FILE, [$this, "activate"]);
            register_deactivation_hook(SHSCS_PLUGIN_FILE, [
                  $this,
                  "deactivate",
            ]);

            // Initialize plugin components - after textdomain is loaded
            add_action("plugins_loaded", [$this, "load_components"], 10);

            // Initialize REST API
            add_action("rest_api_init", [$this, "init_rest_api"]);

            // Detect multilingual plugins early
            add_action(
                  "plugins_loaded",
                  [$this, "detect_multilingual_plugins"],
                  5,
            );

            // Initialize multilingual sync hooks
            add_action("plugins_loaded", [$this, "init_multilingual_sync"], 15);
      }

      /**
       * Load plugin textdomain
       *
       * @return void
       */
      public function load_textdomain(): void
      {
            // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Used for local/Custom translations outside WP.org
            load_plugin_textdomain(
                  "shills-simple-currency-switcher",
                  false,
                  dirname(plugin_basename(SHSCS_PLUGIN_FILE)) . "/languages/",
            );
      }

      /**
       * Safe translation function
       * Prevents translation loading issues by checking if translation functions are available
       *
       * @param string $text Text to translate
       * @param string $domain Text domain
       * @return string Translated text or original text if translation not available
       */
      public static function safe_translate(string $text, string $domain = "shills-simple-currency-switcher"): string
      {
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralDomain -- Wrapper function accepting dynamic text/domain
            return function_exists("__") ? __($text, $domain) : $text;
      }

      /**
       * Plugin activation hook
       *
       * @return void
       */
      public function activate(): void
      {
            // Set default options
            $this->set_default_options();

            // Create database version marker
            add_option("shscs_db_version", SHSCS_VERSION);

            // Schedule cron for auto rate updates
            if (!wp_next_scheduled("shscs_auto_update_rates")) {
                  wp_schedule_event(time(), "daily", "shscs_auto_update_rates");
            }

            // Flush rewrite rules for REST API
            flush_rewrite_rules();

            /**
             * Fires after plugin activation
             *
             * @since 1.0.0
             */
            do_action("shscs_activated");
      }

      /**
       * Plugin deactivation hook
       *
       * @return void
       */
      public function deactivate(): void
      {
            // Clear scheduled events
            wp_clear_scheduled_hook("shscs_auto_update_rates");

            // Clean up transients
            delete_transient("shscs_rates_cache");

            // Note: We don't delete options to preserve user settings on reactivation

            /**
             * Fires before plugin deactivation completes
             *
             * @since 1.0.0
             */
            do_action("shscs_deactivated");
      }

      /**
       * Set default plugin options
       *
       * @return void
       */
      private function set_default_options(): void
      {
            $defaults = [
                  "shscs_base_currency" => "USD",
                  "shscs_currencies" => [
                        [
                              "code" => "USD",
                              "symbol" => '$',
                              "rate" => 1.0,
                              "position" => "left",
                              "enabled" => true,
                              "order" => 0,
                        ],
                        [
                              "code" => "EUR",
                              "symbol" => "€",
                              "rate" => 0.923,
                              "position" => "right",
                              "enabled" => true,
                              "order" => 1,
                        ],
                        [
                              "code" => "CNY",
                              "symbol" => "¥",
                              "rate" => 7.245,
                              "position" => "left",
                              "enabled" => true,
                              "order" => 2,
                        ],
                  ],
                  "shscs_api_source" => "none",
                  "shscs_auto_update" => "daily",
                  "shscs_theme_mode" => "auto",
                  "shscs_custom_color" => "#007cba",
                  "shscs_last_update" => 0,
                  "shscs_lang_currency_map" => [
                        "zh-hans" => "CNY",
                        "en" => "USD",
                        "de" => "EUR",
                  ],
                  "shscs_lang_currency_sync" => true,
                  "shscs_geo_detection" => false,
                  "shscs_allow_user_override" => false,
            ];

            foreach ($defaults as $option => $value) {
                  if (false === get_option($option)) {
                        add_option($option, $value);
                  }
            }
      }

      /**
       * Autoloader for plugin classes
       *
       * @param string $class Class name to load
       * @return void
       */
      public function autoload(string $class): void
      {
            $prefix = "SHSCS_";

            // Check if class belongs to this plugin
            if (strpos($class, $prefix) !== 0) {
                  return;
            }

            // Convert class name to file path
            // SHSCS_Class_Name -> class-shscs-class-name.php
            $file =
                  strtolower(
                        str_replace(
                              ["SHSCS_", "_"],
                              ["class-shscs-", "-"],
                              $class,
                        ),
                  ) . ".php";

            $path = SHSCS_INCLUDES_DIR . $file;

            if (file_exists($path)) {
                  require_once $path;
            }
      }

      /**
       * Load plugin components
       *
       * @return void
       */
      public function load_components(): void
      {
            // Register autoloader
            spl_autoload_register([$this, "autoload"]);

            // Load public helper functions
            require_once SHSCS_INCLUDES_DIR . "functions.php";

            // Core components (always loaded)
            $this->load_core_components();

            // Conditional components
            $this->load_conditional_components();

            /**
             * Fires after all components are loaded
             *
             * @since 1.0.0
             * @param SHSCS_Main $this Main plugin instance
             */
            do_action("shscs_components_loaded", $this);
      }

      /**
       * Load core components
       *
       * @return void
       */
      private function load_core_components(): void
      {
            // Currency management
            if ($this->load_class("SHSCS_Currency")) {
                  $this->components[
                        "currency"
                  ] = SHSCS_Currency::get_instance();
            }

            // Exchange rate API
            if ($this->load_class("SHSCS_API")) {
                  $this->components["api"] = SHSCS_API::get_instance();
            }

            // REST API endpoints
            if ($this->load_class("SHSCS_REST")) {
                  $this->components["rest"] = SHSCS_REST::get_instance();
            }

            // Asset management (Webpack)
            if ($this->load_class("SHSCS_Assets")) {
                  $this->components["assets"] = SHSCS_Assets::get_instance();
            }

            // Admin settings
            if (is_admin() && $this->load_class("SHSCS_Admin")) {
                  $this->components["admin"] = SHSCS_Admin::get_instance();
            }

            // Frontend display
            if ($this->load_class("SHSCS_Frontend")) {
                  $this->components[
                        "frontend"
                  ] = SHSCS_Frontend::get_instance();
            }

            // Widget
            if ($this->load_class("SHSCS_Widget")) {
                  add_action("widgets_init", function () {
                        register_widget("SHSCS_Widget");
                  });
            }
      }

      /**
       * Load conditional components based on environment
       *
       * @return void
       */
      private function load_conditional_components(): void
      {
            // WooCommerce integration
            if (class_exists("WooCommerce") && $this->load_class("SHSCS_Woo")) {
                  $this->components["woo"] = SHSCS_Woo::get_instance();
            }

            // Multilingual integration (WPML or Polylang)
            if (
                  $this->is_multilingual_active() &&
                  $this->load_class("SHSCS_Multilingual")
            ) {
                  $this->components[
                        "multilingual"
                  ] = SHSCS_Multilingual::get_instance();
            }

            // GeoIP detection (if enabled)
            $geo_enabled = get_option("shscs_geo_detection", false);
            if ($geo_enabled && $this->load_class("SHSCS_Geo")) {
                  $this->components["geo"] = SHSCS_Geo::get_instance();
            }
      }

      /**
       * Helper to load a class file
       *
       * @param string $class Class name
       * @return bool True if loaded successfully
       */
      private function load_class(string $class): bool
      {
            $this->autoload($class);
            return class_exists($class);
      }

      /**
       * Initialize REST API
       *
       * @return void
       */
      public function init_rest_api(): void
      {
            /**
             * Fires when REST API is initialized
             * Allows components to register endpoints
             *
             * @since 1.0.0
             */
            do_action("shscs_rest_api_init");
      }

      /**
       * Detect active multilingual plugins
       *
       * Sets global constants for quick detection
       *
       * @return void
       */
      public function detect_multilingual_plugins(): void
      {
            // Debug: Log what we detect

            
            // First check for Polylang (more specific detection)
            $polylang_detected = false;
            if (defined("POLYLANG_VERSION") || function_exists("pll_current_language")) {

                  $polylang_detected = true;
            }
            
            // Then check for WPML (but be careful about PolyLang defining WPML functions)
            $wpml_detected = false;
            if (defined("ICL_SITEPRESS_VERSION")) {

                  $wpml_detected = true;
            } elseif (function_exists("icl_object_id")) {
                  // Check if this might be PolyLang defining WPML compatibility functions
                  if (!$polylang_detected) {

                        $wpml_detected = true;
                  } else {

                  }
            }
            
            // Set constants
            define("SHSCS_WPML_ACTIVE", $wpml_detected);
            define("SHSCS_POLYLANG_ACTIVE", $polylang_detected);
            
            // Generic multilingual active flag
            define(
                  "SHSCS_MULTILINGUAL_ACTIVE",
                  $wpml_detected || $polylang_detected,
            );
            

      }

      /**
       * Check if multilingual plugin is active
       *
       * @return bool
       */
      public function is_multilingual_active(): bool
      {
            return defined("SHSCS_MULTILINGUAL_ACTIVE") &&
                  SHSCS_MULTILINGUAL_ACTIVE;
      }

      /**
       * Check if WPML is active
       *
       * @return bool
       */
      public function is_wpml_active(): bool
      {
            return defined("SHSCS_WPML_ACTIVE") && SHSCS_WPML_ACTIVE;
      }

      /**
       * Check if Polylang is active
       *
       * @return bool
       */
      public function is_polylang_active(): bool
      {
            return defined("SHSCS_POLYLANG_ACTIVE") && SHSCS_POLYLANG_ACTIVE;
      }

      /**
       * Get component instance
       *
       * @param string $name Component name
       * @return object|null Component instance or null
       */
      public function get_component(string $name): ?object
      {
            return $this->components[$name] ?? null;
      }

      /**
       * Check if WooCommerce is active
       *
       * @return bool
       */
      public static function is_woocommerce_active(): bool
      {
            return class_exists("WooCommerce");
      }

      // ============================================================================
      // Multilingual sync core logic
      // ============================================================================

      /**
       * Initialize multilingual sync hooks
       */
      public function init_multilingual_sync(): void
      {
            // Process language-currency sync before template load
            // Priority 1: earlier than most themes/plugins to ensure state readiness
            add_action('template_redirect', [$this, 'maybe_sync_language_currency'], 1);
            
            // Ensure latest config is injected for frontend
            add_filter('wp_localize_script', [$this, 'inject_latest_currency_config'], 10, 4);
      }

      /**
       * Sync currency with language on page load
       */
      public function maybe_sync_language_currency(): void
      {
            // Pre-checks
            if (
                is_admin() || 
                wp_doing_ajax() || 
                wp_doing_cron() ||
                !get_option('shscs_lang_currency_sync', true) ||
                !defined('SHSCS_MULTILINGUAL_ACTIVE') ||
                !SHSCS_MULTILINGUAL_ACTIVE
            ) {
                return;
            }

            // Detect current language (Polylang/WPML compatible)
            $current_lang = $this->get_current_language();
            if (!$current_lang) {
                return;
            }

            // Check mapping table
            $lang_map = get_option('shscs_lang_currency_map', []);
            $expected_currency = $lang_map[$current_lang] ?? null;
            
            if (!$expected_currency || !self::is_valid_currency($expected_currency)) {
                return;
            }

            // Check if update needed
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $cookie_currency = isset($_COOKIE['shscs_currency']) ? sanitize_text_field(wp_unslash($_COOKIE['shscs_currency'])) : null;
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $user_choice = isset($_COOKIE['shscs_user_currency_choice']) ? sanitize_text_field(wp_unslash($_COOKIE['shscs_user_currency_choice'])) : '0';

            // URL parameter takes priority - use filter_input for security
            $url_currency = filter_input(INPUT_GET, 'shscs_currency', FILTER_SANITIZE_SPECIAL_CHARS);

            // Language priority mode: force sync if mapping exists and user hasn't manually chosen
            $should_sync = (
                $cookie_currency !== $expected_currency &&
                $user_choice !== '1'
            );

            // URL parameter takes priority
            if ($url_currency && self::is_valid_currency($url_currency)) {
                $this->set_currency_cookie($url_currency);
                $_COOKIE['shscs_currency'] = $url_currency;
                return;
            }

            // Sync needed: set cookie + optional redirect
            if ($should_sync) {
                $this->set_currency_cookie($expected_currency);
                
                // Update $_COOKIE for current request
                $_COOKIE['shscs_currency'] = $expected_currency;
                
                // Add sync param and redirect if not present
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Internal sync param, no nonce needed
                if (!isset($_GET['_shscs_sync'])) {
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only used as base for add_query_arg
                    $redirect_url = add_query_arg([
                        '_shscs_sync' => time(),
                        '_shscs_curr' => $expected_currency,
                    ], isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '');
                    
                    wp_safe_redirect($redirect_url);
                    exit;
                }
                
                // Sync WooCommerce session if enabled
                $this->sync_wc_session($expected_currency);
                

            }
      }

      /**
       * Get current language (Polylang/WPML compatible)
       */
      private function get_current_language(): ?string
      {
            // Polylang
            if (defined('SHSCS_POLYLANG_ACTIVE') && SHSCS_POLYLANG_ACTIVE) {
                if (function_exists('pll_current_language')) {
                    return pll_current_language('slug');
                }
                // Fallback: read from query param or cookie
                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- No nonce needed for language detection
                if (isset($_GET['lang'])) {
                    return sanitize_text_field(wp_unslash($_GET['lang']));
                }
                // phpcs:enable WordPress.Security.NonceVerification.Recommended
                if (isset($_COOKIE['pll_language'])) {
                    return sanitize_text_field(wp_unslash($_COOKIE['pll_language']));
                }
            }
            
            // WPML
            if (defined('SHSCS_WPML_ACTIVE') && SHSCS_WPML_ACTIVE) {
                if (function_exists('icl_object_id')) {
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML core filter
                    return apply_filters('wpml_current_language', null);
                }
                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- No nonce needed for language detection
                if (isset($_GET['lang'])) {
                    return sanitize_text_field(wp_unslash($_GET['lang']));
                }
                // phpcs:enable WordPress.Security.NonceVerification.Recommended
            }
            
            // Fallback: parse from URL path
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only reading URL path, not using unsanitized data
            $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            $path = wp_parse_url($request_uri, PHP_URL_PATH);
            if (preg_match('#^/([a-z]{2}(?:-[A-Z]{2})?)/#', (string) $path, $matches)) {
                return strtolower($matches[1]);
            }
            
            return null;
      }

      /**
       * Set currency cookie with proper args
       */
      private function set_currency_cookie(string $currency): void
      {
            $cookie_args = [
                'expires' => time() + 30 * DAY_IN_SECONDS,
                'path' => defined('COOKIEPATH') ? COOKIEPATH : '/',
                'domain' => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
                'secure' => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            ];
            
            setcookie('shscs_currency', $currency, $cookie_args);
      }

      /**
       * Sync currency to WooCommerce session
       */
      private function sync_wc_session(string $currency): void
      {
            if (!class_exists('WooCommerce') || !WC()->session) {
                return;
            }
            
            WC()->session->set('shscs_currency', $currency);
            
            // If multi-currency is enabled, also update WC currency
            if (class_exists('WOOCOMMERCE_MULTI_CURRENCY')) {
                WC()->session->set('woocommerce_currency', $currency);
            }
      }

      /**
       * Inject latest currency into localized script config
       */
      public function inject_latest_currency_config($translation, $text_domain, $object_name, $handle): array
      {
            // Only process our config object
            if ($object_name !== 'shscsConfig') {
                return $translation;
            }
            
            // Read latest currency from URL parameter or cookie
            $url_currency = filter_input(INPUT_GET, 'shscs_currency', FILTER_SANITIZE_SPECIAL_CHARS);
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $cookie_currency = isset($_COOKIE['shscs_currency']) ? sanitize_text_field(wp_unslash($_COOKIE['shscs_currency'])) : null;
            $latest_currency = $url_currency ?: $cookie_currency;

            if ($latest_currency && self::is_valid_currency($latest_currency)) {
                // Update root level currentCurrency
                $translation['currentCurrency'] = sanitize_text_field($latest_currency);
                
                // Also update multilingual nested object if present
                if (isset($translation['multilingual']) && is_array($translation['multilingual'])) {
                    $translation['multilingual']['currentCurrency'] = $translation['currentCurrency'];
                }
            }
            
            return $translation;
      }

      // ============================================================================
      // get_current_currency with real-time value priority
      // ============================================================================

      /**
       * Get current currency code
       *
       * Priority: URL param > Cookie > Language default (if sync enabled) > First enabled currency > Base currency
       *
       * @return string Currency code
       */
      public static function get_current_currency(): string
      {
            // Highest priority: URL parameter (for forced refresh scenarios)
            $url_currency = filter_input(INPUT_GET, 'shscs_currency', FILTER_SANITIZE_SPECIAL_CHARS);
            if ($url_currency && self::is_valid_currency($url_currency)) {
                return $url_currency;
            }

            // Check cookie first (user choice or previous sync)
            if (isset($_COOKIE["shscs_currency"]) && !empty($_COOKIE["shscs_currency"])) {
                $cookie_currency = sanitize_text_field(wp_unslash($_COOKIE["shscs_currency"]));
                if (self::is_valid_currency($cookie_currency)) {
                    return $cookie_currency;
                }
            }

            // Check language default if multilingual + sync enabled
            if (defined("SHSCS_MULTILINGUAL_ACTIVE") && SHSCS_MULTILINGUAL_ACTIVE) {
                $sync_enabled = get_option("shscs_lang_currency_sync", true);
                if ($sync_enabled) {
                    $lang_currency = self::get_language_default_currency();
                    if ($lang_currency) {
                        return $lang_currency;
                    }
                }
            }

            // Fallback to first enabled currency (sorted by order)
            $currencies = get_option("shscs_currencies", []);
            
            if (is_array($currencies) && !empty($currencies)) {
                $sorted = $currencies;
                usort($sorted, function($a, $b) {
                    $order_a = isset($a['order']) ? (int) $a['order'] : PHP_INT_MAX;
                    $order_b = isset($b['order']) ? (int) $b['order'] : PHP_INT_MAX;
                    return $order_a <=> $order_b;
                });
                
                // Return first enabled currency
                foreach ($sorted as $currency) {
                    if (
                        is_array($currency) && 
                        !empty($currency['code']) && 
                        !empty($currency['enabled'])
                    ) {
                        return sanitize_text_field($currency['code']);
                    }
                }
            }
            
            // Final fallback: base currency
            return get_option("shscs_base_currency", "USD");
      }

      // ============================================================================
      // Keep existing methods unchanged (only minor comment adjustments)
      // ============================================================================

      /**
       * Get default currency for current language
       *
       * @return string|null Currency code or null
       */
      public static function get_language_default_currency(): ?string
      {
            $lang_map = get_option("shscs_lang_currency_map", []);

            $current_lang = "";

            if (
                  defined("SHSCS_WPML_ACTIVE") &&
                  SHSCS_WPML_ACTIVE &&
                  function_exists("icl_object_id")
            ) {
                  // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML core filter
                  $current_lang = apply_filters("wpml_current_language", null);
            } elseif (
                  defined("SHSCS_POLYLANG_ACTIVE") &&
                  SHSCS_POLYLANG_ACTIVE &&
                  function_exists("pll_current_language")
            ) {
                  $current_lang = pll_current_language("slug");
            }

            if (!empty($current_lang) && isset($lang_map[$current_lang])) {
                  return $lang_map[$current_lang];
            }

            return null;
      }

      /**
       * Validate if currency code is enabled
       *
       * @param string $code Currency code
       * @return bool
       */
      public static function is_valid_currency(string $code): bool
      {
            $currencies = get_option("shscs_currencies", []);

            // error_log("SHSCS VALIDATE: Checking if currency '{$code}' is valid");

            // Ensure $currencies is an array
            if (!is_array($currencies)) {
                  // error_log("SHSCS VALIDATE ERROR: Currencies option is not an array");
                  return false;
            }

            // error_log("SHSCS VALIDATE: Found " . count($currencies) . " currency entries");

            $plugin_base = get_option("shscs_base_currency", "USD");
            // error_log("SHSCS VALIDATE: Plugin base currency: {$plugin_base}");

            // Always consider plugin base currency as valid
            if ($code === $plugin_base) {
                  // error_log("SHSCS VALIDATE: '{$code}' is plugin base currency, considering as valid");
                  return true;
            }

            $found_but_disabled = false;
            
            foreach ($currencies as $index => $currency) {
                  // Ensure $currency is an array
                  if (!is_array($currency)) {
                        // error_log("SHSCS VALIDATE WARNING: Currency entry at index {$index} is not an array");
                        continue;
                  }
                  
                  $currency_code = $currency["code"] ?? '';
                  $enabled = $currency["enabled"] ?? false;
                  
                  if ($currency_code === $code) {
                        if (!empty($enabled)) {
                              // error_log("SHSCS VALIDATE: Found '{$code}' at index {$index}, enabled: YES");
                              return true;
                        } else {
                              // error_log("SHSCS VALIDATE: Found '{$code}' at index {$index}, but DISABLED");
                              $found_but_disabled = true;
                        }
                  }
            }

            if ($found_but_disabled) {
                  // error_log("SHSCS VALIDATE: Currency '{$code}' found but disabled");
            } else {
                  // error_log("SHSCS VALIDATE: Currency '{$code}' not found in currency list");
                  // error_log("SHSCS VALIDATE: Available currencies: " . json_encode(array_map(function($c) { 
                  //       return is_array($c) ? ($c['code'] ?? 'unknown') : 'invalid'; 
                  // }, $currencies)));
            }

            return false;
      }

      /**
       * Convert amount between currencies
       *
       * @param float  $amount      Amount to convert
       * @param string $from        From currency code
       * @param string $to          To currency code
       * @param bool   $format      Whether to format the result
       * @return float|string Converted amount or formatted string
       */
      public static function convert_amount(
            float $amount,
            string $from,
            string $to,
            bool $format = false,
      ) {
            if ($from === $to) {
                  // error_log("SHSCS CONVERT: Same currency ({$from} -> {$to}), returning original: {$amount}");
                  return $format ? self::format_price($amount, $to) : $amount;
            }

            $rates = self::get_exchange_rates();
            $base_currency = get_option("shscs_base_currency", "USD");

            // If either currency is not in rates, cannot convert
            if (empty($rates[$from]) || empty($rates[$to])) {
                  // error_log("SHSCS CONVERT ERROR: Missing rates. From '{$from}': " . (isset($rates[$from]) ? $rates[$from] : 'NOT FOUND') . ", To '{$to}': " . (isset($rates[$to]) ? $rates[$to] : 'NOT FOUND'));
                  // error_log("SHSCS CONVERT: All available rates: " . json_encode(array_keys($rates)));
                  return $format ? self::format_price($amount, $to) : $amount;
            }

         

            // If 'from' is the base currency, conversion is direct
            // Example: 90 CNY (base) * 0.15 (USD rate) = 13.5 USD
            if ($from === $base_currency) {
                  $converted = $amount * $rates[$to];
                  // error_log("SHSCS CONVERT: From is base currency. {$amount} * {$rates[$to]} = {$converted}");
                  return $format ? self::format_price($converted, $to) : $converted;
            }
            
            // If 'to' is the base currency, conversion is direct (inverse)
            if ($to === $base_currency) {
                  $rate_from = $rates[$from];
                  $converted = $rate_from > 0 ? $amount / $rate_from : $amount;
                  // error_log("SHSCS CONVERT: To is base currency. {$amount} / {$rate_from} = {$converted}");
                  return $format ? self::format_price($converted, $to) : $converted;
            }

            // General case: Convert from -> base -> to
            $rate_from = $rates[$from];
            $rate_to = $rates[$to];
            
            // Avoid division by zero
            if ($rate_from <= 0) {
                  // error_log("SHSCS CONVERT ERROR: Rate from is zero or negative: {$rate_from}");
                  return $format ? self::format_price($amount, $to) : $amount;
            }
            
            $converted = ($amount / $rate_from) * $rate_to;
            // error_log("SHSCS CONVERT: General conversion. ({$amount} / {$rate_from}) * {$rate_to} = {$converted}");

            return $format ? self::format_price($converted, $to) : $converted;
      }

      /**
       * Get all exchange rates
       *
       * @return array Currency code => rate
       */
      public static function get_exchange_rates(): array
      {
            $currencies = get_option("shscs_currencies", []);
            $rates = [];

        
            // Ensure $currencies is an array
            if (!is_array($currencies)) {
                  // error_log("SHSCS RATES ERROR: Currencies option is not an array");
                  return $rates;
            }


            $base_currency = get_option("shscs_base_currency", "USD");
           
            foreach ($currencies as $index => $currency) {
                  // Ensure $currency is an array
                  if (!is_array($currency)) {
                      
                        continue;
                  }
                  $code = $currency["code"] ?? '';
                  $rate = $currency["rate"] ?? 1.0;
                  $enabled = $currency["enabled"] ?? false;
                  
                  if (!empty($code)) {
                        $rates[$code] = (float) $rate;
                       
                  } else {
                        // error_log("SHSCS RATES WARNING: Currency at index {$index} has no code");
                  }
            }

            
            // Ensure base currency is always in rates with rate 1.0
            if (!isset($rates[$base_currency])) {
                  $rates[$base_currency] = 1.0;
                  // error_log("SHSCS RATES: Added base currency {$base_currency} with rate 1.0");
            }

            return $rates;
      }

      /**
       * Format price with currency symbol
       *
       * @param float  $amount   Amount
       * @param string $currency Currency code
       * @return string Formatted price
       */
      public static function format_price(
            float $amount,
            string $currency,
      ): string {
            $currencies = get_option("shscs_currencies", []);
            $currency_data = null;

            // Ensure $currencies is an array
            if (is_array($currencies)) {
                  foreach ($currencies as $c) {
                        // Ensure $c is an array
                        if (!is_array($c)) {
                              continue;
                        }
                        if (($c["code"] ?? '') === $currency) {
                              $currency_data = $c;
                              break;
                        }
                  }
            }

            // Default currency symbols - use SHSCS_Currency class
            $symbol = SHSCS_Currency::get_default_symbol($currency) ?? $currency_data["symbol"] ?? $currency;
            $position = $currency_data["position"] ?? "left";
            $decimals = $currency_data["decimals"] ?? 2;

            $formatted = number_format_i18n($amount, $decimals);

            if ($position === "right" || $position === "right_space") {
                  $space = $position === "right_space" ? " " : "";
                  return $formatted . $space . $symbol;
            } else {
                  $space = $position === "left_space" ? " " : "";
                  return $symbol . $space . $formatted;
            }
      }
}

// Initialize plugin
SHSCS_Main::get_instance();