<?php
/**
 * Asset Management (Webpack)
 *
 * @package ShillsSimpleCurrencySwitcher
 * @since   1.0.0
 */

declare(strict_types=1);

if (!defined("ABSPATH")) {
      exit();
}



/**
 * Class SHSCS_Assets
 */
class SHSCS_Assets
{
      private static ?self $instance = null;
      private array $loaded = [];

      public static function get_instance(): self
      {
            if (null === self::$instance) {
                  self::$instance = new self();
            }
            return self::$instance;
      }

      private function __construct()
      {
            add_action("admin_enqueue_scripts", [$this, "load_admin_assets"]);
            add_action("wp_enqueue_scripts", [$this, "load_frontend_assets"]);

            // Higher priority to load before theme/plugins
            add_action("wp_enqueue_scripts", [$this, "maybe_load_multilingual"], 20);

            // URL parameter handling: ensure currency param persists in internal links
            add_filter("home_url", [$this, "add_currency_to_url"], 10, 2);
            add_filter("post_link", [$this, "add_currency_to_permalink"], 10, 2);
            add_filter("page_link", [$this, "add_currency_to_permalink"], 10, 2);
            add_filter("term_link", [$this, "add_currency_to_term_link"], 10, 3);
            add_filter("woocommerce_get_endpoint_url", [$this, "add_currency_to_wc_endpoint"], 10, 2);
      }

      /**
       * Load admin assets (single entry)
       */
      public function load_admin_assets(string $hook): void
      {
            if ($hook !== "settings_page_shscs_settings") {
                  return;
            }

            // Single entry: load admin.js + admin.css
            $this->enqueue_asset("admin", "shscs-admin", [
                  "shscsSettings" => $this->get_admin_data(),
            ]);
      }

      /**
       * Load frontend assets (single entry)
       */
      public function load_frontend_assets(): void
      {
            // Single entry: load frontend.js + frontend.css
            $this->enqueue_asset("frontend", "shscs-frontend", [
                  "shscsSettings" => $this->get_frontend_config(),
            ]);

            $this->inject_theme_css();
      }

      /**
       * Load multilingual bridge on demand (single entry)
       */
      public function maybe_load_multilingual(): void
      {
            if (
                  !defined("SHSCS_MULTILINGUAL_ACTIVE") ||
                  !SHSCS_MULTILINGUAL_ACTIVE ||
                  !get_option("shscs_lang_currency_sync", true)
            ) {
                  return;
            }

            $detected_plugin = $this->detect_active_multilingual_plugin();

            if ($detected_plugin) {
                  // Single entry: load multilingual.js + multilingual.css
                  $this->enqueue_asset("multilingual", "shscs-multilingual", [
                        "shscsConfig" => [
                              'multilingual' => $this->get_multilingual_config($detected_plugin),
                        ],
                  ], ['shscs-frontend']);
            }
      }

      /**
       * Detect currently active multilingual plugin
       */
      private function detect_active_multilingual_plugin(): ?string
      {
            if (defined("SHSCS_POLYLANG_ACTIVE") && SHSCS_POLYLANG_ACTIVE) {
                  if (function_exists("pll_current_language") || defined("POLYLANG_VERSION")) {
                        return "polylang";
                  }
            }

            if (defined("SHSCS_WPML_ACTIVE") && SHSCS_WPML_ACTIVE) {
                  if (defined("ICL_SITEPRESS_VERSION") || function_exists("icl_object_id")) {
                        return "wpml";
                  }
            }

            return null;
      }

      /**
       * Load assets (single entry adapter + filename fix)
       */
    
      private function enqueue_asset(
            string $entry,
            string $handle,
            array $localize,
            array $deps = []
      ): void {
            $asset = $this->get_asset_file($entry);

            // Merge dependencies
            $dependencies = $asset && !empty($asset["dependencies"])
                  ? array_unique(array_merge($asset["dependencies"], $deps))
                  : array_unique(array_merge(["wp-element", "wp-api-fetch", "wp-i18n"], $deps));

            $version = $asset["version"] ?? SHSCS_VERSION;

            // Ensure filename matches Webpack output
            $js_file = SHSCS_DIST_DIR . "{$entry}.js";
            $js_url = SHSCS_DIST_URL . "{$entry}.js";
            $css_file = SHSCS_DIST_DIR . "{$entry}.css";
            $css_url = SHSCS_DIST_URL . "{$entry}.css";

            // Check if file exists
			if (!file_exists($js_file)) {
				return;
			}

            // Load script
            wp_enqueue_script($handle, $js_url, $dependencies, $version, true);

            // Load stylesheet (optional)
            if (file_exists($css_file)) {
                  wp_enqueue_style("{$handle}-style", $css_url, [], $version);
            }

            // Pass config to JS
            foreach ($localize as $obj => $data) {
                  wp_localize_script($handle, $obj, $data);
            }

            $this->loaded[] = $entry;
      }

      /**
       * Add currency parameter to home URL
       *
       * @param string $url  The home URL.
       * @param string $path The path (optional).
       * @return string URL with currency parameter if present in current request.
       */
      public function add_currency_to_url(string $url, string $path): string {
            // Skip REST API URLs to avoid breaking REST routes
            if (str_contains($url, '/wp-json')) {
                  return $url;
            }
            return $this->append_currency_param( $url );
      }

      /**
       * Add currency parameter to post permalink
       *
       * @param string  $permalink The post permalink.
       * @param WP_Post $post      The post object.
       * @return string URL with currency parameter.
       */
      public function add_currency_to_permalink(string $permalink, $post): string {
            return $this->append_currency_param( $permalink );
      }

      /**
       * Add currency parameter to term link
       *
       * @param string $termlink Term link URL.
       * @param object $term     Term object.
       * @param string $taxonomy Taxonomy slug.
       * @return string URL with currency parameter.
       */
      public function add_currency_to_term_link(string $termlink, $term, string $taxonomy): string {
            return $this->append_currency_param( $termlink );
      }

      /**
       * Add currency parameter to WooCommerce endpoint URL
       *
       * @param string $url      Endpoint URL.
       * @param string $endpoint Endpoint slug.
       * @return string URL with currency parameter.
       */
      public function add_currency_to_wc_endpoint(string $url, string $endpoint): string {
            return $this->append_currency_param( $url );
      }

      /**
       * Append currency parameter to URL if present in current request
       *
       * @param string $url The URL to modify.
       * @return string Modified URL with currency parameter.
       */
      private function append_currency_param(string $url): string {
            $currency = filter_input(INPUT_GET, 'shscs_currency', FILTER_SANITIZE_SPECIAL_CHARS);

            if (! $currency || ! SHSCS_Main::is_valid_currency($currency)) {
                  return $url;
            }

            return add_query_arg('shscs_currency', $currency, $url);
      }

      /**
       * Read .asset.php dependency file
       */
      private function get_asset_file(string $entry): ?array
      {
            $dist_dir = trailingslashit(SHSCS_DIST_DIR);
            $asset_file = $dist_dir . "{$entry}.asset.php";

			if (!file_exists($asset_file)) {
				return null;
			}

            $asset = require $asset_file;

			if (!is_array($asset) || !isset($asset['dependencies'], $asset['version'])) {
				return null;
			}

            return $asset;
      }

      /**
       * Inject theme CSS variables
       */
      private function inject_theme_css(): void
      {
            $mode = get_option("shscs_theme_mode", "auto");
            $color = match ($mode) {
                  "light" => "#007cba",
                  "dark" => "#2271b1",
                  "custom" => get_option("shscs_custom_color", "#007cba"),
                  default => get_theme_mod("primary_color") ?: "#007cba",
            };

            $css = ":root{--shscs-primary:{$color};--shscs-primary-hover:" .
                  $this->darken($color, -20) . ";}";

            wp_register_style("shscs-theme", false);
            wp_enqueue_style("shscs-theme");
            wp_add_inline_style("shscs-theme", $css);
      }

      /**
       * Get admin config data
       */
      private function get_admin_data(): array
      {
            $c = SHSCS_Main::get_instance()->get_component("currency");
            $a = SHSCS_Main::get_instance()->get_component("api");

            $currency_codes = $this->get_default_iso_currencies();
            $currency_symbols = $this->get_default_currency_symbols();

            return [
                  "restUrl" => rest_url("shscs/v1/"),
                  "nonce" => wp_create_nonce("wp_rest"),
                  "strings" => [
                        "delete" => __("Delete", "shills-simple-currency-switcher"),
                        "deleteConfirm" => __("Delete this currency?", "shills-simple-currency-switcher"),
                        "saveSuccess" => __("Settings saved successfully.", "shills-simple-currency-switcher"),
                        "saveError" => __("Failed to save settings.", "shills-simple-currency-switcher"),
                        "add" => __("Add", "shills-simple-currency-switcher"),
                        "updateRates" => __("Update Exchange Rates", "shills-simple-currency-switcher"),
                        "updating" => __("Updating Exchange Rates...", "shills-simple-currency-switcher"),
                        "updateSuccess" => __("Exchange rates updated successfully.", "shills-simple-currency-switcher"),
                  ],
                  "currencies" => $c ? $c->get_currencies() : [],
                  "currencyCodes" => $currency_codes,
                  "currencySymbols" => $currency_symbols,
                  "baseCurrency" => get_option("shscs_base_currency", "USD"),
                  "apiSource" => get_option("shscs_api_source", "exchangerate-api"),
                  "lastUpdate" => $a ? $a->get_last_update() : 0,
                  "multilingual" => [
                        "active" => defined("SHSCS_MULTILINGUAL_ACTIVE") && SHSCS_MULTILINGUAL_ACTIVE,
                        "wpml" => defined("SHSCS_WPML_ACTIVE") && SHSCS_WPML_ACTIVE,
                        "polylang" => defined("SHSCS_POLYLANG_ACTIVE") && SHSCS_POLYLANG_ACTIVE,
                        "langMap" => get_option("shscs_lang_currency_map", []),
                  ],
            ];
      }

      /**
       * Get frontend config with real-time currency
       */
    private function get_frontend_config(): array
    {
        // Priority: URL param > Cookie > regular logic
        $url_currency = filter_input(INPUT_GET, 'shscs_currency', FILTER_SANITIZE_SPECIAL_CHARS);
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $cookie_currency = isset($_COOKIE['shscs_currency']) ? sanitize_text_field(wp_unslash($_COOKIE['shscs_currency'])) : null;

        $current_currency = null;

        if ($url_currency && SHSCS_Main::is_valid_currency($url_currency)) {
            $current_currency = $url_currency;
        } elseif ($cookie_currency && SHSCS_Main::is_valid_currency($cookie_currency)) {
            $current_currency = $cookie_currency;
        } else {
            $current_currency = shscs_get_currency();
        }
        
        return [
            // API configuration
            'restUrl' => rest_url('shscs/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            
            // Currency configuration (use real-time values)
            'currentCurrency' => $current_currency,
            'baseCurrency' => get_option('shscs_base_currency', 'USD'),
            'currencySymbols' => $this->get_default_currency_symbols(),
            'currencies' => SHSCS_Main::get_instance()->get_component('currency')?->get_currencies() ?? [],
            
            // Multilingual configuration (nested structure)
            'multilingual' => [
                'active' => defined('SHSCS_MULTILINGUAL_ACTIVE') && SHSCS_MULTILINGUAL_ACTIVE,
                'wpml' => defined('SHSCS_WPML_ACTIVE') && SHSCS_WPML_ACTIVE,
                'polylang' => defined('SHSCS_POLYLANG_ACTIVE') && SHSCS_POLYLANG_ACTIVE,
                'langCurrencySync' => (bool) get_option('shscs_lang_currency_sync', true),
                'allowOverride' => (bool) get_option('shscs_allow_user_override', false),
                'langCurrencyMap' => get_option('shscs_lang_currency_map', []),
                // Also inject current language (backend detection, more reliable)
                'currentLang' => $this->get_current_language_from_backend(),
            ],
            
            // Other configurations
            'geoDetection' => get_option('shscs_geo_detection', false),
            'woocommerce' => [
                'active' => class_exists('WooCommerce'),
                'ajaxUrl' => class_exists('WooCommerce') ? WC_AJAX::get_endpoint('%%endpoint%%') : '',
            ],
            'syncConfig' => [
                'SYNC_TIMEOUT' => 300,
                'USER_CHOICE_COOKIE' => 'shscs_user_currency_choice',
                'USER_CHOICE_DURATION' => 30,
            ],
        ];
    }
     
     /**
     * Get current language from backend (Polylang/WPML)
     */
    private function get_current_language_from_backend(): string
    {
        if (defined('SHSCS_POLYLANG_ACTIVE') && SHSCS_POLYLANG_ACTIVE && function_exists('pll_current_language')) {
            return pll_current_language('slug') ?: 'en';
        }
        if (defined('SHSCS_WPML_ACTIVE') && SHSCS_WPML_ACTIVE && function_exists('icl_object_id')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML core filter
            return apply_filters('wpml_current_language', null) ?: 'en';
        }
        return 'en';
    }
    

      /**
       * Get multilingual config data
       */
      private function get_multilingual_config(string $plugin): array
      {
            $lang = "";
            if ($plugin === "wpml" && function_exists("icl_object_id")) {
                  // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML core filter
                  $lang = apply_filters("wpml_current_language", null);
            } elseif ($plugin === "polylang" && function_exists("pll_current_language")) {
                  $lang = pll_current_language("slug");
            }


            $config = [
                  "detectedPlugin" => $plugin,  // Key field
                  "currentLang" => $lang ?: $this->get_fallback_language(),
                  "langCurrencyMap" => get_option("shscs_lang_currency_map", []),
                  "syncEnabled" => (bool) get_option("shscs_lang_currency_sync", true),
                  "langCurrencySync" => (bool) get_option("shscs_lang_currency_sync", true),
                  "allowOverride" => (bool) get_option("shscs_allow_user_override", false),
                  "baseCurrency" => get_option("shscs_base_currency", "USD"),
                  "pluginVersion" => $this->get_plugin_version($plugin),
                  "events" => [
                        "LANGUAGE_CHANGED" => "shscs:languageChanged",
                        "CURRENCY_CHANGED" => "shscs:currencyChanged",
                        "LANGUAGE_CURRENCY_SYNCED" => "shscs:languageCurrencySynced",
                  ],
            ];



            return $config;
      }

      /**
       * Get fallback language code
       */
      private function get_fallback_language(): string
      {
            $html_lang = get_locale();
            if ($html_lang) {
                  return explode("_", $html_lang)[0];
            }
            return "en";
      }

      /**
       * Get plugin version
       */
      private function get_plugin_version(string $plugin): ?string
      {
            if ($plugin === "wpml" && defined("ICL_SITEPRESS_VERSION")) {
                  return ICL_SITEPRESS_VERSION;
            }
            if ($plugin === "polylang" && defined("POLYLANG_VERSION")) {
                  return POLYLANG_VERSION;
            }
            return null;
      }

      /**
       * Darken color utility
       */
      private function darken(string $hex, int $steps): string
      {
            $hex = ltrim($hex, "#");
            $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + $steps));
            $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + $steps));
            $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + $steps));
            return sprintf("#%02x%02x%02x", $r, $g, $b);
      }

      private function get_default_iso_currencies(): array
      {
            return [
                  "USD" => "US Dollar",
                  "EUR" => "Euro",
                  "GBP" => "British Pound",
                  "JPY" => "Japanese Yen",
                  "CNY" => "Chinese Yuan",
                  "AUD" => "Australian Dollar",
                  "CAD" => "Canadian Dollar",
                  "CHF" => "Swiss Franc",
                  "HKD" => "Hong Kong Dollar",
                  "SGD" => "Singapore Dollar",
                  "MXN" => "Mexican Peso",
                  "BRL" => "Brazilian Real",
                  "ARS" => "Argentine Peso",
                  "CLP" => "Chilean Peso",
                  "COP" => "Colombian Peso",
                  "PEN" => "Peruvian Sol",
                  "UYU" => "Uruguayan Peso",
                  "PYG" => "Paraguayan Guarani",
                  "BOB" => "Bolivian Boliviano",
                  "VES" => "Venezuelan Bolívar",
                  "KRW" => "South Korean Won",
                  "INR" => "Indian Rupee",
                  "RUB" => "Russian Ruble",
                  "TRY" => "Turkish Lira",
                  "ZAR" => "South African Rand",
                  "NZD" => "New Zealand Dollar",
                  "SEK" => "Swedish Krona",
                  "NOK" => "Norwegian Krone",
                  "DKK" => "Danish Krone",
                  "PLN" => "Polish Złoty",
                  "CZK" => "Czech Koruna",
                  "HUF" => "Hungarian Forint",
                  "RON" => "Romanian Leu",
                  "BGN" => "Bulgarian Lev",
                  "HRK" => "Croatian Kuna",
                  "ILS" => "Israeli Shekel",
                  "AED" => "UAE Dirham",
                  "SAR" => "Saudi Riyal",
                  "QAR" => "Qatari Riyal",
                  "THB" => "Thai Baht",
                  "MYR" => "Malaysian Ringgit",
                  "IDR" => "Indonesian Rupiah",
                  "PHP" => "Philippine Peso",
                  "VND" => "Vietnamese Đồng",
            ];
      }

      private function get_default_currency_symbols(): array
      {
            return SHSCS_Currency::get_default_symbols();
      }
}