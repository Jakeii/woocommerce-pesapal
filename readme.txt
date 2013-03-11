=== Pesapal Gateway for Woocommerce ===
Contributors: Jakeii
Donate link: http://jakeii.github.com/woocommerce-pesapal/
Tags: pesapal, woocommerce, ecommerce, gateway, payment
Requires at least: 3.3
Tested up to: 3.5.1
Stable tag: 0.4 beta
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Simple and easy to use plugin for pesapal.com payment gateway.

== Description ==

Simple and easy to use plugin for pesapal.com payment gateway.

**This plugin is in BETA and I would not recommend using it in a production environment without thorough testing.**

= Please Note =
There is an option to use an iframe rather than redirecting to Pesapal, although the iframe will use SSL to show the payment page, customers paying won't be able to see this. I recommend that you use an SSL Certificate and use the "force secure checkout" option in Woocommerce (To show the https padlock in the url or status bar), and ensure that your customers know that any details they enter will be safe.

== Installation ==

1. Upload `woocommerce-pesapal` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('plugin_name_hook'); ?>` in your templates
1. Enter your consumer and secret key in the Payment Gateway section of the Woocommerce settings page.
1. Enable the gateway.
1. **Test before production!**


== Changelog ==

https://github.com/Jakeii/woocommerce-pesapal/commits/master
