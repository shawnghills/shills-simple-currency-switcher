<?php
/**
 * Currency Data Management Class
 *
 * @package ShillsSimpleCurrencySwitcher
 * @since   1.0.0
 */

declare(strict_types=1);

if (!defined("ABSPATH")) {
	exit();
}

/**
 * Class SHSCS_Currency
 */
class SHSCS_Currency
{
	private static ?self $instance = null;
	private array $cache = [];

	public static function get_instance(): self
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		add_action("shscs_auto_update_rates", [$this, "auto_update_rates"]);
	}

	public function get_currencies(bool $enabled_only = false): array
	{

		$currencies = get_option("shscs_currencies", []);


		if (!is_array($currencies)) {
			$currencies = [];
		}


		if (empty($currencies)) {
			$currencies = $this->get_default_currencies();
			update_option("shscs_currencies", $currencies);
		}

		usort(
			$currencies,
			fn($a, $b) => ($a["order"] ?? 0) <=> ($b["order"] ?? 0)
		);

		if ($enabled_only) {
			$currencies = array_filter(
				$currencies,
				fn($c) => !empty($c["enabled"])
			);
		}

		return array_values($currencies);
	}

	/**
	 * Get default currencies
	 * 
	 *
	 * @return array
	 */
	private function get_default_currencies(): array
	{
		return [
			[
				"code" => "USD",
				"symbol" => "$",
				"rate" => 0.15,
				"position" => "left",
				"decimals" => 2,
				"enabled" => 1,
				"order" => 0,
			],
			[
				"code" => "EUR",
				"symbol" => "€",
				"rate" => 0.85,
				"position" => "left",
				"decimals" => 2,
				"enabled" => 1,
				"order" => 1,
			],
			[
				"code" => "GBP",
				"symbol" => "£",
				"rate" => 0.73,
				"position" => "left",
				"decimals" => 2,
				"enabled" => 1,
				"order" => 2,
			],
		];
	}

	public function get_currency(string $code): ?array
	{
		foreach ($this->get_currencies() as $currency) {
			if ($currency["code"] === $code) {
				return $currency;
			}
		}
		return null;
	}

	public function add_currency(array $data)
	{
		$currencies = $this->get_currencies();

		if (empty($data["code"])) {
			return new WP_Error(
				"missing_code",
				__(
					"Currency code required",
					"shills-simple-currency-switcher"
				)
			);
		}

		foreach ($currencies as $c) {
			if ($c["code"] === $data["code"]) {
				return new WP_Error(
					"duplicate",
					__(
						"Currency exists",
						"shills-simple-currency-switcher"
					)
				);
			}
		}

		$new = [
			"code" => strtoupper(sanitize_text_field($data["code"])),
			"symbol" => sanitize_text_field($data["symbol"] ?? '$'),
			"rate" => floatval($data["rate"] ?? 1.0),
			"position" => in_array($data["position"] ?? "left", [
				"left",
				"right",
				"left_space",
				"right_space",
			])
				? $data["position"]
				: "left",
			"decimals" => intval($data["decimals"] ?? 2),
			"enabled" => !empty($data["enabled"]),
			"order" => count($currencies),
		];

		$currencies[] = $new;
		update_option("shscs_currencies", $currencies);

		do_action("shscs_currency_added", $new);
		return true;
	}

	public function update_currency(string $code, array $data)
	{
		$currencies = $this->get_currencies();
		$found = false;

		foreach ($currencies as $key => $c) {
			if ($c["code"] === $code) {
				if (isset($data["symbol"])) {
					$currencies[$key]["symbol"] = sanitize_text_field(
						$data["symbol"]
					);
				}
				if (isset($data["rate"])) {
					$currencies[$key]["rate"] = floatval(
						$data["rate"]
					);
				}
				if (isset($data["position"])) {
					$currencies[$key][
						"position"
					] = sanitize_text_field($data["position"]);
				}
				if (isset($data["decimals"])) {
					$currencies[$key]["decimals"] = intval(
						$data["decimals"]
					);
				}
				if (isset($data["enabled"])) {
					$currencies[$key]["enabled"] =
						(bool) $data["enabled"];
				}
				$found = true;
				break;
			}
		}

		if (!$found) {
			return new WP_Error(
				"not_found",
				__(
					"Currency not found",
					"shills-simple-currency-switcher"
				)
			);
		}

		update_option("shscs_currencies", $currencies);
		do_action("shscs_currency_updated", $code, $data);
		return true;
	}

	public function delete_currency(string $code)
	{
		if ($code === get_option("shscs_base_currency")) {
			return new WP_Error(
				"base_currency",
				__(
					"Cannot delete base currency",
					"shills-simple-currency-switcher"
				)
			);
		}

		$currencies = $this->get_currencies();
		$new = array_values(
			array_filter($currencies, fn($c) => $c["code"] !== $code)
		);

		if (count($new) === count($currencies)) {
			return new WP_Error(
				"not_found",
				__(
					"Currency not found",
					"shills-simple-currency-switcher"
				)
			);
		}

		foreach ($new as $key => $c) {
			$new[$key]["order"] = $key;
		}
		update_option("shscs_currencies", $new);

		do_action("shscs_currency_deleted", $code);
		return true;
	}

	public function update_order(array $order): bool
	{
		$currencies = $this->get_currencies();
		$new = [];
		$codes = array_column($currencies, "code");

		foreach ($order as $code) {
			$key = array_search($code, $codes);
			if ($key !== false) {
				$currencies[$key]["order"] = count($new);
				$new[] = $currencies[$key];
			}
		}

		foreach ($currencies as $c) {
			if (!in_array($c["code"], $order)) {
				$c["order"] = count($new);
				$new[] = $c;
			}
		}

		update_option("shscs_currencies", $new);
		return true;
	}

	public function update_rates_from_api()
	{
		$api = SHSCS_API::get_instance();
		$rates = $api->fetch_rates();

		if (is_wp_error($rates)) {
			return $rates;
		}

		$currencies = $this->get_currencies();
		$base = get_option("shscs_base_currency", "USD");
		$updated = [];

		foreach ($currencies as $key => $c) {
			$code = $c["code"];
			if (isset($rates[$code])) {
				$currencies[$key]["rate"] =
					$code === $base
						? 1.0
						: round(
							$rates[$code] /
								($rates[$base] ?? 1.0),
							6
						);
				$updated[] = $code;
			}
		}

		update_option("shscs_currencies", $currencies);
		update_option("shscs_last_update", time());
		delete_transient("shscs_rates_cache");

		do_action("shscs_rates_updated", $updated, $rates);
		return $updated;
	}

	public function auto_update_rates(): void
	{
		$result = $this->update_rates_from_api();
		if (!is_wp_error($result)) {
			// Auto-update successful
		}
	}

	public function get_frontend_data(): array
	{
		return array_map(
			fn($c) => [
				"code" => $c["code"] ?? '',
				"symbol" => $c["symbol"] ?? '',
				"position" => $c["position"] ?? 'left',
				"decimals" => $c["decimals"] ?? 2,
				"rate" => $c["rate"] ?? 1.0,
			],
			$this->get_currencies(true)
		);
	}

	public function get_base_currency(): string
	{
		return get_option("shscs_base_currency", "USD");
	}

	public function set_base_currency(string $code): bool
	{
		if (!$this->get_currency($code)) {
			return false;
		}
		update_option("shscs_base_currency", $code);
		$this->update_rates_from_api();
		return true;
	}

	/**
	 * Get default currency symbols
	 *
	 * @return array
	 */
	public static function get_default_symbols(): array
	{
		return [
			"USD" => "$", "EUR" => "€", "GBP" => "£", "JPY" => "¥", "CNY" => "¥",
			"AUD" => "$", "CAD" => "$", "CHF" => "CHF", "HKD" => "$", "SGD" => "$",
			"MXN" => "$", "BRL" => "R$", "ARS" => "$", "CLP" => "$", "COP" => "$",
			"PEN" => "S/", "UYU" => "$", "PYG" => "₲", "BOB" => "Bs", "VES" => "Bs",
			"KRW" => "₩", "INR" => "₹", "RUB" => "₽", "TRY" => "₺", "ZAR" => "R",
			"NZD" => "$", "SEK" => "kr", "NOK" => "kr", "DKK" => "kr", "PLN" => "zł",
			"CZK" => "Kč", "HUF" => "Ft", "RON" => "lei", "BGN" => "лв", "HRK" => "kn",
			"ILS" => "₪", "AED" => "د.إ", "SAR" => "ر.س", "QAR" => "ر.ق", "THB" => "฿",
			"MYR" => "RM", "IDR" => "Rp", "PHP" => "₱", "VND" => "₫",
		];
	}

	/**
	 * Get default symbol for a currency code
	 *
	 * @param string $code Currency code
	 * @return string
	 */
	public static function get_default_symbol(string $code): string
	{
		$symbols = self::get_default_symbols();
		return $symbols[$code] ?? "$";
	}
}
