<?php
/**
 * Exchange Rate API Handler
 *
 * @package ShillsSimpleCurrencySwitcher
 * @since   1.0.0
 */

declare(strict_types=1);

if (!defined("ABSPATH")) {
      exit();
}

/**
 * Class SHSCS_API
 */
class SHSCS_API
{
      private static ?self $instance = null;
      private array $providers;
      private int $cache_duration = 3600;

      public static function get_instance(): self
      {
            if (null === self::$instance) {
                  self::$instance = new self();
            }
            return self::$instance;
      }

      private function __construct()
      {
            // Initialize providers without translations in constructor
            $this->providers = [
                  "none" => [
                        "name" => "None - Use Manual Rates",
                        "url" => "",
                        "free" => true,
                        "key" => false,
                        "manual" => true,
                  ],
                  "exchangerate-api" => [
                        "name" => "ExchangeRate-API.com",
                        "url" =>
                              "https://api.exchangerate-api.com/v4/latest/{base}",
                        "free" => true,
                        "key" => false,
                        "manual" => false,
                  ],
                  "openexchangerates" => [
                        "name" => "Open Exchange Rates",
                        "url" =>
                              "https://openexchangerates.org/api/latest.json?app_id={key}",
                        "free" => true,
                        "key" => true,
                        "manual" => false,
                  ],
            ];
      }

      public function get_providers(): array
      {
            // Apply filters and translations only when providers are requested
            $providers = $this->providers;

            // Apply translations
            if (isset($providers["none"]) && is_array($providers["none"])) {
                  $providers["none"]["name"] = __("None - Use Manual Rates", "shills-simple-currency-switcher");
            }

            return apply_filters("shscs_api_providers", $providers);
      }

      public function fetch_rates(?string $provider = null)
      {
            $cached = get_transient("shscs_rates_cache");
            if ($cached === false || !is_array($cached) || empty($cached)) {
                  // Force refresh if cache is invalid
                  $cached = false;
            }

            if ($cached !== false) {
                  return $cached;
            }

            $provider =
                  $provider ??
                  get_option("shscs_api_source", "exchangerate-api");

            if (!isset($this->providers[$provider])) {
                  return new WP_Error(
                        "invalid_provider",
                        __(
                              "Invalid API provider",
                              "shills-simple-currency-switcher",
                        ),
                  );
            }

            // If provider is "none", get rates from manual settings
            if ($provider === "none" || ($this->providers[$provider]["manual"] ?? false)) {
                  $rates = $this->get_manual_rates();

                  if (!is_wp_error($rates)) {
                        set_transient(
                              "shscs_rates_cache",
                              $rates,
                              $this->cache_duration,
                        );
                  }

                  return $rates;
            }

            $base = get_option("shscs_base_currency", "USD");
            $rates = $this->fetch_from_provider($provider, $base);

            if (is_wp_error($rates)) {
                  foreach ($this->providers as $key => $p) {
                        if ($key !== $provider && $p["free"] && !($p["manual"] ?? false)) {
                              $rates = $this->fetch_from_provider($key, $base);
                              if (!is_wp_error($rates)) {
                                    break;
                              }
                        }
                  }
            }

            if (!is_wp_error($rates)) {
                  set_transient(
                        "shscs_rates_cache",
                        $rates,
                        $this->cache_duration,
                  );
            }

            return $rates;
      }

      private function fetch_from_provider(string $provider, string $base)
      {
            $config = $this->providers[$provider];

            // If provider is manual (like "none"), return manual rates
            if ($config["manual"] ?? false) {
                  return $this->get_manual_rates();
            }

            $url = str_replace("{base}", $base, $config["url"]);

            if ($config["key"]) {
                  $key = get_option("shscs_api_key_{$provider}", "");
                  if (empty($key)) {
                        return new WP_Error(
                              "missing_key",
                              sprintf(
                                    /* translators: %s: API provider name */
                                    __(
                                          "API key required for %s",
                                          "shills-simple-currency-switcher",
                                    ),
                                    $config["name"],
                              ),
                        );
                  }
                  $url = str_replace("{key}", urlencode($key), $url);
            }

            $url = apply_filters("shscs_api_request_url", $url, $provider);
            $response = wp_remote_get($url, [
                  "timeout" => 30,
                  "headers" => ["Accept" => "application/json"],
            ]);

            if (is_wp_error($response)) {
                  return $response;
            }
            if (wp_remote_retrieve_response_code($response) !== 200) {
                  return new WP_Error(
                        "api_error",
                        sprintf(
                              /* translators: %d: HTTP response status code */
                              __(
                                    "API error: %d",
                                    "shills-simple-currency-switcher",
                              ),
                              wp_remote_retrieve_response_code($response),
                        ),
                  );
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                  return new WP_Error(
                        "json_error",
                        __("Invalid JSON", "shills-simple-currency-switcher"),
                  );
            }

            return $this->parse_rates($provider, $data);
      }

     

      private function parse_rates(string $provider, array $data): array
      {
            $rates = [];
            if (isset($data["rates"]) && is_array($data["rates"])) {
                  $rates = $data["rates"];
            }

            $base = get_option("shscs_base_currency", "USD");

            // Standardize format + filter invalid rates
            $normalized = ["_last_updated" => time(), "_source" => $provider, "_base" => $base];
            foreach ($rates as $code => $rate) {
                  if (is_numeric($rate) && $rate > 0) {
                        $normalized[$code] = (float) $rate;
                  }
            }
            $normalized[$base] = 1.0;

            return apply_filters("shscs_parsed_rates", $normalized, $provider, $data);
      }
      public function get_last_update(): int
      {
            return (int) get_option("shscs_last_update", 0);
      }

      /**
       * Get manual exchange rates from currency settings
       *
       * @return array|WP_Error Array of rates or WP_Error on failure
       */
      private function get_manual_rates()
      {
            $currencies = get_option("shscs_currencies", []);
            $base_currency = get_option("shscs_base_currency", "USD");

            if (!is_array($currencies) || empty($currencies)) {
                  return new WP_Error(
                        "no_manual_rates",
                        __("No manual rates configured", "shills-simple-currency-switcher"),
                  );
            }

            $rates = [];

            foreach ($currencies as $currency) {
                  // Ensure $currency is an array
                  if (!is_array($currency)) {
                        continue;
                  }

                  $code = $currency["code"] ?? '';
                  $rate = $currency["rate"] ?? 1.0;
                  $enabled = $currency["enabled"] ?? false;


                  if (!empty($code) && !empty($enabled)) {
                        // Convert to float and ensure it's valid
                        $rate_value = is_numeric($rate) ? (float) $rate : 1.0;
                        if ($rate_value <= 0) {
                              $rate_value = 1.0;
                        }

                        $rates[$code] = $rate_value;
                  }
            }

            // Always include base currency with rate 1.0
            if (!isset($rates[$base_currency])) {
                  $rates[$base_currency] = 1.0;
            }

            // Sort rates alphabetically by currency code
            ksort($rates);

            // Add timestamp
            $last_update = time();
            update_option("shscs_last_update", $last_update);
            $rates["_last_updated"] = $last_update;

            // $rates["_last_updated"] = $this->get_last_update();
            $rates["_source"] = "manual";
            $rates["_base"] = $base_currency;
            // Add validity filtering
            $rates = array_filter($rates, function ($v, $k) {
                  return strpos($k, '_') === 0 || (is_numeric($v) && $v > 0);
            }, ARRAY_FILTER_USE_BOTH);

            return apply_filters("shscs_manual_rates", $rates, $currencies);
      }

      public function clear_cache(): void
      {
            delete_transient("shscs_rates_cache");
      }
}
