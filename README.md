#Pesapal Plugin for Woocommerce beta - Version 0.0.1

Simple and easy to use plugin for pesapal.com payment gateway.

**This plugin is in BETA and I would not recommend using it in a production environment without thorough testing.**

Please raise any issues though github, thanks.

If you like this plugin consider [donating](http://jakeii.github.com/woocommerce-pesapal) a few bob for a coffee :)

##Requirements
(Same as Woocommerce)
* Wordpress 3.3+
* PHP 5.2.4+
* MySQl 5.0+

##Installation
* Simply clone the repo or download the zip and copy the woocommerce-pesapal directory to the /wp-content/plugins directory of your wordpress installation.
* Enable the plugin in Wordpress.
* Enter your consumer and secret key in the Payment Gateway section of the Woocommerce settings page.
* Enable the gateway.
* **Test before production!**

##Note
There is an option to use an iframe rather than redirecting to Pesapal, although the iframe will use SSL to show the payment page, customers paying won't be able to see this. I recommend that you use an SSL Certificate and use the "force secure checkout" option in Woocommerce (To show the https padlock in the url or status bar), and ensure that your customers know that any details they enter will be safe.

##Licence
Copyright &copy; 2012 Jake Lee Kennedy, Licensed under GPLv3

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 3, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USAv