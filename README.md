# Shills Simple Currency Switcher

> A lightweight yet feature-rich WordPress currency switcher plugin with multi-currency management, real-time exchange rates, WooCommerce integration, and WPML/Polylang compatibility.

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple?logo=php)](https://php.net/)
[![Node](https://img.shields.io/badge/Node-18%2B-green?logo=node.js)](https://nodejs.org/)
[![License](https://img.shields.io/badge/License-GPLv2-red)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Features

### 🪙 Multi-Currency Management
- Unlimited currencies with custom code, symbol, symbol position, and decimal places
- Built-in symbol lookup table for 50+ currencies
- Drag-and-drop ordering for currency display

### 🔄 Real-Time Exchange Rates
- Dual API provider support: **ExchangeRate-API.com** and **Open Exchange Rates**
- WP Cron scheduled auto-updates with manual refresh option
- Pure manual mode available

### 🛒 Deep WooCommerce Integration
- Automatic price conversion for products, cart, and checkout
- Currency info stored in orders, viewable in admin
- Compatible with AJAX fragment updates and WC Session synchronization

### 🌍 Multilingual Auto-Sync
- Full compatibility with **WPML** and **Polylang**
- Auto-switch currency on language change (configurable mapping table)
- Respects manual user selections — never overrides without consent

### 📍 GeoIP Detection
- Automatically detects visitor location and sets local currency
- Supports Cloudflare, MaxMind, WooCommerce Geolocation, and Accept-Language detection

### 🎨 Frontend Display
- Two display styles: Dropdown / Buttons
- Four theme modes: `auto` / `light` / `dark` / `custom`
- Dual integration: Widget + Shortcode

### 🔌 REST API
- Full REST API for frontend interaction and admin management
- All switching operations without page refresh

### 💾 Cache Compatible
- User choice persisted via 30-day cookie
- Compatible with WP Rocket, W3 Total Cache, and other major caching plugins

---

## Quick Start

### Installation

1. Upload the `shills-simple-currency-switcher` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Settings → Currency Switcher** to configure currencies and options

### Usage

**Shortcode:**

```
[shscs_switcher]
```

With parameters:

```
[shscs_switcher display="buttons" theme="dark"]
```

**PHP Template:**

```php
echo do_shortcode( '[shscs_switcher]' );
```

**Public Functions:**

```php
// Get current currency
$currency = shscs_get_currency();

// Convert price
$converted = shscs_convert( 99.99, 'EUR' );

// Format price
echo shscs_format_price( 99.99, 'JPY' );
```

**Widget:**

Go to **Appearance → Widgets** and drag "Currency Switcher" into your sidebar.

---

## REST API

All endpoints are under `/wp-json/shscs/v1/`:

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/settings` | GET | Public | Get frontend settings |
| `/switch` | POST | Public | Switch currency |
| `/update-rates` | POST | Admin | Update exchange rates |
| `/theme-color` | GET | Public | Get theme color |
| `/lang-map` | GET | Public | Get language-currency mapping |
| `/sync-lang` | POST | Public | Language-currency synchronization |
| `/sync-currency` | POST | Public | Fine-grained currency sync |
| `/currencies` | GET/POST | Admin | List / Add currencies |
| `/currency/{code}` | GET/POST/DELETE | Admin | Get / Update / Delete |

---

## Hooks Reference

### Filters

| Filter | Description |
|--------|-------------|
| `shscs_currencies` | Filter the currencies array |
| `shscs_exchange_rates` | Filter exchange rate data |
| `shscs_price_format` | Filter price formatting |
| `shscs_api_providers` | Filter API providers |
| `shscs_geo_country_currency_map` | Filter GeoIP country-currency map |

### Actions

| Action | Description |
|--------|-------------|
| `shscs_currency_switched` | Fires when currency is switched |
| `shscs_rates_updated` | Fires when rates are updated |
| `shscs_language_currency_synced` | Fires when language-currency is synced |
| `shscs_activated` | Fires on plugin activation |
| `shscs_deactivated` | Fires on plugin deactivation |
| `shscs_components_loaded` | Fires after all components are loaded |

---

## Development

### Requirements

- **PHP** 7.4+
- **Node.js** 18+
- **npm** 9+
- **WordPress** 6.0+

### Local Development

```bash
# Clone the repository
git clone <repo-url> shills-simple-currency-switcher

# Install dependencies
cd shills-simple-currency-switcher
npm install

# Development mode (auto watch)
npm start

# Production build
npm run build

# Linting
npm run lint:js
npm run lint:css

# Generate translation template
npm run make-pot
```

### Tech Stack

| Layer | Technology |
|-------|-------------|
| **Backend** | PHP 7.4+, strict types, Singleton pattern |
| **Frontend** | ES6+ modules, `@wordpress/scripts` |
| **Styling** | SCSS |
| **Build** | Webpack 5 |
| **i18n** | WordPress i18n + `.pot` |

### Project Structure

```
shills-simple-currency-switcher/
├── shills-simple-currency-switcher.php   # Main plugin entry
├── readme.txt                            # WordPress.org format readme
├── README.md                             # GitHub readme (this file)
├── package.json                          # NPM config & build scripts
├── webpack.config.js                     # Webpack configuration
│
├── includes/                             # PHP core classes
│   ├── functions.php                     # Public helper functions
│   ├── class-shscs-currency.php          # Currency data management
│   ├── class-shscs-api.php               # Exchange rate API interface
│   ├── class-shscs-rest.php              # REST API endpoints
│   ├── class-shscs-admin.php             # Admin settings UI
│   ├── class-shscs-assets.php            # Frontend asset loading
│   ├── class-shscs-frontend.php          # Shortcode/Widget rendering
│   ├── class-shscs-widget.php            # Widget registration
│   ├── class-shscs-woo.php               # WooCommerce integration
│   ├── class-shscs-multilingual.php      # Multilingual integration
│   └── class-shscs-geo.php              # GeoIP detection
│
├── src/                                  # JavaScript source
│   ├── admin/                            # Admin dashboard
│   ├── frontend/                         # Frontend switcher
│   ├── multilingual/                     # Multilingual bridge
│   └── shared/                           # Shared modules
│
├── assets/dist/                          # Webpack build output
├── languages/                            # Translation files
└── node_modules/                         # Frontend dependencies
```

### Architecture

- **Conditional Loading**: WooCommerce, multilingual, and GeoIP components load only when the corresponding plugin is active
- **Singleton Pattern**: All core classes use the Singleton pattern, managed centrally through the `$components` array
- **REST API First**: Frontend switches currency via REST API, no page refresh required
- **Cache Friendly**: 30-day cookie + URL parameters + WP Rocket compatibility

---

## FAQ

<details>
<summary><b>How do I add a new currency?</b></summary>

Go to **Settings → Currency Switcher → Currencies**, click "Add Currency", and fill in the currency code, symbol, exchange rate, and other details.
</details>

<details>
<summary><b>How do I enable automatic exchange rate updates?</b></summary>

Go to **Settings → Currency Switcher → General Settings**, select an API provider, configure your API key, and set the auto-update frequency.
</details>

<details>
<summary><b>Which multilingual plugins are supported?</b></summary>

Both WPML and Polylang are fully supported. Go to **Multilingual Settings** to configure the language-currency mapping table.
</details>

<details>
<summary><b>How do I customize the switcher appearance?</b></summary>

Override the following CSS classes to customize the look and feel:

- `.shscs-switcher` — Container
- `.shscs-switcher--dropdown` / `.shscs-switcher--buttons` — Display modes
- `.shscs-switcher__select` — Dropdown select
- `.shscs-switcher__button` — Button

</details>

<details>
<summary><b>Which caching plugins are compatible?</b></summary>

Compatible with WP Rocket, W3 Total Cache, and other major caching plugins. Currency information is passed via URL parameters, ensuring cached pages still display the correct currency.
</details>

---

## License

[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Author

**Shawn Hills**

- WordPress.org: [profiles.wordpress.org/shawnhills](https://profiles.wordpress.org/shawnhills)

---

## Credits

- [ExchangeRate-API.com](https://www.exchangerate-api.com) — Exchange rate data
- [Open Exchange Rates](https://openexchangerates.org) — Exchange rate data
- WordPress community contributors and testers
