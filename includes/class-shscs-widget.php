<?php
/**
 * Currency Switcher Widget
 *
 * @package ShillsSimpleCurrencySwitcher
 * @since   1.0.0
 */

declare(strict_types=1);

if (!defined("ABSPATH")) {
	exit();
}

/**
 * Class SHSCS_Widget
 */
class SHSCS_Widget extends WP_Widget
{
	public function __construct()
	{
		// Ensure translations are loaded before using __()
		$title = function_exists("__") ? __("Currency Switcher", "shills-simple-currency-switcher") : "Currency Switcher";
		$description = function_exists("__") ? __("Display currency switcher in sidebar", "shills-simple-currency-switcher") : "Display currency switcher in sidebar";
		
		parent::__construct(
			"shscs_currency_switcher",
			$title,
			[
				"description" => $description,
				"classname" => "shscs_widget",
			]
		);
	}

	/**
	 * Widget output
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance settings.
	 * @return void
	 */
	public function widget($args, $instance)
	{
		$title = !empty($instance["title"]) ? $instance["title"] : "";
		$display = !empty($instance["display"])
			? $instance["display"]
			: "dropdown";

		echo $args["before_widget"]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress widget args are theme-provided

		if ($title) {
			echo $args["before_title"] . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress widget args are theme-provided
				esc_html($title) .
				$args["after_title"]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress widget args are theme-provided
		}

		$frontend = SHSCS_Frontend::get_instance();
		echo $frontend->render_switcher_shortcode([ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built from safe data
			"display" => esc_attr( $display ),
			"theme" => "auto",
		]);

		echo $args["after_widget"]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress widget args are theme-provided
	}

	/**
	 * Widget admin form
	 *
	 * @param array $instance Widget instance settings.
	 * @return void
	 */
	public function form($instance)
	{
		$title = $instance["title"] ?? "";
		$display = $instance["display"] ?? "dropdown";
		?>
		<p>
			<label for="<?php echo esc_attr($this->get_field_id("title")); ?>">
				<?php esc_html_e(
					"Title:",
					"shills-simple-currency-switcher"
				); ?>
			</label>
			<input class="widefat" id="<?php echo esc_attr(
				$this->get_field_id("title")
			); ?>"
				   name="<?php echo esc_attr(
				   	$this->get_field_name("title")
				   ); ?>"
				   type="text" value="<?php echo esc_attr($title); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr(
				$this->get_field_id("display")
			); ?>">
				<?php esc_html_e(
					"Display Style:",
					"shills-simple-currency-switcher"
				); ?>
			</label>
			<select class="widefat" id="<?php echo esc_attr(
				$this->get_field_id("display")
			); ?>"
					name="<?php echo esc_attr(
						$this->get_field_name("display")
					); ?>">
				<option value="dropdown" <?php selected(
					$display,
					"dropdown"
				); ?>>
					<?php esc_html_e(
						"Dropdown",
						"shills-simple-currency-switcher"
					); ?>
				</option>
				<option value="buttons" <?php selected($display, "buttons"); ?>>
					<?php esc_html_e(
						"Buttons",
						"shills-simple-currency-switcher"
					); ?>
				</option>
			</select>
		</p>
		<?php
	}

	/**
	 * Update widget instance
	 *
	 * @param array $new_instance New widget settings.
	 * @param array $old_instance Old widget settings.
	 * @return array Updated widget settings.
	 */
	public function update($new_instance, $old_instance)
	{
		return [
			"title" => sanitize_text_field($new_instance["title"] ?? ""),
			"display" => in_array($new_instance["display"] ?? "", [
				"dropdown",
				"buttons",
			])
				? $new_instance["display"]
				: "dropdown",
		];
	}
}
