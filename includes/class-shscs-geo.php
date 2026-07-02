<?php
/**
 * GeoIP Detection
 *
 * @package ShillsSimpleCurrencySwitcher
 * @since   1.0.0
 */

declare(strict_types=1);

if (!defined("ABSPATH")) {
      exit();
}

/**
 * Class SHSCS_Geo
 */
class SHSCS_Geo
{
      private static ?self $instance = null;
      private array $country_currency_map = [
            "US" => "USD",
            "GB" => "GBP",
            "EU" => "EUR",
            "CN" => "CNY",
            "JP" => "JPY",
            "AU" => "AUD",
            "CA" => "CAD",
            "CH" => "CHF",
            "HK" => "HKD",
            "SG" => "SGD",
            "DE" => "EUR",
            "FR" => "EUR",
            "IT" => "EUR",
            "ES" => "EUR",
            "NL" => "EUR",
            "BE" => "EUR",
            "AT" => "EUR",
            "IE" => "EUR",
            "FI" => "EUR",
            "PT" => "EUR",
            "GR" => "EUR",
            "KR" => "KRW",
            "IN" => "INR",
            "BR" => "BRL",
            "MX" => "MXN",
            "RU" => "RUB",
            "ZA" => "ZAR",
            "SE" => "SEK",
            "NO" => "NOK",
            "DK" => "DKK",
            "PL" => "PLN",
            "CZ" => "CZK",
            "HU" => "HUF",
            "RO" => "RON",
            "BG" => "BGN",
            "HR" => "EUR",
            "SI" => "EUR",
            "SK" => "EUR",
            "LT" => "EUR",
            "LV" => "EUR",
            "EE" => "EUR",
            "MT" => "EUR",
            "CY" => "EUR",
            "LU" => "EUR",
      ];

      public static function get_instance(): self
      {
            if (null === self::$instance) {
                  self::$instance = new self();
            }
            return self::$instance;
      }

      private function __construct()
      {
            add_action("init", [$this, "maybe_detect_currency"], 5); // Before multilingual
      }

      public function maybe_detect_currency(): void
      {
            // Skip if currency already set
            if (isset($_COOKIE["shscs_currency"])) {
                  return;
            }

            // Skip if not enabled
            if (!get_option("shscs_geo_detection", false)) {
                  return;
            }

            $country = $this->detect_country();
            if (!$country) {
                  return;
            }

            $currency = $this->country_currency_map[$country] ?? null;
            if (!$currency) {
                  return;
            }

            // Check if currency is enabled
            if (!SHSCS_Main::is_valid_currency($currency)) {
                  return;
            }

            // Set cookie
            setcookie(
                  "shscs_currency",
                  $currency,
                  time() + 30 * DAY_IN_SECONDS,
                  COOKIEPATH ?: "/",
            );

            do_action("shscs_geo_currency_detected", $country, $currency);
      }

      private function detect_country(): ?string
      {
            // Check Cloudflare
            if (!empty($_SERVER["HTTP_CF_IPCOUNTRY"])) {
                  return sanitize_text_field(wp_unslash($_SERVER["HTTP_CF_IPCOUNTRY"]));
            }

            // Check GeoIP if available
            if (function_exists("geoip_detect2_get_info_from_ip")) {
                  $info = geoip_detect2_get_info_from_ip();
                  if ($info && !empty($info->country->isoCode)) {
                        return $info->country->isoCode;
                  }
            }

            // Check MaxMind if available
            if (class_exists("WC_Geolocation")) {
                  $location = WC_Geolocation::geolocate_ip();
                  if (!empty($location["country"])) {
                        return $location["country"];
                  }
            }

            // Fallback to accept-language header for rough estimation
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only used for locale extraction
            $lang = isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? sanitize_text_field(wp_unslash($_SERVER["HTTP_ACCEPT_LANGUAGE"])) : "";
            if (preg_match("/^([a-z]{2})-([A-Z]{2})/", $lang, $matches)) {
                  return $matches[2];
            }

            return null;
      }

      public function get_country_currency_map(): array
      {
            return apply_filters(
                  "shscs_geo_country_currency_map",
                  $this->country_currency_map,
            );
      }
}
