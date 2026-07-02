<?php
/**
 * WPML/Polylang Integration
 *
 * @package ShillsSimpleCurrencySwitcher
 * @since   1.0.0
 */

declare(strict_types=1);

if (!defined("ABSPATH")) {
      exit();
}

/**
 * Class SHSCS_Multilingual
 */
class SHSCS_Multilingual
{
      private static ?self $instance = null;

      public static function get_instance(): self
      {
            if (null === self::$instance) {
                  self::$instance = new self();
            }
            return self::$instance;
      }

      private function __construct()
      {
            // add_action("init", [$this, "init_hooks"]);
            // Use earlier hook to ensure cookie is set before output
            add_action("plugins_loaded", [$this, "init_hooks"], 5);

            // Integrate with WP Rocket cache
            add_action("rocket_cache_reject_uri", [$this, "exclude_currency_pages_from_cache"]);

      }

      public function init_hooks(): void
      {
            if (!get_option("shscs_lang_currency_sync", true)) {
                  return;
            }

            // WPML hooks
            if (defined("SHSCS_WPML_ACTIVE") && SHSCS_WPML_ACTIVE) {
                  add_action(
                        "wpml_language_has_switched",
                        [$this, "wpml_language_switched"],
                        10,
                        2,
                  );
                  add_filter("wpml_ls_html", [
                        $this,
                        "add_currency_to_language_switcher",
                  ]);
            }

            // Polylang hooks
            if (defined("SHSCS_POLYLANG_ACTIVE") && SHSCS_POLYLANG_ACTIVE) {
                  add_action("pll_language_defined", [
                        $this,
                        "polylang_language_defined",
                  ]);
                  add_filter(
                        "pll_the_languages",
                        [$this, "add_currency_to_polylang_switcher"],
                        10,
                        2,
                  );
            }

            // Common: Set initial currency based on language
            // add_action("init", [$this, "maybe_set_initial_currency"], 20);

            //change to plugins_loaded with priority 5 to run earlier 
            add_action("plugins_loaded", [$this, "maybe_set_initial_currency"], 5);

            // Add REST API endpoint for frontend to call
            add_action("rest_api_init", [$this, "register_sync_endpoint"]);

      }

      /**
       * Exclude currency-related pages from cache (WP Rocket compatible)
       *
       * Note: With URL parameter approach, each currency has its own cached version.
       * This exclusion is kept as a safety measure for cookie-based fallback.
       *
       * @param array $uris Array of URIs to exclude from cache.
       * @return array Modified array of URIs.
       */
      public function exclude_currency_pages_from_cache(array $uris): array {
            $currency = filter_input(INPUT_GET, 'shscs_currency', FILTER_SANITIZE_SPECIAL_CHARS);
            if ($currency) {
                  $uris[] = '.*';
            }
            return $uris;
      }


      public function wpml_language_switched(
            string $lang,
            string $cookie_lang,
      ): void {
            $this->sync_currency_to_language($lang);
      }

      public function polylang_language_defined(string $slug): void
      {
            $this->sync_currency_to_language($slug);
      }

      private function sync_currency_to_language(string $lang): void
      {
            // Check if user has manually chosen a currency - don't override
            if (!empty($_COOKIE["shscs_user_currency_choice"])) {
                  return; // User made manual choice, respect it
            }
            
            if (!empty($_COOKIE["shscs_currency"])) {
                  $allow_override = get_option(
                        "shscs_allow_user_override",
                        false,
                  );
                  if ($allow_override) {
                        return;
                  } // Respect user choice
            }

            $map = get_option("shscs_lang_currency_map", []);
            if (!isset($map[$lang])) {
                  return;
            }

            $currency = $map[$lang];

            // Check if currency is set (not empty)
            if (empty($currency)) {
                  return;
            }

		// Check if currency is valid and enabled
		if (!SHSCS_Main::is_valid_currency($currency)) {
			// Check if we should use default base currency instead
			$sync_enabled = get_option("shscs_lang_currency_sync", true);
			if ($sync_enabled) {
				$base_currency = get_option("shscs_base_currency", "USD");
				$currency = $base_currency;

				// Check if fallback currency is valid
				if (!SHSCS_Main::is_valid_currency($currency)) {
					return;
				}
			} else {
				return;
			}
		}

            // Set cookie
            setcookie("shscs_currency", $currency, [
                  "expires" => time() + 30 * DAY_IN_SECONDS,
                  "path" => COOKIEPATH ?: "/",
                  "domain" => COOKIE_DOMAIN,
                  "secure" => is_ssl(),
                  "httponly" => false,
                  "samesite" => "Lax",
            ]);

            // Update WooCommerce session if active
            if (class_exists("WooCommerce") && WC()->session) {
                  WC()->session->set("shscs_currency", $currency);
            }

            do_action("shscs_language_currency_synced", $lang, $currency);
      }

      public function maybe_set_initial_currency(): void
      {
            if (isset($_COOKIE["shscs_currency"])) {
                  return;
            } // Already set

            $lang = "";
            if (defined("SHSCS_WPML_ACTIVE") && SHSCS_WPML_ACTIVE) {
                  // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML core filter
                  $lang = apply_filters("wpml_current_language", null);
            } elseif (
                  defined("SHSCS_POLYLANG_ACTIVE") &&
                  SHSCS_POLYLANG_ACTIVE
            ) {
                  $lang = function_exists("pll_current_language")
                        ? pll_current_language("slug")
                        : "";
            }

            if ($lang) {
                  $this->sync_currency_to_language($lang);
            }
      }

      public function add_currency_to_language_switcher(string $html): string
      {
            // Add currency indicator to WPML language switcher
            return preg_replace_callback(
                  '/<a[^>]*href="([^"]*)"[^>]*>([^<]*)<\/a>/i',
                  function ($matches) {
                        $lang = "";
                        if (
                              preg_match(
                                    "/\/([a-z]{2})\//",
                                    $matches[1],
                                    $lang_match,
                              )
                        ) {
                              $lang = $lang_match[1];
                        }

                        $map = get_option("shscs_lang_currency_map", []);
                        if (isset($map[$lang])) {
                              return str_replace(
                                    $matches[2],
                                    $matches[2] .
                                    ' <span class="shscs-lang-currency">(' .
                                    esc_html($map[$lang]) .
                                    ")</span>",
                                    $matches[0],
                              );
                        }

                        return $matches[0];
                  },
                  $html,
            );
      }

      public function add_currency_to_polylang_switcher(
            array $output,
            array $args,
      ): array {
            $map = get_option("shscs_lang_currency_map", []);

            foreach ($output as &$item) {
                  $slug = $item["slug"] ?? "";
                  if (isset($map[$slug])) {
                        $item["name"] .=
                              ' <span class="shscs-lang-currency">(' .
                              esc_html($map[$slug]) .
                              ")</span>";
                  }
            }

            return $output;
      }

      public function get_current_language(): string
      {
            if (defined("SHSCS_WPML_ACTIVE") && SHSCS_WPML_ACTIVE) {
                  // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML core filter
                  return apply_filters("wpml_current_language", "") ?: "en";
            }
            if (defined("SHSCS_POLYLANG_ACTIVE") && SHSCS_POLYLANG_ACTIVE) {
                  return function_exists("pll_current_language")
                        ? pll_current_language("slug")
                        : "en";
            }
            return determine_locale();
      }
}
