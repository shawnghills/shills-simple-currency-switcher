<?php
/**
 * Admin Settings Page
 *
 * @package ShillsSimpleCurrencySwitcher
 * @since   1.0.0
 */

declare(strict_types=1);

if (!defined("ABSPATH")) {
      exit();
}

use SHSCS_Currency;

/**
 * Class SHSCS_Admin
 */
class SHSCS_Admin
{
      private static ?self $instance = null;
      private string $slug = "shscs_settings";

    
      private string $group_general = "shscs_general";
      private string $group_currencies = "shscs_currencies";
      private string $group_multilingual = "shscs_multilingual";

      public static function get_instance(): self
      {
            if (null === self::$instance) {
                  self::$instance = new self();
            }
            return self::$instance;
      }

      private function __construct()
      {
            add_action("admin_menu", [$this, "add_page"]);
            add_action("admin_init", [$this, "register_settings"]);

            // Preserve settings tab state
            add_filter('wp_redirect', [$this, 'preserve_settings_tab'], 10, 2);

            // Clear cache after saving (registered per group)
            add_action("update_option_shscs_currencies", [$this, 'clear_options_cache'], 10, 0);
            add_action("update_option_shscs_lang_currency_map", [$this, 'clear_options_cache'], 10, 0);
            add_action("update_option_shscs_base_currency", [$this, 'clear_options_cache'], 10, 0);
      }

      public function add_page(): void
      {
            add_options_page(
                  __("Currency Switcher", "shills-simple-currency-switcher"),
                  __("Currency Switcher", "shills-simple-currency-switcher"),
                  "manage_options",
                  $this->slug,
                  [$this, "render_page"],
            );

        

      }

      /**
 * Render integrated API test page (auto-authenticated)
 */
public function render_api_test_page(): void
{
    // Get current user's valid nonce
    $nonce = wp_create_nonce('wp_rest');
    $api_base = rest_url('shscs/v1/');
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p>Test SHSCS REST API endpoints with automatic authentication.</p>
        
        <!-- Auto-configured panel -->
        <div style="background:#fff;padding:15px;border:1px solid #ddd;border-radius:5px;margin:20px 0">
            <strong>Auto-Configured:</strong><br>
            <code>API Base: <?php echo esc_url(rtrim($api_base, '/')); ?></code><br>
            <code>Nonce: <?php echo esc_html(substr($nonce, 0, 20)) . '...'; ?></code>
            <br><small style="color:#666">No manual config needed • Logged in as <?php echo esc_html(wp_get_current_user()->user_login); ?></small>
        </div>
        
        <!-- Inline test interface -->
        <div id="shscs-inline-test">
            <h3>Quick Switch Test</h3>
            <input type="text" id="inline-currency" value="CNY" placeholder="Currency code" style="padding:8px;width:100px">
            <button onclick="inlineSwitch()" style="padding:8px 15px;background:#007cba;color:#fff;border:none;border-radius:3px;cursor:pointer">
                Test Switch
            </button>
            <div id="inline-result" style="margin-top:10px;padding:10px;background:#f5f5f5;border-radius:3px;font-family:monospace"></div>
        </div>
        
        <script>
        // Auto-inject config
        window.SHSCS_TEST_CONFIG = {
            apiBase: '<?php echo esc_js(rtrim($api_base, '/')); ?>',
            nonce: '<?php echo esc_js($nonce); ?>',
        };
        
        async function inlineSwitch() {
            const currency = document.getElementById('inline-currency').value.trim().toUpperCase();
            const resultEl = document.getElementById('inline-result');
            resultEl.innerHTML = 'Switching...';
            
            try {
                const response = await fetch(SHSCS_TEST_CONFIG.apiBase + '/switch', {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': SHSCS_TEST_CONFIG.nonce
                    },
                    body: JSON.stringify({ currency })
                });
                
                const data = await response.json();
                resultEl.innerHTML = `<span style="color:${response.ok ? 'green' : 'red'}">HTTP ${response.status}</span><br>` +
                                    `<pre>${JSON.stringify(data, null, 2)}</pre>`;
                
                if (response.ok) {
                    // Reload page to apply new currency
                    setTimeout(() => location.reload(), 1000);
                }
            } catch (error) {
                resultEl.innerHTML = `<span style="color:red">Error: ${error.message}</span>`;
            }
        }
        </script>
    </div>
    <?php
}

      /**
       * Register settings by group, independent of each other
       */
      public function register_settings(): void
      {
            // ==================== General group ====================
            register_setting($this->group_general, "shscs_base_currency", [
                  "default" => "USD",
                  "sanitize_callback" => "sanitize_text_field",
                  "show_in_rest" => false,
            ]);
            register_setting($this->group_general, "shscs_api_source", [
                  "default" => "none",
                  "sanitize_callback" => "sanitize_text_field",
                  "show_in_rest" => false,
            ]);
            register_setting($this->group_general, "shscs_auto_update", [
                  "default" => "daily",
                  "sanitize_callback" => "sanitize_text_field",
                  "show_in_rest" => false,
            ]);
            register_setting($this->group_general, "shscs_theme_mode", [
                  "default" => "auto",
                  "sanitize_callback" => "sanitize_text_field",
                  "show_in_rest" => false,
            ]);
            register_setting($this->group_general, "shscs_custom_color", [
                  "default" => "#007cba",
                  "sanitize_callback" => "sanitize_hex_color",
                  "show_in_rest" => false,
            ]);

            // ==================== Currencies group ====================
            register_setting($this->group_currencies, "shscs_currencies", [
                  "default" => [],
                  "sanitize_callback" => [$this, "sanitize_currencies"],
                  "show_in_rest" => false,
            ]);

            // ==================== Multilingual group ====================
            register_setting($this->group_multilingual, "shscs_lang_currency_map", [
                  "default" => [],
                  "sanitize_callback" => [$this, "sanitize_lang_currency_map"],
                  "show_in_rest" => false,
            ]);
            register_setting($this->group_multilingual, "shscs_lang_currency_sync", [
                  "default" => "1",
                  "sanitize_callback" => function ($v) {
                        return $v ? "1" : "0"; },
                  "show_in_rest" => false,
            ]);
            register_setting($this->group_multilingual, "shscs_allow_user_override", [
                  "default" => "0",
                  "sanitize_callback" => function ($v) {
                        return $v ? "1" : "0"; },
                  "show_in_rest" => false,
            ]);
            register_setting($this->group_multilingual, "shscs_geo_detection", [
                  "default" => "0",
                  "sanitize_callback" => function ($v) {
                        return $v ? "1" : "0"; },
                  "show_in_rest" => false,
            ]);
      }

      /**
       * Clear options cache after saving
       */
      public function clear_options_cache(): void
      {
            wp_cache_delete('alloptions', 'options');
            wp_cache_delete('shscs_currencies', 'options');
            wp_cache_delete('shscs_base_currency', 'options');
            wp_cache_delete('shscs_lang_currency_map', 'options');
            delete_transient('shscs_currency_data');
      }

      /**
       * Redirect filter to preserve tab state
       */
	public function preserve_settings_tab(string $location, int $status): string
	{
		if (strpos($location, 'settings-updated=true') === false) {
			return $location;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Settings form uses WordPress settings API nonce
		$tab = isset($_POST['shscs_active_tab']) ? sanitize_text_field(wp_unslash($_POST['shscs_active_tab'])) : null;

		if (empty($tab) && !empty($_SERVER['HTTP_REFERER'])) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only reading for tab param
			$referer = sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER']));
			parse_str(wp_parse_url($referer, PHP_URL_QUERY) ?? '', $referer_params);
			$tab = $referer_params['tab'] ?? null;
		}

		if ($tab && in_array($tab, ['general', 'currencies', 'multilingual'], true)) {
			$location = add_query_arg('tab', sanitize_text_field($tab), $location);
		}

		return $location;
	}

      public function render_page(): void
      {
          

         

            if (!current_user_can("manage_options")) {
                  return;
            }

            $base_currency = get_option('shscs_base_currency', 'USD');
            $currencies = get_option('shscs_currencies', []);

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page tab selection, no nonce needed
            $tab = isset($_GET["tab"]) ? sanitize_text_field(wp_unslash($_GET["tab"])) : "general";
            ?>
            <style>
                  .shscs-admin-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 20px;
                        flex-wrap: wrap;
                        gap: 15px;
                  }

                  .shscs-admin-header .nav-tab-wrapper {
                        margin-bottom: 0;
                  }

                  .shscs-admin-actions {
                        display: flex;
                        gap: 10px;
                        align-items: center;
                  }

                  .shscs-admin-actions .button {
                        margin-top: 0;
                  }

                  @media screen and (max-width: 782px) {
                        .shscs-admin-header {
                              flex-direction: column;
                              align-items: flex-start;
                        }

                        .shscs-admin-actions {
                              width: 100%;
                              justify-content: flex-start;
                        }
                  }
            </style>
            <div class="wrap shscs-admin tab-<?php echo esc_attr($tab); ?>">
                  <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

                  <?php if (isset($_POST['shscs_clear_cache']) && check_admin_referer('shscs_clear_cache_nonce')): ?>
                        <div class="notice notice-success is-dismissible">
                              <p><?php esc_html_e('All caches cleared successfully!', 'shills-simple-currency-switcher'); ?></p>
                        </div>
                  <?php endif; ?>

                  <div class="shscs-admin-header">
                        <nav class="nav-tab-wrapper">
                              <a href="?page=<?php echo esc_attr($this->slug); ?>&tab=general"
                                    class="nav-tab <?php echo $tab === "general" ? "nav-tab-active" : ""; ?>">
                                    <?php esc_html_e("General", "shills-simple-currency-switcher"); ?>
                              </a>
                              <a href="?page=<?php echo esc_attr($this->slug); ?>&tab=currencies"
                                    class="nav-tab <?php echo $tab === "currencies" ? "nav-tab-active" : ""; ?>">
                                    <?php esc_html_e("Currencies", "shills-simple-currency-switcher"); ?>
                              </a>
                              <?php if (defined("SHSCS_MULTILINGUAL_ACTIVE") && SHSCS_MULTILINGUAL_ACTIVE): ?>
                              <a href="?page=<?php echo esc_attr($this->slug); ?>&tab=multilingual"
                                    class="nav-tab <?php echo $tab === "multilingual" ? "nav-tab-active" : ""; ?>">
                                    <?php esc_html_e("Multilingual", "shills-simple-currency-switcher"); ?>
                              </a>
                              <?php endif; ?>
                        </nav>
                  </div>

                  <?php
                  // Only show multilingual tab if multilingual plugin is active
                  $multilingual_active = defined("SHSCS_MULTILINGUAL_ACTIVE") && SHSCS_MULTILINGUAL_ACTIVE;
                  if ($tab === "multilingual" && !$multilingual_active) {
                        $tab = "general";
                  }
                  match ($tab) {
                        "currencies" => $this->render_currencies_tab(),
                        "multilingual" => $this->render_multilingual_tab(),
                        default => $this->render_general_tab(),
                  };
                  ?>
            </div>
            <?php
      }

      private function render_general(): void
      {
            $base = get_option("shscs_base_currency", "USD");
            $api = get_option("shscs_api_source", "exchangerate-api");
            $update = get_option("shscs_auto_update", "daily");
            $theme = get_option("shscs_theme_mode", "auto");
            $color = get_option("shscs_custom_color", "#007cba");

            $providers = $this->get_api_providers();
            ?>
            <table class="form-table">
                  <tr>
                        <th><?php esc_html_e("Base Currency", "shills-simple-currency-switcher"); ?></th>
                        <td>
                              <select name="shscs_base_currency">
                                    <?php foreach ($this->get_iso_currencies() as $code => $name): ?>
                                          <option value="<?php echo esc_attr($code); ?>" <?php selected($base, $code); ?>>
                                                <?php echo esc_html("{$code} - {$name}"); ?>
                                          </option>
                                    <?php endforeach; ?>
                              </select>
                        </td>
                  </tr>
                  <tr>
                        <th><?php esc_html_e("API Source", "shills-simple-currency-switcher"); ?></th>
                        <td>
                              <select name="shscs_api_source">
                                    <?php foreach ($providers as $key => $provider):
                                          $label = $provider['name'];
                                          if ($provider['key'] ?? false)
                                                $label .= ' (' . __('Key required', 'shills-simple-currency-switcher') . ')';
                                          elseif ($provider['manual'] ?? false)
                                                $label .= ' (' . __('Manual rates', 'shills-simple-currency-switcher') . ')';
                                          elseif ($provider['free'] ?? false)
                                                $label .= ' (' . __('Free', 'shills-simple-currency-switcher') . ')';
                                          ?>
                                          <option value="<?php echo esc_attr($key); ?>" <?php selected($api, $key); ?>>
                                                <?php echo esc_html($label); ?>
                                          </option>
                                    <?php endforeach; ?>
                              </select>
                        </td>
                  </tr>
                  <tr>
                        <th><?php esc_html_e("Auto Update", "shills-simple-currency-switcher"); ?></th>
                        <td>
                              <select name="shscs_auto_update">
                                    <option value="disabled" <?php selected($update, "disabled"); ?>>
                                          <?php esc_html_e("Disabled", "shills-simple-currency-switcher"); ?></option>
                                    <option value="hourly" <?php selected($update, "hourly"); ?>>
                                          <?php esc_html_e("Hourly", "shills-simple-currency-switcher"); ?></option>
                                    <option value="daily" <?php selected($update, "daily"); ?>>
                                          <?php esc_html_e("Daily", "shills-simple-currency-switcher"); ?></option>
                              </select>
                        </td>
                  </tr>
                  <tr>
                        <th><?php esc_html_e("Theme Mode", "shills-simple-currency-switcher"); ?></th>
                        <td>
                              <select name="shscs_theme_mode" id="shscs_theme_mode">
                                    <option value="auto" <?php selected($theme, "auto"); ?>>
                                          <?php esc_html_e("Auto Detect", "shills-simple-currency-switcher"); ?></option>
                                    <option value="light" <?php selected($theme, "light"); ?>>
                                          <?php esc_html_e("Light", "shills-simple-currency-switcher"); ?></option>
                                    <option value="dark" <?php selected($theme, "dark"); ?>>
                                          <?php esc_html_e("Dark", "shills-simple-currency-switcher"); ?></option>
                                    <option value="custom" <?php selected($theme, "custom"); ?>>
                                          <?php esc_html_e("Custom", "shills-simple-currency-switcher"); ?></option>
                              </select>
                        </td>
                  </tr>
                  <tr id="custom_color_row" style="<?php echo $theme !== "custom" ? "display:none" : ""; ?>">
                        <th><?php esc_html_e("Custom Color", "shills-simple-currency-switcher"); ?></th>
                        <td><input type="color" name="shscs_custom_color" value="<?php echo esc_attr($color); ?>"></td>
                  </tr>
            </table>
            <?php
      }

	private function render_general_tab(): void
      {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page tab selection, no nonce needed
            $tab = isset($_GET["tab"]) ? sanitize_text_field(wp_unslash($_GET["tab"])) : "general";
            echo '<form method="post" action="options.php">';

            settings_fields($this->group_general);
            $this->render_general();
            echo '<p class="submit">';
            submit_button(__("Save General Settings", "shills-simple-currency-switcher"), "primary", "submit", false);
            echo '</p></form>';

            $this->render_cache_clear_form();
      }

      private function render_currencies(): void
      {
            // Read directly from database to ensure latest data
            $currencies = get_option('shscs_currencies', []);
            ?>
            <div id="shscs-currency-manager">
                  <table class="wp-list-table widefat striped" id="shscs-currency-table">
                        <thead>
                              <tr>
                                    <th class="column-drag"></th>
                                    <th scope="col"><?php esc_html_e('Code', 'shills-simple-currency-switcher'); ?></th>
                                    <th scope="col"><?php esc_html_e('Symbol', 'shills-simple-currency-switcher'); ?></th>
                                    <th scope="col"><?php esc_html_e('Rate', 'shills-simple-currency-switcher'); ?></th>
                                    <th scope="col"><?php esc_html_e('Position', 'shills-simple-currency-switcher'); ?></th>
                                    <th scope="col"><?php esc_html_e('Enabled', 'shills-simple-currency-switcher'); ?></th>
                                    <th scope="col"><?php esc_html_e('Actions', 'shills-simple-currency-switcher'); ?></th>
                              </tr>
                        </thead>
                        <tbody>
                              <?php if (empty($currencies)): ?>
                                    <tr>
                                          <td colspan="7" style="text-align:center;padding:20px;">
                                                <?php esc_html_e('No currencies configured. Click "Add Currency" to start.', 'shills-simple-currency-switcher'); ?>
                                          </td>
                                    </tr>
                              <?php else:
                                    foreach ($currencies as $index => $currency):
                                          $code = $currency['code'] ?? '';
                                          $symbol = $currency['symbol'] ?? '';
                                          $rate = $currency['rate'] ?? '1.000000';
                                          $position = $currency['position'] ?? 'left';
                                          $enabled = !empty($currency['enabled']);
                                          ?>
                                          <tr data-code="<?php echo esc_attr($code); ?>">
                                                <td class="column-drag"><span class="dashicons dashicons-menu"></span></td>
                                                <td>
                                                      <select name="shscs_currencies[<?php echo esc_attr($index); ?>][code]"
                                                            class="shscs-currency-code-select" data-index="<?php echo esc_attr($index); ?>">
                                                            <option value="">
                                                                  <?php esc_html_e('— Select Currency —', 'shills-simple-currency-switcher'); ?>
                                                            </option>
                                                            <?php foreach ($this->get_iso_currencies() as $curr_code => $curr_name): ?>
                                                                  <option value="<?php echo esc_attr($curr_code); ?>" <?php selected($code, $curr_code); ?>>
                                                                        <?php echo esc_html($curr_code . ' - ' . $curr_name); ?>
                                                                  </option>
                                                            <?php endforeach; ?>
                                                      </select>
                                                </td>
                                                <td>
                                                     <input type="hidden"
           name="shscs_currencies[<?php echo esc_attr( $index ); ?>][symbol]"
           id="shscs_symbol_<?php echo esc_attr( $index ); ?>"
           value="<?php echo esc_attr( $symbol ); ?>">
    <!-- Fix: value uses unique currency code, data-symbol stores symbol -->
    <select class="shscs-currency-symbol-select" data-index="<?php echo esc_attr( $index ); ?>">
        <option value=""><?php esc_html_e( '— Select Symbol —', 'shills-simple-currency-switcher' ); ?></option>
        
        <?php foreach ( $this->get_currency_symbols() as $curr_code => $curr_symbol ): ?>
        <option value="<?php echo esc_attr( $curr_code ); ?>" 
                <?php selected( $code, $curr_code ); ?>
                data-symbol="<?php echo esc_attr( $curr_symbol ); ?>">
            <?php echo esc_html( $curr_code . ' - ' . $curr_symbol ); ?>
        </option>
        <?php endforeach; ?>
        
        <option value="" disabled>────────────</option>
        <option value="CUSTOM"><?php esc_html_e( 'Custom Symbol', 'shills-simple-currency-switcher' ); ?></option>
    </select>
                                                      <input type="text" name="shscs_currencies[<?php echo esc_attr($index); ?>][symbol_custom]"
                                                            value="<?php echo !in_array($symbol, array_values($this->get_currency_symbols())) && !empty($symbol) ? esc_attr($symbol) : ''; ?>"
                                                            class="small-text shscs-custom-symbol-input"
                                                            data-index="<?php echo esc_attr($index); ?>" size="5"
                                                            placeholder="<?php esc_attr_e('Custom symbol', 'shills-simple-currency-switcher'); ?>"
                                                            style="<?php echo in_array($symbol, array_values($this->get_currency_symbols())) ? 'display:none;' : ''; ?>">
                                                </td>
                                                <td><input type="number" step="0.000001"
                                                            name="shscs_currencies[<?php echo esc_attr($index); ?>][rate]"
                                                            value="<?php echo esc_attr($rate); ?>" class="small-text"></td>
                                                <td>
                                                      <select name="shscs_currencies[<?php echo esc_attr($index); ?>][position]">
                                                            <option value="left" <?php selected($position, 'left'); ?>>
                                                                  <?php esc_html_e('Left', 'shills-simple-currency-switcher'); ?></option>
                                                            <option value="right" <?php selected($position, 'right'); ?>>
                                                                  <?php esc_html_e('Right', 'shills-simple-currency-switcher'); ?></option>
                                                            <option value="left_space" <?php selected($position, 'left_space'); ?>>
                                                                  <?php esc_html_e('Left + Space', 'shills-simple-currency-switcher'); ?>
                                                            </option>
                                                            <option value="right_space" <?php selected($position, 'right_space'); ?>>
                                                                  <?php esc_html_e('Right + Space', 'shills-simple-currency-switcher'); ?>
                                                            </option>
                                                      </select>
                                                </td>
                                                <td><input type="checkbox" name="shscs_currencies[<?php echo esc_attr($index); ?>][enabled]"
                                                            value="1" <?php checked(!empty($currency['enabled'])); ?>></td>
                                                <td><button type="button" class="button shscs-delete"
                                                            data-code="<?php echo esc_attr($code); ?>"><?php esc_html_e('Delete', 'shills-simple-currency-switcher'); ?></button>
                                                </td>
                                          </tr>
                                    <?php endforeach; endif; ?>
                        </tbody>
                  </table>
                  <p><button type="button" class="button"
                              id="shscs-add-currency"><?php echo esc_html('+ ' . __('Add Currency', 'shills-simple-currency-switcher')); ?></button>
                  </p>
            </div>
            <?php
      }

	private function render_currencies_tab(): void
      {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page tab selection, no nonce needed
            $tab = isset($_GET["tab"]) ? sanitize_text_field(wp_unslash($_GET["tab"])) : "general";
            echo '<form method="post" action="options.php">';
            echo '<input type="hidden" name="shscs_active_tab" value="' . esc_attr($tab) . '">';
            settings_fields($this->group_currencies); // Independent group
            $this->render_currencies();
            echo '<p class="submit">';
            submit_button(__("Save Currency Settings", "shills-simple-currency-switcher"), "primary", "submit", false);
            echo ' <button type="button" id="shscs-update-rates" class="button button-secondary">';
            esc_html_e("Update Exchange Rates", "shills-simple-currency-switcher");
            echo '</button></p></form>';
            $this->render_cache_clear_form();
      }

      private function render_multilingual(): void
      {
            $map = get_option("shscs_lang_currency_map", []);
            $sync = get_option("shscs_lang_currency_sync", "1") === "1";
            $override = get_option("shscs_allow_user_override", "0") === "1";
            $geo = get_option("shscs_geo_detection", "0") === "1";
            ?>
            <table class="form-table">
                  <tr>
                        <th><?php esc_html_e("Plugin Status", "shills-simple-currency-switcher"); ?></th>
                        <td>
                              <?php
                              $wpml = defined("SHSCS_WPML_ACTIVE") && SHSCS_WPML_ACTIVE;
                              $pll = defined("SHSCS_POLYLANG_ACTIVE") && SHSCS_POLYLANG_ACTIVE;
                              if ($wpml): ?>
                                    <span style="color:green">WPML
                                          <?php echo esc_html(defined("ICL_SITEPRESS_VERSION") ? ICL_SITEPRESS_VERSION : ""); ?></span>
                              <?php elseif ($pll): ?>
                                    <span style="color:green">Polylang
                                          <?php echo esc_html(defined("POLYLANG_VERSION") ? POLYLANG_VERSION : ""); ?></span>
                              <?php else: ?>
                                    <span
                                          style="color:#999"><?php esc_html_e("No multilingual plugin detected", "shills-simple-currency-switcher"); ?></span>
                              <?php endif; ?>
                        </td>
                  </tr>
                  <tr>
                        <th><?php esc_html_e("Sync Settings", "shills-simple-currency-switcher"); ?></th>
                        <td>
                              <label><input type="checkbox" name="shscs_lang_currency_sync" value="1" <?php checked($sync); ?>>
                                    <?php esc_html_e("Auto-switch currency with language", "shills-simple-currency-switcher"); ?></label>
                        </td>
                  </tr>
                  <tr>
                        <th><?php esc_html_e("User Override", "shills-simple-currency-switcher"); ?></th>
                        <td>
                              <label><input type="checkbox" name="shscs_allow_user_override" value="1" <?php checked($override); ?>>
                                    <?php esc_html_e("Allow manual currency selection", "shills-simple-currency-switcher"); ?></label>
                        </td>
                  </tr>
                  <tr>
                        <th><?php esc_html_e("GeoIP Detection", "shills-simple-currency-switcher"); ?></th>
                        <td>
                              <label><input type="checkbox" name="shscs_geo_detection" value="1" <?php checked($geo); ?>>
                                    <?php esc_html_e("Detect location for initial currency", "shills-simple-currency-switcher"); ?></label>
                        </td>
                  </tr>
            </table>

            <h3><?php esc_html_e("Language-Currency Mapping", "shills-simple-currency-switcher"); ?></h3>
            <table class="widefat striped">
                  <thead>
                        <tr>
                              <th><?php esc_html_e("Language", "shills-simple-currency-switcher"); ?></th>
                              <th><?php esc_html_e("Default Currency", "shills-simple-currency-switcher"); ?></th>
                        </tr>
                  </thead>
                  <tbody>
                        <?php foreach ($this->get_languages() as $code => $name): ?>
                              <tr>
                                    <td><?php echo esc_html("{$name} ({$code})"); ?></td>
                                    <td>
                                          <select name="shscs_lang_currency_map[<?php echo esc_attr($code); ?>]">
                                                <option value=""><?php esc_html_e("— Select —", "shills-simple-currency-switcher"); ?>
                                                </option>
                                                <?php foreach ($this->get_iso_currencies() as $curr => $curr_name): ?>
                                                      <option value="<?php echo esc_attr($curr); ?>" <?php selected($map[$code] ?? "", $curr); ?>><?php echo esc_html($curr); ?></option>
                                                <?php endforeach; ?>
                                          </select>
                                    </td>
                              </tr>
                        <?php endforeach; ?>
                  </tbody>
            </table>
            <?php
      }

	private function render_multilingual_tab(): void
	{
		echo '<div class="notice notice-info" style="margin:10px 0;padding:10px;border-left:4px solid #007cba">';
echo '<p><strong>💡 ' . esc_html__("Tip", "shills-simple-currency-switcher") . '</strong>: ';
echo esc_html__("When \"Auto-sync\" is enabled, switching languages will automatically switch the currency.", "shills-simple-currency-switcher");
echo '</p>';
echo '<p>• ' . esc_html__("Manually selected currencies will be respected (for 30 days)", "shills-simple-currency-switcher") . '</p>';
echo '<p>• ' . esc_html__("To reset user selection, clear browser cookies or wait 30 days", "shills-simple-currency-switcher") . '</p>';
echo '</div>';


            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page tab selection, no nonce needed
            $tab = isset($_GET["tab"]) ? sanitize_text_field(wp_unslash($_GET["tab"])) : "general";

            echo '<form method="post" action="options.php">';
            echo '<input type="hidden" name="shscs_active_tab" value="' . esc_attr($tab) . '">';
            settings_fields($this->group_multilingual); // Independent group
            $this->render_multilingual();
            echo '<p class="submit">';
            submit_button(__("Save Multilingual Settings", "shills-simple-currency-switcher"), "primary", "submit", false);
            echo '</p></form>';

           

            $this->render_cache_clear_form();


      }

      /**
       * Common cache clearing form (shared by three tabs)
       */
      private function render_cache_clear_form(): void
      {
            echo '<div class="shscs-form-footer"><form method="post">';
            wp_nonce_field('shscs_clear_cache_nonce');
            echo '<input type="hidden" name="shscs_clear_cache" value="1">';
            echo '<button type="submit" class="button button-secondary" onclick="return confirm(\'' . esc_attr__('Are you sure you want to clear all caches?', 'shills-simple-currency-switcher') . '\')">';
            esc_html_e('Clear All Caches', 'shills-simple-currency-switcher');
            echo '</button></form></div>';
      }

      // ==================== Helper methods ====================

      private function get_api_providers(): array
      {
            $providers = [
                  'none' => ['name' => 'None - Use Manual Rates', 'key' => false, 'free' => true, 'manual' => true],
                  'exchangerate-api' => ['name' => 'ExchangeRate-API.com', 'key' => false, 'free' => true, 'manual' => false],
                  'openexchangerates' => ['name' => 'Open Exchange Rates', 'key' => true, 'free' => true, 'manual' => false],
            ];
            if (function_exists('__') && isset($providers['none'])) {
                  $providers['none']['name'] = __('None - Use Manual Rates', 'shills-simple-currency-switcher');
            }
            return apply_filters("shscs_api_providers", $providers);
      }

      private function get_iso_currencies(): array
      { /* Unchanged, omitted */
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

      private function get_currency_symbols(): array
      {
            return SHSCS_Currency::get_default_symbols();
      }

      private function get_languages(): array
      {
            $langs = [];
            if (defined("SHSCS_WPML_ACTIVE") && SHSCS_WPML_ACTIVE && function_exists("icl_get_languages")) {
                  foreach (icl_get_languages() as $lang)
                        $langs[$lang["language_code"]] = $lang["native_name"];
            } elseif (defined("SHSCS_POLYLANG_ACTIVE") && SHSCS_POLYLANG_ACTIVE && function_exists("pll_languages_list")) {
                  try {
                        $slugs = pll_languages_list(['fields' => 'slug']);
                        $names = pll_languages_list(['fields' => 'name']);
                        if (is_array($slugs) && is_array($names) && count($slugs) === count($names)) {
                              for ($i = 0; $i < count($slugs); $i++)
                                    $langs[$slugs[$i]] = $names[$i];
                        } elseif (function_exists('pll_the_languages')) {
                              $all = pll_the_languages(['raw' => 1]);
                              if (is_array($all))
                                    foreach ($all as $l)
                                          if (isset($l['slug'], $l['name']))
                                                $langs[$l['slug']] = $l['name'];
                        }
                  } catch (Exception $e) {
                        // Silently fail
                  }
            }
            if (empty($langs))
                  $langs = ["en" => "English", "zh-hans" => "简体中文", "de" => "Deutsch", "ja" => "日本語"];
            return $langs;
      }

      /**
       * Enhanced: return existing value on failure to avoid clearing
       */
      public function sanitize_lang_currency_map($value): array
      {
            if (is_string($value) && !empty($value)) {
                  $decoded = json_decode($value, true);
                  if (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                        $value = $decoded;
            }
            if (!is_array($value))
                  return get_option('shscs_lang_currency_map', []);

            $sanitized = [];
            foreach ($value as $lang_code => $currency_code) {
                  $lang_code = sanitize_text_field($lang_code);
                  if (!empty($currency_code))
                        $sanitized[$lang_code] = sanitize_text_field($currency_code);
            }
            return $sanitized;
      }

      /**
       * Enhanced: compatible with JSON + normalize enabled to 1/0 + return existing value on failure
       */
      public function sanitize_currencies($value): array
      {
            if (is_string($value) && !empty($value)) {
                  $decoded = json_decode($value, true);
                  if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                        return get_option('shscs_currencies', []);
                  }
                  $value = $decoded;
            }
            if (!is_array($value))
                  return get_option('shscs_currencies', []);

            $sanitized = [];
            $order = 0;
            foreach (array_values($value) as $currency) {
                  if (!is_array($currency))
                        continue;
                  $code = sanitize_text_field($currency['code'] ?? '');
                  if (empty($code))
                        continue;

                  $symbol = sanitize_text_field($currency['symbol'] ?? '');

                  // New: try to get from predefined list if symbol is empty or doesn't match currency code
                  $predefined_symbols = $this->get_currency_symbols();
                  if (empty($symbol) || !isset($predefined_symbols[$code])) {
                        // Symbol empty or no predefined symbol for this currency, check symbol_custom
                        if (!empty($currency['symbol_custom'])) {
                              $symbol = sanitize_text_field($currency['symbol_custom']);
                        }
                  } elseif ($symbol !== $predefined_symbols[$code]) {
                        // Symbol doesn't match predefined, check if custom
                        if (!in_array($symbol, $predefined_symbols, true)) {
                              // Custom symbol, keep as is
                        } else {
                              // Symbol is predefined for other currency, reset to current currency's predefined symbol
                              $symbol = $predefined_symbols[$code];
                        }
                  }

                  // if (empty($symbol) && !empty($currency['symbol_custom'])) {
                  //       $symbol = sanitize_text_field($currency['symbol_custom']);
                  // }

                  $sanitized[] = [
                        'code' => $code,
                        'symbol' => $symbol,
                        'rate' => number_format(floatval($currency['rate'] ?? 1), 6, '.', ''),
                        'position' => sanitize_text_field($currency['position'] ?? 'left'),
                        // Store as 1/0 integer to avoid type comparison issues
                        'enabled' => !empty($currency['enabled']) ? 1 : 0,
                        'order' => $order++,
                  ];
            }
            return array_values($sanitized);
      }

      /**
       * Clear all plugin and WooCommerce caches
       */
      private function clear_all_caches(): void
      {
            delete_transient('shscs_rates_cache');
            delete_transient('shscs_currency_data');
            if (function_exists('wp_cache_flush')) {
                  wp_cache_flush();
            }

            if (class_exists('WooCommerce')) {
                  // phpcs:disable WordPress.DB.DirectDatabaseQuery
                  global $wpdb;
                  $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_%'");
                  $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wc_%'");
                  $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_product_%'");
                  // phpcs:enable WordPress.DB.DirectDatabaseQuery
                  if (WC()->session) {
                        WC()->session->set('cart_totals', null);
                        WC()->session->set('cart', null);
                  }
                  if (function_exists('wc_delete_product_transients'))
                        wc_delete_product_transients();
            }
            wp_cache_delete('alloptions', 'options');
				do_action('shscs_all_caches_cleared');
	}

      /**
       * Determine if symbol is custom (not predefined)
       *
       * @param string $symbol Current symbol
       * @param string $code Current currency code
       * @return bool
       */
      private function is_custom_symbol(string $symbol, string $code): bool
      {
            // Empty symbol is not custom
            if (empty($symbol)) {
                  return false;
            }

            // Get predefined symbols
            $predefined = $this->get_currency_symbols();

            // If this currency has predefined symbol and current matches → not custom
            if (isset($predefined[$code]) && $symbol === $predefined[$code]) {
                  return false;
            }

            // If symbol is in other currency's predefined list → probably wrong selection, not custom
            if (in_array($symbol, $predefined, true)) {
                  return false;
            }

            // Other cases are custom symbols
            return true;
      }

}