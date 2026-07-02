<?php
/**
 * Frontend Display
 *
 * @package ShillsSimpleCurrencySwitcher
 * @since   1.0.0
 */

declare(strict_types=1);

if (!defined("ABSPATH")) {
      exit();
}



/**
 * Class SHSCS_Frontend
 */
class SHSCS_Frontend
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
            add_action("init", [$this, "init"]);
      }

      public function init(): void
      {
            add_shortcode("shscs_switcher", [
                  $this,
                  "render_switcher_shortcode",
            ]);
            add_action("wp_footer", [$this, "maybe_inject_switcher"]);
      }

      public function render_switcher_shortcode(array $atts): string
      {
            $atts = shortcode_atts(
                  [
                        "display" => "dropdown",
                        "theme" => "auto",
                  ],
                  $atts,
                  "shscs_switcher",
            );

            return $this->get_switcher_html($atts["display"], $atts["theme"]);
      }

      public function maybe_inject_switcher(): void
      {
            if (!get_option("shscs_show_in_footer", false)) {
                  return;
            }

            echo $this->get_switcher_html("dropdown", "auto"); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built from safe data
      }

      private function get_switcher_html(string $display, string $theme): string
      {
            $currencies =
                  SHSCS_Main::get_instance()
                        ->get_component("currency")
                        ?->get_frontend_data() ?? [];
            $current = shscs_get_currency();
            
            // Get default currency symbols from SHSCS_Currency class
            $currency_symbols = SHSCS_Currency::get_default_symbols();

            ob_start();
            ?>
        <div class="shscs-switcher shscs-display-<?php echo esc_attr(
              $display,
        ); ?> shscs-theme-<?php echo esc_attr($theme); ?>"
             data-display="<?php echo esc_attr($display); ?>">

            <?php if ($display === "dropdown"): ?>
                <select class="shscs-select" aria-label="<?php esc_attr_e(
                      "Select Currency",
                      "shills-simple-currency-switcher",
                ); ?>">
                    <?php foreach ($currencies as $c):
                        // Use default symbol if available, fallback to user symbol
                        $symbol = $currency_symbols[$c["code"]] ?? $c["symbol"] ?? $c["code"];
                    ?>
                    <option value="<?php echo esc_attr(
                          $c["code"],
                    ); ?>" <?php selected($current, $c["code"]); ?>>
                        <?php echo esc_html("{$symbol} {$c["code"]}"); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <div class="shscs-buttons">
                    <?php foreach ($currencies as $c):
                        // Use default symbol if available, fallback to user symbol
                        $symbol = $currency_symbols[$c["code"]] ?? $c["symbol"] ?? $c["code"];
                    ?>
                    <button type="button"
                            class="shscs-btn <?php echo $current === $c["code"]
                                  ? "active"
                                  : ""; ?>"
                            data-currency="<?php echo esc_attr($c["code"]); ?>"
                            aria-pressed="<?php echo $current === $c["code"]
                                  ? "true"
                                  : "false"; ?>">
                        <span class="shscs-symbol"><?php echo esc_html($symbol); ?></span>
                        <span class="shscs-code"><?php echo esc_html($c["code"]); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
      }
}
