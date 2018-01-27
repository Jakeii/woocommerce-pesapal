<?php
/*
Plugin Name: Woocommerce Pesapal Payment Gateway
Plugin URI: http://bodhi.io
Description: Allows use of kenyan payment processor Pesapal - http://pesapal.com.
Version: 2.1.1
Author: Jake Lee Kennedy
Author URI: http://bodhi.io
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 3.3
Tested up to: 4.9.2
WC requires at least: 3.0.0
WC tested up to: 3.2.6

Copyright 2012  Jake Lee Kennedy  (email : jake@bodhi.io)

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
 */

// Check for woocommerce
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    // Hooks for adding/ removing the database table, and the wpcron to check them
    register_activation_hook(__FILE__, 'create_background_checks');
    register_deactivation_hook(__FILE__, 'remove_background_checks');
    register_uninstall_hook(__FILE__, 'on_uninstall');

    // include OAuth
    define('PLUGIN_DIR', dirname(__FILE__) . '/');
    require_once PLUGIN_DIR . 'libs/OAuth.php';

    add_filter('woocommerce_currency_symbol', 'add_kenya_shilling_symbol', 10, 2);
    function add_kenya_shilling_symbol($currency_symbol, $currency)
    {
        switch ($currency) {
            case 'KES':$currency_symbol = '/=';
                break;
        }
        return $currency_symbol;
    }

    // cron interval for ever 5 minuites
    add_filter('cron_schedules', 'fivemins_cron_definer');

    function fivemins_cron_definer($schedules)
    {
        $schedules['fivemins'] = array(
            'interval' => 300,
            'display' => __('Once Every 5 minuites'),
        );
        return $schedules;
    }

    /**
     * Activation, create processing order table, and table version option
     * @return void
     */
    function create_background_checks()
    {
        // Wp_cron checks pending payments in the background
        wp_schedule_event(time(), 'fivemins', 'pesapal_background_payment_checks');

        //Get the table name with the WP database prefix
        global $wpdb;
        $db_version = "1.0";
        $table_name = $wpdb->prefix . "pesapal_queue";

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
      order_id mediumint(9) NOT NULL,
      tracking_id varchar(36) NOT NULL,
      time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
      PRIMARY KEY (order_id, tracking_id)
    );";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        add_option('pesapal_db_version', $db_version);
    }

    function remove_background_checks()
    {
        $next_sheduled = wp_next_scheduled('pesapal_background_payment_checks');
        wp_unschedule_event($next_sheduled, 'pesapal_background_payment_checks');
    }

    /**
     * Clean up table and options on uninstall
     * @return [type] [description]
     */
    function on_uninstall()
    {
        // Clean up i.e. delete the table, wp_cron already removed on deacivate
        delete_option('pesapal_db_version');

        global $wpdb;

        $table_name = $wpdb->prefix . "pesapal_queue";

        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }

    add_action('plugins_loaded', 'init_woo_pesapal_gateway', 0);

    function init_woo_pesapal_gateway()
    {

        class WC_Pesapal_Gateway extends WC_Payment_Gateway
        {

            function __construct()
            {
                global $woocommerce;
                $this->id = 'pesapal';
                $this->method_title = __('Pesapal', 'woocommerce');
                $this->has_fields = false;
                $this->testmode = ($this->get_option('testmode') === 'yes') ? true : false;
                $this->debug = $this->get_option('debug');

                // Logs
                if ('yes' == $this->debug) {
                    if (class_exists('WC_Logger')) {
                        $this->log = new WC_Logger();
                    } else {
                        $this->log = $woocommerce->logger();
                    }

                }

                if ($this->testmode) {
                    $api = 'http://demo.pesapal.com/';
                    $this->consumer_key = $this->get_option('testconsumerkey');
                    $this->consumer_secret = $this->get_option('testsecretkey');
                } else {
                    $api = 'https://www.pesapal.com/';
                    $this->consumer_key = $this->get_option('consumerkey');
                    $this->consumer_secret = $this->get_option('secretkey');
                }

                $this->consumer = new OAuthConsumer($this->consumer_key, $this->consumer_secret);
                $this->signature_method = new OAuthSignatureMethod_HMAC_SHA1();
                $this->token = $this->params = null;

                // Gateway payment URLs
                $this->gatewayURL = $api . 'api/PostPesapalDirectOrderV4';
                $this->QueryPaymentStatus = $api . 'API/QueryPaymentStatus';
                $this->QueryPaymentStatusByMerchantRef = $api . 'API/QueryPaymentStatusByMerchantRef';
                $this->querypaymentdetails = $api . 'API/querypaymentdetails';

                // IPN Request URL
                $this->notify_url = str_replace('https:', 'http:', add_query_arg('wc-api', 'WC_Pesapal_Gateway', home_url('/')));
                $this->init_form_fields();
                $this->init_settings();

                // Settings
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->ipn = ($this->get_option('ipn') === 'yes') ? true : false;

                // Actions
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_receipt_pesapal', array(&$this, 'payment_page'));
                // add_action('before_woocommerce_pay', array(&$this, 'before_pay'));
                add_action('woocommerce_thankyou_pesapal', array(&$this, 'thankyou_page'));
                add_action('pesapal_background_payment_checks', array($this, 'background_check_payment_status'));
                add_action('woocommerce_api_wc_pesapal_gateway', array($this, 'ipn_response'));
                add_action('pesapal_process_valid_ipn_request', array($this, 'process_valid_ipn_request'));
            }

            function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'woothemes'),
                        'type' => 'checkbox',
                        'label' => __('Enable Pesapal Payment', 'woothemes'),
                        'default' => 'no',
                    ),
                    'title' => array(
                        'title' => __('Title', 'woothemes'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woothemes'),
                        'default' => __('Pesapal Payment', 'woothemes'),
                    ),
                    'description' => array(
                        'title' => __('Description', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __('This is the description which the user sees during checkout.', 'woocommerce'),
                        'default' => __("Payment via Pesapal Gateway, you can pay by either credit/debit card or use mobile payment option such as Mpesa.", 'woocommerce'),
                    ),
                    'ipn' => array(
                        'title' => __('Use IPN', 'woothemes'),
                        'type' => 'checkbox',
                        'label' => __('Use IPN', 'woothemes'),
                        'description' => __('Pesapal has the ability to send your site an Instant Payment Notification whenever there is an order update. It is highly reccomended that you enable this, as there are some issues with the "background" status checking. It is disabled by default because the IPN URL needs to be entered in the pesapal control panel.', 'woothemes'),
                        'default' => 'no',
                    ),
                    'ipnurl' => array(
                        'title' => __('IPN URL', 'woothemes'),
                        'type' => 'text',
                        'description' => __('This is the IPN URL that you must enter in the Pesapal control panel. (This is not editable)', 'woothemes'),
                        'default' => $this->notify_url,
                    ),
                    'consumerkey' => array(
                        'title' => __('Pesapal Consumer Key', 'woothemes'),
                        'type' => 'text',
                        'description' => __('Your Pesapal consumer key which should have been emailed to you.', 'woothemes'),
                        'default' => '',
                    ),
                    'secretkey' => array(
                        'title' => __('Pesapal Secret Key', 'woothemes'),
                        'type' => 'text',
                        'description' => __('Your Pesapal secret key which should have been emailed to you.', 'woothemes'),
                        'default' => '',
                    ),
                    'testmode' => array(
                        'title' => __('Use Demo Gateway', 'woothemes'),
                        'type' => 'checkbox',
                        'label' => __('Use Demo Gateway', 'woothemes'),
                        'description' => __('Use demo pesapal gateway for testing.', 'woothemes'),
                        'default' => 'no',
                    ),
                    'testconsumerkey' => array(
                        'title' => __('Pesapal Demo Consumer Key', 'woothemes'),
                        'type' => 'text',
                        'description' => __('Your demo Pesapal consumer key which can be seen at demo.pesapal.com.', 'woothemes'),
                        'default' => '',
                    ),
                    'debug' => array(
                        'title' => __('Debug Log', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Enable logging', 'woocommerce'),
                        'default' => 'no',
                        'description' => sprintf(__('Log PesaPal events, such as IPN requests, inside <code>woocommerce/logs/pesapal-%s.txt</code>', 'woocommerce'), sanitize_file_name(wp_hash('pesapal'))),
                    ),

                    'testsecretkey' => array(
                        'title' => __('Pesapal Demo Secret Key', 'woothemes'),
                        'type' => 'text',
                        'description' => __('Your demo Pesapal secret key which can be seen at demo.pesapal.com.', 'woothemes'),
                        'default' => '',
                    ),
                );
            }

            public function admin_options()
            {?>

          <h3><?php _e('Pesapal Payment', 'woothemes');?></h3>
          <p>
            <?php _e('Allows use of the Pesapal Payment Gateway, all you need is an account at pesapal.com and your consumer and secret key.<br />', 'woothemes');?>
            <?php _e('<a href="http://docs.woothemes.com/document/managing-orders/">Click here </a> to learn about the various woocommerce Payment statuses.<br /><br />', 'woothemes');?>
            <?php _e('<strong>Developer: </strong>Jakeii<br />', 'woothemes');?>
            <?php _e('<strong>Contributors: </strong>PesaPal<br />', 'woothemes');?>
            <?php _e('<strong>Donate link:  </strong><a href="http://jakeii.github.com/woocommerce-pesapal" target="_blank"> http://jakeii.github.com/woocommerce-pesapal</a>', 'woothemes');?>
          </p>
          <table class="form-table">
          <?php
// Generate the HTML For the settings form.
                $this->generate_settings_html();
                ?>
          </table>
          <script type="text/javascript">
          jQuery(function(){
            var testMode = jQuery("#woocommerce_pesapal_testmode");
            var ipn = jQuery("#woocommerce_pesapal_ipn");
            var ipnurl = jQuery("#woocommerce_pesapal_ipnurl");
            var consumer = jQuery("#woocommerce_pesapal_testconsumerkey");
            var secrect = jQuery("#woocommerce_pesapal_testsecretkey");

            if (testMode.is(":not(:checked)")){
              consumer.parents("tr").css("display","none");
              secrect.parents("tr").css("display","none");
            }

            if (ipn.is(":not(:checked)")){
              ipnurl.parents("tr").css("display","none");
            }

            // Add onclick handler to checkbox w/id checkme
            testMode.click(function(){
              // If checked
              if (testMode.is(":checked")) {
                //show the hidden div
                consumer.parents("tr").show("fast");
                secrect.parents("tr").show("fast");
              } else {
                //otherwise, hide it
                consumer.parents("tr").hide("fast");
                secrect.parents("tr").hide("fast");
              }
            });

            ipn.click(function(){
              // If checked
              if (ipn.is(":checked")) {
                //show the hidden div
                ipnurl.parents("tr").show("fast");
              } else {
                //otherwise, hide it
                ipnurl.parents("tr").hide("fast");
              }
            });

          });
          </script>
          <?php
} // End admin_options()

            /**
             * Thank You Page
             *
             * @param Integer $order_id
             * @return void
             * @author Jake Lee Kennedy
             **/
            public function thankyou_page($order_id)
            {
                // global $woocommerce;

                // $order = wc_get_order( $order_id );

                // // Remove cart
                // $woocommerce->cart->empty_cart();

                if (isset($_GET['pesapal_transaction_tracking_id'])) {

                    // $order_id = $_GET['order'];
                    $order = wc_get_order($order_id);
                    $pesapalMerchantReference = $_GET['pesapal_merchant_reference'];
                    $pesapalTrackingId = $_GET['pesapal_transaction_tracking_id'];

                    //$status            = $this->check_transaction_status($pesapalMerchantReference);
                    //$status             = $this->check_transaction_status($pesapalMerchantReference,$pesapalTrackingId);
                    $transactionDetails = $this->get_transaction_details($pesapalMerchantReference, $pesapalTrackingId);

                    $order->add_order_note(__('Payment accepted, awaiting confirmation.', 'woothemes'));
                    add_post_meta($order_id, '_order_pesapal_transaction_tracking_id', $transactionDetails['pesapal_transaction_tracking_id']);
                    add_post_meta($order_id, '_order_pesapal_payment_method', $transactionDetails['payment_method']);

                    $dbUpdateSuccessful = add_post_meta($order_id, '_order_payment_method', $transactionDetails['payment_method']);

                    // if immeadiatly complete mark it so
                    if ($transactionDetails["status"] === 'COMPLETED') {
                        $order->add_order_note(__('Payment confirmed.', 'woothemes'));
                        $order->payment_complete();
                    } else if (!$this->ipn) {
                        $tracking_id = $_GET['pesapal_transaction_tracking_id'];

                        global $wpdb;
                        $table_name = $wpdb->prefix . 'pesapal_queue';
                        $wpdb->insert($table_name, array('order_id' => $order_id, 'tracking_id' => $tracking_id, 'time' => current_time('mysql')), array('%d', '%s', '%s'));
                    }
                }

            }

            /**
             * Proccess payment
             *
             * @param Integer $order_id
             * @return void
             * @author Jake Lee Kennedy
             *
             **/
            function process_payment($order_id)
            {
                global $woocommerce;

                $order = wc_get_order($order_id);

                // Redirect to payment page
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true)),
                );

            } //END process_payment()

            /**
             * Payment page, creates pesapal oauth request and shows the gateway iframe
             *
             * @return void
             * @author Jake Lee Kennedy
             **/
            function payment_page($order_id)
            {
                $url = $this->create_url($order_id);
                ?>
          <iframe src="<?php echo $url; ?>" width="100%" height="700px"  scrolling="yes" frameBorder="0">
            <p>Browser unable to load iFrame</p>
          </iframe>
          <?php
}

            /**
             * Before Payment
             *
             * @return void
             * @author Jake Lee Kennedy
             **/
            function before_pay()
            {
                // if we have come from the gateway do some stuff
                if (isset($_GET['pesapal_transaction_tracking_id'])) {

                    $order_id = $_GET['order'];
                    $order = wc_get_order($order_id);
                    $pesapalMerchantReference = $_GET['pesapal_merchant_reference'];
                    $pesapalTrackingId = $_GET['pesapal_transaction_tracking_id'];

                    //$status            = $this->check_transaction_status($pesapalMerchantReference);
                    //$status             = $this->check_transaction_status($pesapalMerchantReference,$pesapalTrackingId);
                    $transactionDetails = $this->get_transaction_details($pesapalMerchantReference, $pesapalTrackingId);

                    $order->add_order_note(__('Payment accepted, awaiting confirmation.', 'woothemes'));
                    add_post_meta($order_id, '_order_pesapal_transaction_tracking_id', $transactionDetails['pesapal_transaction_tracking_id']);
                    add_post_meta($order_id, '_order_pesapal_payment_method', $transactionDetails['payment_method']);

                    $dbUpdateSuccessful = add_post_meta($order_id, '_order_payment_method', $transactionDetails['payment_method']);

                    if (!$this->ipn) {
                        $tracking_id = $_GET['pesapal_transaction_tracking_id'];

                        global $wpdb;
                        $table_name = $wpdb->prefix . 'pesapal_queue';
                        $wpdb->insert($table_name, array('order_id' => $order_id, 'tracking_id' => $tracking_id, 'time' => current_time('mysql')), array('%d', '%s', '%s'));
                    }

                    wp_redirect(add_query_arg('key', $order->get_order_key(), add_query_arg('order', $order_id, $order->get_checkout_order_received_ur())));
                }
            }

            /**
             * backgroud check payment
             *
             * @return void
             * @author Jake Lee Kennedy
             **/
            function background_check_payment_status()
            {
                global $wpdb;
                $table_name = $wpdb->prefix . 'pesapal_queue';

                $checks = $wpdb->get_results("SELECT order_id, tracking_id FROM $table_name");

                if ($wpdb->num_rows > 0) {

                    foreach ($checks as $check) {

                        $order = wc_get_order($check->order_id);

                        $status = $this->status_request($check->tracking_id, $check->order_id);

                        switch ($status) {
                            case 'COMPLETED':
                                // hooray payment complete
                                $order->add_order_note(__('Payment confirmed.', 'woothemes'));
                                $order->payment_complete();
                                $wpdb->query("DELETE FROM $table_name WHERE order_id = $check->order_id");
                                break;
                            case 'FAILED':
                                // aw, payment failed
                                $order->update_status('failed', __('Payment denied by gateway.', 'woocommerce'));
                                $wpdb->query("DELETE FROM $table_name WHERE order_id = $check->order_id");
                                break;
                        }
                    }
                }
            }

            /**
             * Generate OAuth pesapal payment url
             *
             * @param Integer $order_id
             * @return string
             * @author Jake Lee Kennedy
             **/
            function create_url($order_id)
            {
                $order = wc_get_order($order_id);
                $order_xml = $this->pesapal_xml($order_id);
                $callback_url = add_query_arg('key', $order->get_order_key(), $order->get_checkout_order_received_url());

                $url = OAuthRequest::from_consumer_and_token($this->consumer, $this->token, "GET", $this->gatewayURL, $this->params);
                $url->set_parameter("oauth_callback", $callback_url);
                $url->set_parameter("pesapal_request_data", $order_xml);
                $url->sign_request($this->signature_method, $this->consumer, $this->token);

                return $url;
            }

            /**
             * Create XML order request
             *
             * @param Integer $order_id
             * @return string
             * @author Jake Lee Kennedy
             **/
            function pesapal_xml($order_id)
            {

                $order = wc_get_order($order_id);
                $pesapal_args['total'] = $order->get_total();
                $pesapal_args['reference'] = $order_id;
                $pesapal_args['first_name'] = $order->get_billing_first_name();
                $pesapal_args['last_name'] = $order->get_billing_last_name();
                $pesapal_args['email'] = $order->get_billing_email();
                $pesapal_args['phone'] = $order->get_billing_phone();

                $i = 0;
                foreach ($order->get_items('line_item') as $item) {
                    $product = $item->get_product();

                    $cart[$i] = array(
                        'id' => ($product->get_sku() ? $product->get_sku() : $product->id),
                        'particulars' => $product->get_name(),
                        'quantity' => $item->get_quantity(),
                        'unitcost' => $product->get_regular_price(),
                        'subtotal' => $order->get_item_total($item, true),
                    );
                    $i++;
                }

                $xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
            <PesapalDirectOrderInfo xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\"
            Amount=\"" . $pesapal_args['total'] . "\"
            Description=\"Order from " . bloginfo('name') . ".\"
            Type=\"MERCHANT\"
            Reference=\"" . $pesapal_args['reference'] . "\"
            FirstName=\"" . $pesapal_args['first_name'] . "\"
            LastName=\"" . $pesapal_args['last_name'] . "\"
            Email=\"" . $pesapal_args['email'] . "\"
            PhoneNumber=\"" . $pesapal_args['phone'] . "\"
            Currency=\"" . get_woocommerce_currency() . "\"
            xmlns=\"http://www.pesapal.com\" />";

                return htmlentities($xml);
            }

            /**
             * Status request
             *
             * @param String $transaction_id
             * @param String $merchant_ref
             * @return object
             * @author Jake Lee Kennedy
             * @modifiedBy PesaPal
             **/
            function status_request($transaction_id, $merchant_ref)
            {

                $request_status = OAuthRequest::from_consumer_and_token($this->consumer, $this->token, "GET", $this->gatewayURL, $this->params);
                $request_status->set_parameter("pesapal_merchant_reference", $merchant_ref);
                $request_status->set_parameter("pesapal_transaction_tracking_id", $transaction_id);
                $request_status->sign_request($this->signature_method, $this->consumer, $this->token);

                return $this->check_transaction_status($merchant_ref);
                //return $this->check_transaction_status($merchant_ref,$transaction_id);
                //return $this->get_transaction_details($merchant_ref,$transaction_id);

            }

            /**
             * Check Transaction status
             *
             * @param String $pesapalMerchantReference
             * @param String $pesapalTrackingId
             * @return PENDING/FAILED/INVALID
             * @author PesaPal
             **/
            function check_transaction_status($pesapalMerchantReference, $pesapalTrackingId = null)
            {
                if ($pesapalTrackingId) {
                    $queryURL = $this->QueryPaymentStatus;
                } else {
                    $queryURL = $this->QueryPaymentStatusByMerchantRef;
                }

                //get transaction status
                $request_status = OAuthRequest::from_consumer_and_token(
                    $this->consumer,
                    $this->token,
                    "GET",
                    $queryURL,
                    $this->params
                );

                $request_status->set_parameter("pesapal_merchant_reference", $pesapalMerchantReference);

                if ($pesapalTrackingId) {
                    $request_status->set_parameter("pesapal_transaction_tracking_id", $pesapalTrackingId);
                }

                $request_status->sign_request($this->signature_method, $this->consumer, $this->token);

                return $this->curl_request($request_status);
            }

            /**
             * Check Transaction status
             *
             * @param String $pesapalMerchantReference
             * @param String $pesapalTrackingId
             * @return PENDING/FAILED/INVALID
             * @author PesaPal
             **/
            function get_transaction_details($pesapalMerchantReference, $pesapalTrackingId)
            {

                $request_status = OAuthRequest::from_consumer_and_token(
                    $this->consumer,
                    $this->token,
                    "GET",
                    $this->querypaymentdetails,
                    $this->params
                );

                $request_status->set_parameter("pesapal_merchant_reference", $pesapalMerchantReference);
                $request_status->set_parameter("pesapal_transaction_tracking_id", $pesapalTrackingId);
                $request_status->sign_request($this->signature_method, $this->consumer, $this->token);

                $responseData = $this->curl_request($request_status);

                $pesapalResponse = explode(",", $responseData);

                $pesapalResponseArray = array('pesapal_transaction_tracking_id' => $pesapalResponse[0],
                    'payment_method' => $pesapalResponse[1],
                    'status' => $pesapalResponse[2],
                    'pesapal_merchant_reference' => $pesapalResponse[3],
                );

                return $pesapalResponseArray;
            }

            /**
             * Check Transaction status
             *
             * @param String $request_status
             * @return ARRAY
             * @author PesaPal
             **/
            function curl_request($request_status)
            {

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $request_status);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HEADER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                if (defined('CURL_PROXY_REQUIRED')) {
                    if (CURL_PROXY_REQUIRED == 'True') {
                        $proxy_tunnel_flag = (
                            defined('CURL_PROXY_TUNNEL_FLAG')
                            && strtoupper(CURL_PROXY_TUNNEL_FLAG) == 'FALSE'
                        ) ? false : true;
                        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, $proxy_tunnel_flag);
                        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                        curl_setopt($ch, CURLOPT_PROXY, CURL_PROXY_SERVER_DETAILS);
                    }
                }

                $response = curl_exec($ch);
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $raw_header = substr($response, 0, $header_size - 4);
                $headerArray = explode("\r\n\r\n", $raw_header);
                $header = $headerArray[count($headerArray) - 1];

                //transaction status
                $elements = preg_split("/=/", substr($response, $header_size));
                $pesapal_response_data = $elements[1];

                return $pesapal_response_data;

            }

            /**
             * IPN Response
             *
             * @return null
             * @author Jake Lee Kennedy
             **/
            function ipn_response()
            {

                $pesapalTrackingId = '';
                $pesapalNotification = '';
                $pesapalMerchantReference = '';

                if (isset($_GET['pesapal_merchant_reference'])) {
                    $pesapalMerchantReference = $_GET['pesapal_merchant_reference'];
                }

                if (isset($_GET['pesapal_transaction_tracking_id'])) {
                    $pesapalTrackingId = $_GET['pesapal_transaction_tracking_id'];
                }

                if (isset($_GET['pesapal_notification_type'])) {
                    $pesapalNotification = $_GET['pesapal_notification_type'];
                }

                /** check status of the transaction made
                 *There are 3 available API
                 *checkStatusUsingTrackingIdandMerchantRef() - returns Status only.
                 *checkStatusByMerchantRef() - returns status only.
                 *getMoreDetails() - returns status, payment method, merchant reference and pesapal tracking id
                 */

                //$status            = $this->check_transaction_status($pesapalMerchantReference);
                //$status             = $this->check_transaction_status($pesapalMerchantReference,$pesapalTrackingId);
                $transactionDetails = $this->get_transaction_details($pesapalMerchantReference, $pesapalTrackingId);
                $order = wc_get_order($pesapalMerchantReference);

                // We are here so lets check status and do actions
                switch ($transactionDetails['status']) {
                    case 'COMPLETED':
                    case 'PENDING':

                        // Check order not already completed
                        if ($order->get_status() == 'completed') {
                            if ('yes' == $this->debug) {
                                $this->log->add('pesapal', 'Aborting, Order #' . $order->id . ' is already complete.');
                            }

                            exit;
                        }

                        if ($transactionDetails['status'] == 'COMPLETED') {
                            $order->add_order_note(__('IPN payment completed', 'woocommerce'));
                            $order->payment_complete();
                        } else {
                            $order->update_status('on-hold', sprintf(__('Payment pending: %s', 'woocommerce'), 'Waiting PesaPal confirmation'));
                        }

                        if ('yes' == $this->debug) {
                            $this->log->add('pesapal', 'Payment complete.');
                        }

                        break;
                    case 'INVALID':
                    case 'FAILED':
                        // Order failed
                        $order->update_status('failed', sprintf(__('Payment %s via IPN.', 'woocommerce'), strtolower($transactionDetails['status'])));
                        break;

                    default:
                        // No action
                        break;
                }

                $order = wc_get_order($pesapalMerchantReference);
                $newstatus = $order->get_status();

                if ($transactionDetails['status'] == $newstatus) {
                    $dbupdated = "True";
                } else {
                    $dbupdated = 'False';
                }

                if ($pesapalNotification == "CHANGE" && $dbupdated && $transactionDetails['status'] != "PENDING") {

                    $resp = "pesapal_notification_type=$pesapalNotification" .
                        "&pesapal_transaction_tracking_id=$pesapalTrackingId" .
                        "&pesapal_merchant_reference=$pesapalMerchantReference";

                    ob_start();
                    echo $resp;
                    ob_flush();
                    exit;
                }
            }

        } // END WC_Pesapal_Gateway Class

    } // END init_woo_pesapal_gateway()

    /**
     * @param String[] $methods
     * @return String[]
     */
    function add_pesapal_gateway($methods)
    {
        $methods[] = 'WC_Pesapal_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_pesapal_gateway');
}