=== Shills Simple Currency Switcher ===
Author: Shawn Hills
Author URI: https://profiles.wordpress.org/shawnhills
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: shills-simple-currency-switcher
Requires at least: 6.0
Tested up to:      7.0
Requires PHP:      7.4
Stable tag:       1.0.0
Domain Path: /languages

Tags:   currency switcher, woocommerce, exchange rates, multilingual, multi-currency

License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Simple and efficient currency switcher with multi-currency management, real-time exchange rates, WooCommerce integration, and WPML/Polylang support.

== Description ==

Shills Simple Currency Switcher is a powerful yet lightweight currency switcher plugin for WordPress that allows your visitors to switch between multiple currencies seamlessly. Perfect for e-commerce stores, international businesses, and multilingual websites.

**Key Features:**

* **Multi-Currency Management** - Add unlimited currencies with custom symbols, positions, and exchange rates
* **Real-Time Exchange Rates** - Automatic rate updates from ExchangeRate-API.com or Open Exchange Rates
* **WooCommerce Integration** - Full compatibility with WooCommerce product prices, cart, and checkout
* **WPML & Polylang Support** - Automatic currency switching based on language selection
* **Language-Currency Mapping** - Map specific currencies to languages (e.g., English → USD, Chinese → CNY)
* **GeoIP Detection** - Auto-detect visitor location and set appropriate currency
* **Multiple Display Styles** - Choose between dropdown or button display formats
* **REST API** - Complete REST API for currency operations and frontend integration
* **Widget & Shortcode** - Easy implementation via widget or `[shscs_switcher]` shortcode
* **Theme Integration** - Auto-detect theme colors or set custom colors
* **User Choice Persistence** - Remember visitor currency selection for 30 days
* **Cache Compatible** - Works with WP Rocket, W3 Total Cache, and other caching plugins
* **Modern JavaScript** - Built with Webpack and modern ES6+ JavaScript

**Currency Features:**

* Custom currency symbols and positions (left, right, left_space, right_space)
* Configurable decimal places per currency
* Manual or automatic exchange rate updates
* Base currency support with rate normalization
* Currency validation and error handling

**WooCommerce Features:**

* Automatic price conversion on product pages
* Cart and checkout price formatting
* Order currency metadata storage
* Currency display in admin order details
* AJAX fragment support for dynamic updates

**Multilingual Features:**

* Automatic currency sync when switching languages
* Respect user manual currency selection
* Configurable language-currency mappings
* Compatible with WPML and Polylang language switchers

== Installation ==

1. Upload the `shills-simple-currency-switcher` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Settings → Currency Switcher** to configure currencies and settings
4. Add the currency switcher to your site using:
   * Widget: Go to **Appearance → Widgets** and add "Currency Switcher"
   * Shortcode: `[shscs_switcher]` in posts, pages, or templates
   * PHP: `<?php echo do_shortcode('[shscs_switcher]'); ?>`

== Frequently Asked Questions ==

= How do I add a new currency? =

Go to **Settings → Currency Switcher → Currencies** and click "Add Currency". Enter the currency code, symbol, exchange rate, and other settings.

= How do I enable automatic exchange rate updates? =

Go to **Settings → Currency Switcher → General Settings** and select an API provider (ExchangeRate-API.com or Open Exchange Rates). You can also set the auto-update frequency.

= Does this plugin work with WooCommerce? =

Yes! The plugin fully integrates with WooCommerce and automatically converts product prices, cart totals, and checkout amounts.

= Can I sync currency with language selection? =

Yes! Go to **Settings → Currency Switcher → Multilingual Settings** and enable "Auto Sync". Then map languages to currencies (e.g., English → USD, Spanish → EUR).

= What multilingual plugins are supported? =

WPML and Polylang are fully supported with automatic currency switching.

= Can I display the switcher as buttons instead of dropdown? =

Yes! Use the shortcode `[shscs_switcher display="buttons"]` or select "Buttons" in the widget settings.

= How do I set a custom color for the switcher? =

Go to **Settings → Currency Switcher → Appearance** and select "Custom" theme mode, then choose your color.

= Is this plugin compatible with caching plugins? =

Yes! The plugin adds `Vary: Cookie` headers and works with WP Rocket, W3 Total Cache, and other caching plugins.

== Screenshots ==

1. Currency management interface with add/edit/delete functionality
2. Exchange rate settings with multiple API providers
3. Multilingual settings with language-currency mapping
4. Frontend switcher showing dropdown and button styles
5. WooCommerce integration showing converted prices
6. Widget configuration in the block editor

== Changelog ==

= 1.0.0 =
* Initial release
* Multi-currency management with unlimited currencies
* Real-time exchange rate updates from multiple providers
* WooCommerce integration with price conversion
* WPML and Polylang multilingual support
* Language-currency automatic sync
* REST API for all currency operations
* Widget and shortcode support
* GeoIP detection for automatic currency selection
* Modern JavaScript with Webpack build system
* Cache compatibility with major caching plugins

== Upgrade Notice ==

= 1.0.0 =
First release - no upgrade needed!

== Arbitrary section ==

**Development**

This plugin is built with modern development practices:

* PHP 7.4+ with strict types
* WordPress Coding Standards
* Webpack for asset building
* ES6+ JavaScript
* SCSS for styling
* REST API architecture
* Comprehensive error handling and logging


== Third Party Service ==

This plugin uses third-party services for exchange rate data:

**ExchangeRate-API.com**
* Service URL: https://www.exchangerate-api.com
* Terms of Service: https://www.exchangerate-api.com/terms
* Privacy Policy: https://www.exchangerate-api.com/privacy

**Open Exchange Rates**
* Service URL: https://openexchangerates.org
* Terms of Service: https://openexchangerates.org/terms
* Privacy Policy: https://openexchangerates.org/privacy

These services are optional and can be disabled by selecting "Manual Rates" in the settings. No personal data is sent to these services - only the base currency code (e.g., "USD") is requested.

== Filter Reference ==

* `shscs_currencies` - Filter the currencies array
* `shscs_exchange_rates` - Filter exchange rates before storage
* `shscs_price_format` - Filter price formatting
* `shscs_api_providers` - Filter available API providers
* `shscs_geo_country_currency_map` - Filter GeoIP country-currency mapping

== Action Reference ==

* `shscs_currency_switched` - Fires when currency is switched
* `shscs_rates_updated` - Fires when exchange rates are updated
* `shscs_language_currency_synced` - Fires when language-currency sync occurs
* `shscs_activated` - Fires on plugin activation
* `shscs_deactivated` - Fires on plugin deactivation
* `shscs_components_loaded` - Fires after all components are loaded

== REST API Endpoints ==

All endpoints are under `/wp-json/shscs/v1/`:

* `GET /settings` - Get currency switcher settings
* `POST /switch` - Switch currency (sets cookie)
* `POST /update-rates` - Update exchange rates from API
* `GET /theme-color` - Get current theme color
* `GET /lang-map` - Get language-currency mapping
* `POST /sync-lang` - Sync language with currency
* `GET /currencies` - Get all currencies (admin)
* `POST /currencies` - Add new currency (admin)
* `GET /currency/{code}` - Get single currency (admin)
* `POST /currency/{code}` - Update currency (admin)
* `DELETE /currency/{code}` - Delete currency (admin)

== Shortcode Parameters ==

`[shscs_switcher]`

* `display` - Display style: `dropdown` (default) or `buttons`
* `theme` - Theme mode: `auto` (default), `light`, `dark`, or `custom`

Example: `[shscs_switcher display="buttons" theme="dark"]`

== Requirements ==

* PHP 7.4 or higher
* WordPress 5.8 or higher
* WooCommerce 6.0 or higher (optional, for e-commerce features)
* WPML or Polylang (optional, for multilingual features)

== Credits ==

Built with love by Shawn Hills

Special thanks to:
* The WordPress community
* Contributors and testers
* ExchangeRate-API.com and Open Exchange Rates for exchange rate data
