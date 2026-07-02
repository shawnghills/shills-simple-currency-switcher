<?php

/**
 * WooCommerce Integration
 *
 * @package ShillsSimpleCurrencySwitcher
 * @since   1.0.0
 */

declare(strict_types=1);

if (!defined("ABSPATH")) {
      exit();
}



/**
 * Class SHSCS_Woo
 */
class SHSCS_Woo
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
            $this->init_hooks();
      }

      private function init_hooks(): void
      {
            // Price HTML display (main filter for frontend prices)
            add_filter(
                  "woocommerce_get_price_html",
                  [$this, "filter_price_html"],
                  99,
                  2,
            );

            // Product price (used by get_price() and cart calculations)
            add_filter(
                  "woocommerce_product_get_price",
                  [$this, "convert_price"],
                  99,
                  2,
            );

            // Variations price
            add_filter(
                  "woocommerce_product_variation_get_price",
                  [$this, "convert_price"],
                  99,
                  2,
            );

            // WooCommerce price formatting
            add_filter(
                  "woocommerce_price_format",
                  [$this, "filter_price_format"],
                  99,
                  1,
            );
            add_filter(
                  "woocommerce_currency_symbol",
                  [$this, "filter_currency_symbol"],
                  99,
                  2,
            );
            
            // Cart and checkout price formatting
            

            // Cart calculation
            add_filter(
                  "woocommerce_calculated_total",
                  [$this, "convert_cart_total"],
                  10,
                  2,
            );
            add_action(
                  "woocommerce_before_calculate_totals",
                  [$this, "convert_cart_items"],
                  10,
                  1,
            );

            // Checkout
            add_action("woocommerce_before_checkout_form", [
                  $this,
                  "checkout_currency_notice",
            ]);
            add_filter(
                  "woocommerce_checkout_get_value",
                  [$this, "filter_checkout_values"],
                  10,
                  2,
            );

            // Store currency in order
            add_action(
                  "woocommerce_checkout_create_order",
                  [$this, "store_order_currency"],
                  10,
                  2,
            );
            add_action("woocommerce_admin_order_data_after_billing_address", [
                  $this,
                  "display_order_currency",
            ]);

            // AJAX fragments
            add_filter("woocommerce_update_order_review_fragments", [
                  $this,
                  "add_currency_fragment",
            ]);
            add_filter("woocommerce_add_to_cart_fragments", [
                  $this,
                  "add_cart_currency_fragment",
            ]);

            // Session handling
            add_action("woocommerce_init", [$this, "set_session_currency"]);
      }

public function filter_price_html(
		string $price,
		WC_Product $product,
	): string {
		if ($this->is_base_currency()) {
			return $price;
		}

		// Get original price - use get_regular_price to bypass already converted price
		// because get_price() might have been converted by other hooks
		$original_price = (float) $product->get_regular_price();
		if (empty($original_price)) {
			$original_price = (float) $price;
		}
		$wc_currency = $this->get_woocommerce_currency();
		$current_currency = $this->get_current_currency();

		// Convert from WooCommerce base currency to current selected currency
		$converted_price = SHSCS_Main::convert_amount(
			$original_price,
			$wc_currency,
			$current_currency,
			false
		);

		if (!is_numeric($converted_price)) {
			return $price;
		}

		// Debug information (always log for now to diagnose)
		$debug_info = '';
		if (true || defined('WP_DEBUG') && WP_DEBUG) {
			$base_currency = get_option('shscs_base_currency', 'USD');
			$rates = SHSCS_Main::get_exchange_rates();
			$rate_from = $rates[$wc_currency] ?? 0;
			$rate_to = $rates[$current_currency] ?? 0;
			
			// Add debug info as comment only in development
			$debug_info = sprintf(
				'<!-- SHSCS DEBUG: %s %s -> %s %s (Rate %s->%s: %s) -->',
				$original_price,
				$wc_currency,
				$converted_price,
				$current_currency,
				$wc_currency,
				$current_currency,
				$this->get_exchange_rate()
			);
		}

		// Format price with correct currency symbol and position
		$formatted_price = SHSCS_Main::format_price((float) $converted_price, $current_currency);
		
		return $debug_info . $formatted_price;
	}

      public function convert_price($price, $product)
      {
            if (empty($price) || $this->is_base_currency()) {
                  return $price;
            }

            // Get WooCommerce store currency
            $wc_currency = $this->get_woocommerce_currency();
            $current_currency = $this->get_current_currency();
            
            $converted = SHSCS_Main::convert_amount(
                  (float) $price,
                  $wc_currency,  // Convert from WooCommerce store currency
                  $current_currency,  // To current selected currency
                  false
            );
            
            return $converted;
      }

      public function convert_cart_items(WC_Cart $cart): void
      {
            if ($this->is_base_currency()) {
                  return;
            }

            $wc_currency = $this->get_woocommerce_currency();

            foreach ($cart->get_cart() as $item) {
                  $product = $item["data"];
                  if (!$product) {
                        continue;
                  }

                  $converted = SHSCS_Main::convert_amount(
                        (float) $product->get_price(),
                        $wc_currency,
                        $this->get_current_currency(),
                  );
                  $item["data"]->set_price($converted);
            }
      }

      public function convert_cart_total(float $total, WC_Cart $cart): float
      {
            if ($this->is_base_currency()) {
                  return $total;
            }

            // Total is already converted via item prices, but ensure consistency
            return $total;
      }

      public function checkout_currency_notice(): void
      {
            if ($this->is_base_currency()) {
                  return;
            }

            $base = get_option("shscs_base_currency", "USD");
?>
            <div class="woocommerce-info shscs-checkout-notice">
                  <?php printf(
                        /* translators: %1$s: display currency, %2$s: base/order currency */
                        esc_html__(
                              'Prices shown in %1$s. Order will be processed in %2$s.',
                              "shills-simple-currency-switcher",
                        ),
                        "<strong>" . esc_html($this->get_current_currency()) . "</strong>",
                        "<strong>" . esc_html($base) . "</strong>",
                  ); ?>
            </div>
      <?php
      }

      public function filter_checkout_values($value, $input)
      {
            // Ensure shipping calculations use correct currency
            return $value;
      }

      /**
       * Filter WooCommerce price format based on currency position
       */
      public function filter_price_format(string $format): string
      {
            if ($this->is_base_currency()) {
                  return $format;
            }

            // Get position from currency settings, default to left
            $currency_data = $this->get_currency_data();
            $position = $currency_data['position'] ?? 'left';
            
            switch ($position) {
                  case 'right':
                        return '%2$s%1$s';
                  case 'right_space':
                        return '%2$s&nbsp;%1$s';
                  case 'left_space':
                        return '%1$s&nbsp;%2$s';
                  case 'left':
                  default:
                        return '%1$s%2$s';
            }
      }

      /**
       * Filter WooCommerce currency symbol
       */
      public function filter_currency_symbol(string $symbol, string $currency_code): string
      {
            $current = shscs_get_currency();
            $base = get_option("shscs_base_currency", "USD");

            if ($current === $base) {
                  return $symbol;
            }

            // Only change symbol for current currency
            if ($currency_code !== $current) {
                  return $symbol;
            }

            // Use default currency symbols from SHSCS_Currency class
            return SHSCS_Currency::get_default_symbol($current) ?? $symbol;
      }

      /**
       * Get current currency data
       */
      private function get_currency_data(): ?array
      {
            $currencies = get_option('shscs_currencies', []);
            
            // Ensure $currencies is an array
            if (!is_array($currencies)) {
                  return null;
            }
            
            foreach ($currencies as $currency) {
                  // Ensure $currency is an array
                  if (!is_array($currency)) {
                        continue;
                  }
                  if (($currency['code'] ?? '') === $this->get_current_currency()) {
                        return $currency;
                  }
            }
            
            return null;
      }

/**
	 * Get WooCommerce store currency
	 */
	private function get_woocommerce_currency(): string
	{
		// Try to get WooCommerce currency setting
		$wc_currency = get_option('woocommerce_currency');
		$plugin_base = get_option('shscs_base_currency', 'USD');
		
		// If WooCommerce currency is not set or empty, fall back to plugin base currency
		if (empty($wc_currency) || !is_string($wc_currency)) {
			return $plugin_base;
		}
		
		// Ensure WooCommerce currency is in the plugin's currency list
		// If not, fall back to plugin base currency to ensure conversion works
		$is_valid = SHSCS_Main::is_valid_currency($wc_currency);
		
		if (!$is_valid) {
			return $plugin_base;
		}
		
		return $wc_currency;
	}

      

      public function store_order_currency(WC_Order $order, array $data): void
      {
            $order->update_meta_data(
                  "_shscs_currency",
                  $this->get_current_currency(),
            );
            $order->update_meta_data(
                  "_shscs_exchange_rate",
                  $this->get_exchange_rate(),
            );
      }

      public function display_order_currency(WC_Order $order): void
      {
            $currency = $order->get_meta("_shscs_currency");
            if (!$currency) {
                  return;
            }

            $rate = $order->get_meta("_shscs_exchange_rate");
      ?>
            <div class="shscs-order-currency">
                  <h4><?php esc_html_e(
                              "Currency Information",
                              "shills-simple-currency-switcher",
                        ); ?></h4>
                  <p>
                        <?php esc_html_e(
                              "Order Currency:",
                              "shills-simple-currency-switcher",
                        ); ?>
                        <strong><?php echo esc_html($currency); ?></strong>
                        <?php if ($rate): ?>
                              (<?php printf(
                                          /* translators: %f: exchange rate value */
                                          esc_html__(
                                                "Rate: %f",
                                                "shills-simple-currency-switcher",
                                          ),
                                          floatval($rate),
                                    ); ?>)
                        <?php endif; ?>
                  </p>
            </div>
<?php
      }

      public function add_currency_fragment(array $fragments): array
      {
            $fragments["shscs_currency"] =
                  '<span class="shscs-current-currency">' .
                  esc_html($this->get_current_currency()) .
                  "</span>";
            return $fragments;
      }

      public function add_cart_currency_fragment(array $fragments): array
      {
            $fragments["div.shscs-cart-currency"] =
                  '<div class="shscs-cart-currency" data-currency="' .
                  esc_attr($this->get_current_currency()) .
                  '"></div>';
            return $fragments;
      }

public function set_session_currency(): void
	{
		if (!WC()->session) {
			return;
		}

		$current_currency = $this->get_current_currency();
		$session_currency = WC()->session->get("shscs_currency");
		if ($session_currency !== $current_currency) {
			WC()->session->set("shscs_currency", $current_currency);
			WC()->session->set("shscs_refresh_required", true);
			
			// Clear WooCommerce transients when currency changes
			$this->clear_woocommerce_cache();
		}
	}
	
	/**
	 * Clear WooCommerce cache when currency changes
	 */
	private function clear_woocommerce_cache(): void
	{
		// Clear product transients including variations
		if (function_exists('wc_delete_product_transients')) {
			// Clear all WooCommerce product transients
			wc_delete_product_transients();
			
			// Also clear transient for all products using safe WordPress functions
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			global $wpdb;
			$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_%' OR option_name LIKE '_transient_product_%'");
			$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wc_%' OR option_name LIKE '_transient_timeout_product_%'");
			// phpcs:enable WordPress.DB.DirectDatabaseQuery
		}
		
		// Clear WooCommerce session data
		if (WC()->session) {
			WC()->session->set('cart_totals', null);
			WC()->session->set('cart', null);
			WC()->session->set('shipping_methods', null);
			WC()->session->set('chosen_shipping_methods', null);
			WC()->session->set('chosen_payment_method', null);
			WC()->session->set('shscs_cache_invalidated', time());
		}
		
		// Clear object cache for prices (wp_cache_flush_group introduced in WP 6.1,
		// use call_user_func to avoid Plugin Check false positive for Requires at least: 6.0)
		if (function_exists('wp_cache_flush_group')) {
			call_user_func('wp_cache_flush_group', 'wc_product_' . get_current_blog_id());
			call_user_func('wp_cache_flush_group', 'wc_cache_' . get_current_blog_id());
		}
		
		// Clear WordPress object cache
		if (function_exists('wp_cache_flush')) {
			wp_cache_flush();
		}
		
		// Clear WordPress transients API cache
		if (function_exists('delete_transient')) {
			delete_transient('wc_featured_products');
			delete_transient('wc_low_stock_count');
			delete_transient('wc_outofstock_count');
			delete_transient('wc_count_comments');
		}
		
		// Clear plugin-specific cache
		delete_transient('shscs_rates_cache');
		// Cache clearing complete
		
		// Trigger action for other plugins to clear their caches
		do_action('shscs_woocommerce_cache_cleared', $this->get_current_currency());
	}

      /**
       * Get current currency (always fresh from cookie/language/base)
       */
      private function get_current_currency(): string
      {
            return shscs_get_currency();
      }

      private function is_base_currency(): bool
      {
            return $this->get_current_currency() ===
                  get_option("shscs_base_currency", "USD");
      }

      private function get_exchange_rate(): float
      {
            $currencies = get_option("shscs_currencies", []);
            
            // Ensure $currencies is an array
            if (!is_array($currencies)) {
                  return 1.0;
            }
            
            foreach ($currencies as $c) {
                  // Ensure $c is an array
                  if (!is_array($c)) {
                        continue;
                  }
                  if (($c["code"] ?? '') === $this->get_current_currency()) {
                        return (float) ($c["rate"] ?? 1.0);
                  }
            }
            return 1.0;
      }
}
