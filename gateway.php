<?php
/*
Plugin Name: Woocommerce Pesapal Payment Gateway
Plugin URI: http://bodhi.io
Description: Allows use of kenyan payment processor Pesapal - http://pesapal.com.
Version: 0.0.4
Author: Jake Lee Kennedy
Author URI: http://bodhi.io
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

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
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

  // Hooks for adding/ removing the database table, and the wpcron to check them
  register_activation_hook( __FILE__, 'on_activate' );
  register_deactivation_hook( __FILE__, 'on_deactivate' );
  register_uninstall_hook( __FILE__, 'on_uninstall' );

  // include OAuth
  define( 'PLUGIN_DIR', dirname(__FILE__).'/' );
  require_once(PLUGIN_DIR . 'libs/OAuth.php');

  //Woocommerce doesn't have KES by default so add it if not already added
  add_filter( 'woocommerce_currencies', 'add_kenya_shilling' );

  function add_kenya_shilling( $currencies ) {
    if(!isset($currencies['KES'])||!isset($currencies['KSH'])){
       $currencies['KES'] = __( 'Kenyan Shilling', 'woocommerce' );
       add_filter('woocommerce_currency_symbol', 'add_kenya_shilling_symbol', 10, 2);
       return $currencies;
      }
  }

  function add_kenya_shilling_symbol( $currency_symbol, $currency ) {
     switch( $currency ) {
        case 'KES': $currency_symbol = '/='; break;
     }
     return $currency_symbol;
  }

  // cron interval for ever 5 minuites
  add_filter('cron_schedules','fivemins_cron_definer');

  function fivemins_cron_definer($schedules){
    $schedules['fivemins'] = array(
        'interval'=> 300,
        'display'=>  __('Once Every 5 minuites')
    );
    return $schedules;
  }

  /**
   * Activation, create processing order table, and table version option
   * @return void
   */
  function on_activate()
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

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    add_option('pesapal_db_version', $db_version);
  } 

  function on_deactivate()
  {
    $next_sheduled = wp_next_scheduled( 'pesapal_background_payment_checks' );
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

  function init_woo_pesapal_gateway() {
      
      class WC_Pesapal_Gateway extends WC_Payment_Gateway {

        function __construct()
        {
          $this->id = 'pesapal';
          $this->method_title     = __('Pesapal', 'woocommerce');
          $this->has_fields = false;
          
          // Gateway payment URLs
          $this->gatewayurl = 'https://pesapal.com/api/PostPesapalDirectOrderV4';
          $this->gatewaytesturl = 'https://demo.pesapal.com/api/PostPesapalDirectOrderV4';
          
          // Gateway status URLs
          $this->statusurl = 'http://pesapal.com/api/querypaymentstatus';
          $this->statustesturl = 'http://demo.pesapal.com/api/querypaymentstatus';
          
          $this->init_form_fields();
          
          $this->init_settings();
          
          // Settings
          $this->title      = $this->get_option('title');
          $this->description    = $this->get_option('description');
          $this->iframe       = ($this->get_option('iframe') === 'yes') ? true : false;
          
          $this->secretkey    = $this->get_option('secretkey');
          $this->consumerkey    = $this->get_option('consumerkey');
          
          $this->testmode     = ($this->get_option('testmode') === 'yes') ? true : false;
          $this->testsecretkey  = $this->get_option('testsecretkey');
          $this->testconsumerkey  = $this->get_option('testconsumerkey');
          
          // Actions
          add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
          
          // If user wants to use an iframe add the iframe code
          if($this->iframe) { add_action('woocommerce_receipt_pesapal', array(&$this, 'payment_page')); }
          
          add_action('before_woocommerce_pay', array(&$this, 'before_pay'));
          add_action('woocommerce_thankyou_pesapal', array(&$this, 'thankyou_page'));

          add_action('pesapal_background_payment_checks', array($this, 'background_check_payment_status'));
        
        }
        
        function init_form_fields() {
          $this->form_fields = array(
            'enabled' => array(
              'title' => __( 'Enable/Disable', 'woothemes' ),
              'type' => 'checkbox',
              'label' => __( 'Enable Pesapal Payment', 'woothemes' ),
              'default' => 'no'
            ),
            'title' => array(
              'title' => __( 'Title', 'woothemes' ),
              'type' => 'text',
              'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
              'default' => __( 'Pesapal Payment', 'woothemes' )
            ),
            'description' => array(
              'title' => __( 'Description', 'woocommerce' ),
              'type' => 'textarea',
              'description' => __( 'This is the description which the user sees during checkout.', 'woocommerce' ),
              'default' => __("Payment via Pesapal Gateway, you can pay by either credit/debit card or use mobile payment option such as Mpesa.", 'woocommerce')
            ),
            'iframe' => array(
              'title' => __( 'Use iframe', 'woothemes' ),
              'type' => 'checkbox',
              'label' => __( 'Use iframe', 'woothemes' ),
              'description' => __( 'Use pay page and iframe rather than redirect to pesapal. RECOMMENDED: Use SSL on the payment page (WooCommerce > Settings > General > Force secure checkout. Although the iframe is secure, users will not see so (i.e. HTTPS padlock or greenbar).', 'woothemes' ),
              'default' => 'no'
            ),
            'consumerkey' => array(
              'title' => __( 'Pesapal Consumer Key', 'woothemes' ),
              'type' => 'text',
              'description' => __( 'Your Pesapal consumer key which should have been emailed to you.', 'woothemes' ),
              'default' => ''
            ),
            'secretkey' => array(
              'title' => __( 'Pesapal Secret Key', 'woothemes' ),
              'type' => 'text',
              'description' => __( 'Your Pesapal secret key which should have been emailed to you.', 'woothemes' ),
              'default' => ''
            ),
            'testmode' => array(
              'title' => __( 'Use Demo Gateway', 'woothemes' ),
              'type' => 'checkbox',
              'label' => __( 'Use Demo Gateway', 'woothemes' ),
              'description' => __( 'Use demo pesapal gateway for testing.', 'woothemes' ),
              'default' => 'no'
            ),
            'testconsumerkey' => array(
              'title' => __( 'Pesapal Demo Consumer Key', 'woothemes' ),
              'type' => 'text',
              'description' => __( 'Your demo Pesapal consumer key which can be seen at demo.pesapal.com.', 'woothemes' ),
              'default' => ''
            ),
            'testsecretkey' => array(
              'title' => __( 'Pesapal Demo Secret Key', 'woothemes' ),
              'type' => 'text',
              'description' => __( 'Your demo Pesapal secret key which can be seen at demo.pesapal.com.', 'woothemes' ),
              'default' => ''
            )
          );
        }
        
        public function admin_options() {
          
          ?>
          <h3><?php _e('Pesapal Payment', 'woothemes'); ?></h3>
          <p><?php _e('Allows use of the Pesapal Payment Gateway, all you need is an account at pesapal.com and your consumer and secret key.', 'woothemes'); ?></p>
          <table class="form-table">
          <?php
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
          ?>
          </table>
          <script type="text/javascript">
          jQuery(function(){
            var testMode = jQuery("#woocommerce_pesapal_testmode");
            var consumer = jQuery("#woocommerce_pesapal_testconsumerkey");
            var secrect = jQuery("#woocommerce_pesapal_testsecretkey");
            
            if (testMode.is(":not(:checked)")){
              consumer.parents("tr").css("display","none");
              secrect.parents("tr").css("display","none");
            }
                      
              // Add onclick handler to checkbox w/id checkme
               testMode.click(function(){
            
              // If checked
              if (testMode.is(":checked"))
              {
                //show the hidden div
                consumer.parents("tr").show("fast");
                secrect.parents("tr").show("fast");
              }
              else
              {
                //otherwise, hide it
                consumer.parents("tr").hide("fast");
                secrect.parents("tr").hide("fast");
              }
              });

          });
          </script>
          <?php
        } // End admin_options()
        
        /**
         * Thankyou Page
         *
         * @return void
         * @author Jake Lee Kennedy
         **/
        function thankyou_page($order_id) {
          global $woocommerce;
          
          $order = new WC_Order( $order_id );
          
          // Remove cart
          $woocommerce->cart->empty_cart();
          
          // Empty awaiting payment session
          unset($_SESSION['order_awaiting_payment']);
                  
        }
        
        /**
         * Proccess payment
         *
         * @return void
         * @author Jake Lee Kennedy
         *
         **/
        function process_payment( $order_id ) {
          global $woocommerce;
        
          $order = &new WC_Order( $order_id );
        
          if($this->iframe){
            // Redirect to payment page
            return array(
              'result'    => 'success',
              'redirect'  => add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('pay'))))
            );
          }else {
            // Redirect to pesapal
            $url = $this->create_url($order_id);
            
            return array(
              'result'    => 'success',
              'redirect'  => $url
            );
          }
          
        
        } //END process_payment()
        
        /**
         * Payment page, creates pesapal oauth request and shows the gateway iframe
         *
         * @return void
         * @author Jake Lee Kennedy
         **/
        function payment_page($order_id){
          $url = $this->create_url($order_id);
          ?>
          <iframe src="<?php echo $url;?>" width="100%" height="620px"  scrolling="no" frameBorder="0">
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
          if(isset($_GET['pesapal_transaction_tracking_id'])){
            
            $order_id = $_GET['order'];
            $order = &new WC_Order( $order_id );
            
            $order->add_order_note( __('Payment accepted, awaiting confirmation.', 'woothemes') );
            
            add_post_meta( $order_id, 'Pesapal Tracking ID', $_GET['pesapal_transaction_tracking_id']);
            
            // Seems to be an issue with updating status when checking so soon after transaction.
            
            // $status = $this->status_request($_GET['pesapal_transaction_tracking_id'], $_GET['pesapal_merchant_reference']);
            // switch ($status) {
            //  case 'COMPLETED':
            //    // hooray payment complete
            //    $order->add_order_note( __('Payment confirmed.', 'woothemes') );
            //    $order->payment_complete();
            //    break;
            //  case 'FAILED':
            //    // aw, payment failed
            //    $order->update_status('failed',  __('Payment denied by gateway.', 'woocommerce'));
            //    break;
            //  case false:
            //  case 'PENDING':
            //    // not sure yet, add to list of payments to check every 5 minutes
            //    $tracking_id = $_GET['pesapal_transaction_tracking_id'];

            //    global $wpdb;
            //    $table_name = $wpdb->prefix . 'pesapal_queue';
            //    $wpdb->insert($table_name, array('order_id' => $order_id, 'tracking_id' => $tracking_id, 'time' => current_time('mysql')), array('%d', '%s', '%s'));
            //    break;
            // }
            
            $tracking_id = $_GET['pesapal_transaction_tracking_id'];

            global $wpdb;
            $table_name = $wpdb->prefix . 'pesapal_queue';
            $wpdb->insert($table_name, array('order_id' => $order_id, 'tracking_id' => $tracking_id, 'time' => current_time('mysql')), array('%d', '%s', '%s'));
            
            
            wp_redirect(add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('thanks')))));
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

            foreach($checks as $check){
            
              $order = &new WC_Order( $check->order_id );
            
              $status = $this->status_request($check->tracking_id, $check->order_id);
            
              switch ($status) {
                case 'COMPLETED':
                  // hooray payment complete
                  $order->add_order_note( __('Payment confirmed.', 'woothemes') );
                  $order->payment_complete(); 
                  $wpdb->query("DELETE FROM $table_name WHERE order_id = $check->order_id");
                  break;
                case 'FAILED':
                  // aw, payment failed
                  $order->update_status('failed',  __('Payment denied by gateway.', 'woocommerce'));
                  $wpdb->query("DELETE FROM $table_name WHERE order_id = $check->order_id");
                  break;
              }
            }
          }
        }
        
        /**
         * Generate OAuth pesapal payment url
         *
         * @return string
         * @author Jake Lee Kennedy
         **/
        function create_url($order_id)
        {
          $order = &new WC_Order( $order_id );
          
          $order_xml = $this->pesapal_xml($order_id);
          
          $signature_method = new OAuthSignatureMethod_HMAC_SHA1();
          $token = $params = NULL;
          
          //use test vars if in testmode
          if($this->testmode){
            $baseurl = $this->gatewaytesturl;
            $consumerkey = $this->testconsumerkey;
            $secretkey = $this->testsecretkey;
          } else {
            $baseurl = $this->gatewayurl;
            $consumerkey = $this->consumerkey;
            $secretkey = $this->secretkey;
          }
          
          $thankyou = add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('pay'))));
          
          $consumer = new OAuthConsumer($consumerkey, $secretkey);
          
          $url = OAuthRequest::from_consumer_and_token($consumer, $token, "GET", $baseurl, $params);
          $url->set_parameter("oauth_callback", $thankyou);
          $url->set_parameter("pesapal_request_data", $order_xml);
          $url->sign_request($signature_method, $consumer, $token);
          
          return htmlentities($url);
        }
        
        /**
         * Create XML order request
         *
         * @return string
         * @author Jake Lee Kennedy
         **/
        function pesapal_xml($order_id) {
          
          $order = &new WC_Order( $order_id );
          
          $pesapal_args['total'] = $order->get_total();
                    
          $pesapal_args['reference'] = $order_id;
          
          $pesapal_args['first_name'] = $order->billing_first_name;
          $pesapal_args['last_name'] = $order->billing_last_name;
          $pesapal_args['email'] = $order->billing_email;
          $pesapal_args['phone'] = $order->billing_phone;
          
          $i = 0;
          foreach($order->get_items() as $item){
            $product = $order->get_product_from_item($item);
            
            $cart[$i] = array(
              'id' => ($product->get_sku() ? $product->get_sku() : $product->id),
              'particulars' => $cart_row['name'],
              'quantity' => $item['qty'],
              'unitcost' => $product->regular_price,
              'subtotal' => $order->get_item_total($item, true)
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
            xmlns=\"http://www.pesapal.com\" />
            <lineitems>";
          foreach($cart as $item){
            $xml .= "<lineitem
                  uniqueid=\"".$item['id']."\"
                  particulars=\"".$item['particulars']."\"
                  quantity=\"".$item['quantity']."\"
                  unitcost=\"".$item['unitcost']."\"
                  subtotal=\"".$item['subtotal']."\"></lineitem>";
          }
          $xml .= "</lineitems></pesapaldirectorderinfo>";
          
          return $xml;
        }
        
        /**
         * Status request
         *
         * @return object
         * @author Jake Lee Kennedy
         **/
        function status_request($transaction_id, $merchant_ref)
        {
          $token = $params = NULL;
          
          //use test vars if in testmode
            if($this->testmode = true){
            $baseurl = $this->statustesturl;
            $consumerkey = $this->testconsumerkey;
            $secretkey = $this->testsecretkey;
            } else {
            $baseurl = $this->statusurl;
            $consumerkey = $this->consumerkey;
            $secretkey = $this->secretkey;
            }
            
          $consumer = new OAuthConsumer($consumerkey, $secretkey);
          $signature_method = new OAuthSignatureMethod_HMAC_SHA1();

          $request_status = OAuthRequest::from_consumer_and_token($consumer, $token, "GET", $baseurl, $params);
          $request_status->set_parameter("pesapal_merchant_reference", $merchant_ref);
          $request_status->set_parameter("pesapal_transaction_tracking_id", $transaction_id);
          $request_status->sign_request($signature_method, $consumer, $token);
          
          $result = wp_remote_get( $request_status );
          
          //curl request
          // $ajax_req =  new XMLHttpRequest();
          // $ajax_req->open("GET",$request_status);
          // $ajax_req->send();
          //if curl request successful
          
          
          if ($result['response']['code'] == 200) {
            $elements = preg_split("/=/",$result['body']);
            return $elements[1];
          }
          return false;
        }
          
      } // END WC_Pesapal_Gateway Class
    
  } // END init_woo_pesapal_gateway()

  function add_pesapal_gateway( $methods ) {
    $methods[] = 'WC_Pesapal_Gateway';
    return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'add_pesapal_gateway' );
} 