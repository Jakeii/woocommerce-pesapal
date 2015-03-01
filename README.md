#Pesapal Plugin for Woocommerce beta - Version 1.0.2

Simple and easy to use plugin for pesapal.com payment gateway.

Please raise any issues though github, thanks.

If you like this plugin consider [donating](http://jakeii.github.com/woocommerce-pesapal) a few bob for a coffee :)

##Requirements
(Same as Woocommerce)
* Wordpress 3.3+
* PHP 5.2.4+
* MySQl 5.0+

##Installation
Install by simply searching for the plugin within the wordpress plugin repository.

**OR**

* Clone the repo or download the zip and copy the woocommerce-pesapal directory to the /wp-content/plugins directory of your wordpress installation.
* Enable the plugin in Wordpress.
* Enter your consumer and secret key in the Payment Gateway section of the Woocommerce settings page.
* Enable the gateway.
* **Test before production!**

##Note
Not using an iframe has been disabled/removed because it causes to much trouble.

It is recommend that you enable IPN in the plugins settings page, as there are some issues with checking periodically for order updates.

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