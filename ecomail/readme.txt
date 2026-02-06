=== Ecomail ===

Contributors: wpify, vasikgreif, mejta, ecomailcz
Tags: email, marketing, newsletter, ecomail, woocommerce, emailing
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 2.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Official plugin to connect your WooCommerce platform with Ecomail.cz application.

== Description ==

Official plugin to connect your WooCommerce platform with Ecomail.cz application.

### Features:

* Add new customers to your selected contacts list
* Customer's data transfer selection
* Add new orders to contacts in your list (only for Marketer+ accounts)
* Update customer's data from an order (only for Marketer+ accounts)
* Add a tracking code for behaviour tracking on your site (only for Marketer+ accounts)
* Abandoned cart tracking for automations in Ecomail application (only for Marketer+ accounts)

### Support

If you have any questions, contact us at support@ecomail.cz or via chat directly in the application.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ecomail` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings → Ecomail screen to configure the plugin
4. Paste your API key to the API key field
5. Configure preferred options
6. Save changes

== Frequently Asked Questions ==

== Screenshots ==

== Changelog ==
= 2.4.2 =
* Change transaction item price from unit price to total price
* Change category parameter to categories array (supports multiple categories per product)
* Fix transaction amount to total including tax

= 2.4.1 =
* Fix double opt-in after Woo order
* Fix adding wp_newsletter tag from the account subscription
* Fix textdomain
* Add better email validation check

= 2.4.0 =
* Add bulk update existing orders functionality
* Add subscription preference tracking for orders and users
* Add info about subscription into Customer account"
* Add toggle button to subscribe/unsubscribe into Customer accoun
* Add webhooks to update subscription info
* Add process logging using WooCommerce logger
* Fix bulk import existing orders

= 2.3.2 =
* Fix set tags to not overwrite existing

= 2.3.1 =
* Add tags to users when creating an order and/or signing up for the newsletter
* Change opt-in checkbox to opt-out
* Fix users data in bulk imports
* Update dependencies

= 2.3.0 =
* Fix deploy
* Mininum PHP version increased to 8.1

= 2.2.1 =
* Fix deploy

= 2.2.0 =
* Add bulk transaction import
* Add more options for checkbox on checkout
* Add WooCommerce tags to orders
* Update dependencies
* Various fixes and improvements


= 2.1.6 =
* Fix category for variable products

= 2.1.5 =
* Declare HPOS support

= 2.1.4 =
* Handle no email on cart tracking

= 2.1.3 =
* Full size image in cart tracking

= 2.1.2 =
* Add support for subscription on pay for order page

= 2.1.1 =
* Add filter for options value

= 2.1.0 =
* Add settings for disabling tracking by cookie

= 2.0.0 =
* Migrate to new core
* Add option to send customer phone
* Add tracking last viewed product
* Add bulk upload all existing customers
* Minimum required PHP version increased to 7.4

= 1.0.7 =
* Fix order line item category

= 1.0.4 - 1.0.6 =
* Hotfixes

= 1.0.3 =
* Fix fatal error when session does not exist

= 1.0.2 =
* Plugin branding added

= 1.0.1 =
* Plugin branding added

= 1.0.0 =
* Initial version

== Upgrade Notice ==
