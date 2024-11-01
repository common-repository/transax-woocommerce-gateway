<?php
/**
 * Plugin Name: Transax - WooCommerce Gateway
 * Plugin URI: https://wordpress.org/plugins/transax-woocommerce-gateway/
 * Description: Extends WooCommerce by Adding the TRANSAX Gateway.
 * Version: 1.1.5
 * Author: Transax
 * Author URI: http://www.transaxgateway.com/
 *
 * WC requires at least: 3.0
 * WC tested up to: 4.2.2
 *
 */

require_once('include/lib.php');
if (!defined('ABSPATH'))
    exit;

register_uninstall_hook(__FILE__, 'transax_uninstall');

function transax_uninstall() {
	delete_option('woocommerce_transax_settings');
}

add_action('plugins_loaded', 'woocommerce_transax_payment_init', 0);

function woocommerce_transax_payment_init() {
    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Transax_Payment_Gateway extends WC_Payment_Gateway_CC {

        /**
         * Constructor for the gateway.
         *
         * @return void
         */
        public function __construct() {
            global $woocommerce;

            $this->id = 'transax';
            $this->icon = apply_filters('woocommerce_transax_icon', '');
            $this->has_fields = false;
            $this->method_title = __('TRANSAX Gateway', 'wctransax');
            $this->send_shipping = $this->get_option( 'send_shipping' );
            $this->address_override = $this->get_option( 'address_override' );
            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user set variables.
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->instructions = $this->get_option('instructions');
            $this->enable_for_methods = $this->get_option('enable_for_methods', array());

            // Actions.
            if (version_compare(WOOCOMMERCE_VERSION, '3.0.0', '>='))
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            else
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
        }

        /* Admin Panel Options. */

        function admin_options() {
?>
<h3>
    <?php _e('TRANSAX Gateway', 'wctransax'); ?>
</h3>
<table class="form-table">
    <?php $this->generate_settings_html(); ?>
</table>
<?php
        }

        /* Initialise Gateway Settings Form Fields. */

        public function init_form_fields() {
            global $woocommerce;

            $shipping_methods = array();

            //if (is_admin())
            foreach (WC()->shipping()->load_shipping_methods() as $method) {
                $shipping_methods[$method->id] = $method->get_method_title();
            }

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable TRANSAX Gateway', 'wcwcCpg1'),
                    'type' => 'checkbox',
                    'label' => __('Enable/Disable', 'wctransax'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title', 'wctransax'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'wctransax'),
                    'desc_tip' => true,
                    'default' => __('TRANSAX Gateway', 'wctransax'),
                    'placeholder' => 'TRANSAX Gateway'
                ),
                'description' => array(
                    'title' => __('Description', 'wctransax'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'wctransax'),
                    'default' => __('Desctiptions for TRANSAX Gateway.', 'wctransax'),
                    'placeholder' => 'Desctiptions for TRANSAX Gateway'
                ),
                'gatewayusername' => array(
                    'title' => __('GatewayUserName', 'wctransax'),
                    'type' => 'text',
                    'description' => __('Enter the API username you created in your TRANSAX Gateway.', 'wctransax'),
                    'desc_tip' => true,
                    'placeholder' => 'User Name'
                ),
                'gatewaypassword' => array(
                    'title' => __('Gatewaypassword', 'wctransax'),
                    'type' => 'password',
                    'description' => __('Enter the API password you created in your TRANSAX Gateway.', 'wctransax'),
                    'desc_tip' => true,
                    'default' => __('', 'wctransax')
                ),

                'instructions' => array(
                    'title' => __('Instructions', 'wctransax'),
                    'type' => 'textarea',
                    'description' => __('Instructions that will be added to the thank you page.', 'wctransax'),
                    'default' => __('Instructions for TRANSAX Gateway.', 'wctransax')
                ),
                'enable_for_methods' => array(
                    'title' => __('Enable for shipping methods', 'wctransax'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select',
                    'css' => 'width: 450px;',
                    'default' => '',
                    'description' => __('If Transax is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'wctransax'),
                    'options' => $shipping_methods,
                    'desc_tip' => true,
                    'custom_attributes' => array(
			    		'data-placeholder' => __( 'Select shipping methods', 'woocommerce' ),
		    		)
               ),
               'successstatus' => array(
                   'title' => __('Success Status', 'wctransax'),
                   'type' => 'select',
                   'description' => __('Status that order will be in upon successful transaction charge <br/>* <strong>Processing</strong>: <em>Payment Accepted, Stock levels unchanged</em><br/>* <strong>Completed</strong>: <em>Payment Accepted, Stock levels reduced</em>', 'wctransax'),
                   'class' => 'wc-select',
                   'options' => array(
                        'processing' => 'Processing',
                        'completed' => 'Completed')
               ),
               'failurestatus' => array(
				   'title' => __('Failure Status', 'wctransax'),
				   'type' => 'select',
				   'description' => __('Status that order will be in upon failed transaction charge <br/>* <strong>On-Hold</strong>: <em>Payment Declined, Stock levels reduced, Awaiting external payment</em><br/>* <strong>Failed</strong>: <em>Payment Declined, Stock levels unchanged</em>', 'wctransax'),
				   'class' => 'wc-select',
				   'options' => array(
					  'on-hold' => 'On-Hold',
					  'failed' => 'Failed')
               ),
			   'testmode' => array(
				   'title' => __('Enable Test Mode', 'wctransax'),
				   'type' => 'checkbox',
				   'label' => __('Enable/Disable', 'wctransax'),
				   'description' => __('Enable Test Mode. All transactions while checked will be sent using the test credentials.', 'wctransax'),
                   'default' => 'no'
			   ),
			   'ziponly' => array(
				   'title' => __('Only send Zip', 'wctransax'),
				   'type' => 'checkbox',
				   'label' => __('Enable/Disable', 'wctransax'),
				   'description' => __('Send the billing Zip instead of the entire billing address.', 'wctransax'),
                   'default' => 'no'
			   ),
            );
        }

        /* Process the payment and return the result. */
        public function get_transax_state( $cc, $state ) {
            if ( 'US' === $cc ) {
                return $state;
            }

            $states = WC()->countries->get_states( $cc );

            if ( isset( $states[ $state ] ) ) {
                return $states[ $state ];
            }

            return $state;
        }
        public function transax_item_name( $item_name ) {
            if ( strlen( $item_name ) > 127 ) {
                $item_name = substr( $item_name, 0, 124 ) . '...';
            }
            return html_entity_decode( $item_name, ENT_NOQUOTES, 'UTF-8' );
        }
        private function get_line_items( $order ) {
            // Do not send lines for tax inclusive prices
            if ( 'yes' === get_option( 'woocommerce_calc_taxes' ) && 'yes' === get_option( 'woocommerce_prices_include_tax' ) ) {
                return false;
            }
            // Do not send lines when order discount is present, or too many line items in the order.
            //if ( $order->get_order_discount() > 0 || ( sizeof( $order->get_items() ) + sizeof( $order->get_fees() ) ) >= 9 ) {
            //    return false;
            //}
            if ( $order->get_total_discount() > 0 || ( sizeof( $order->get_items() ) + sizeof( $order->get_fees() ) ) >= 9 ) {
                return false;
            }
            $item_loop        = 0;
            $args             = array();
            //$args['tax_cart'] = $order->get_total_tax();
            // Products
            if ( sizeof( $order->get_items() ) > 0 ) {
                foreach ( $order->get_items() as $item ) {
                    if ( ! $item['qty'] ) {
                        continue;
                    }

                    $product   = $order->get_product_from_item( $item );
                    $item_name = $item['name'];
					if(!is_array($item['item_meta'])) {
						if ( $meta = wc_display_item_meta($item['item_meta']) ) {
							$item_name .= ' ( ' . $meta . ' )';
						}
					}
                    $args[$item_loop][ 'item_name_' . $item_loop ] = $this->transax_item_name( $item_name );
                    $args[$item_loop][ 'quantity_' . $item_loop ]  = $item['qty'];
                    $args[$item_loop][ 'amount_' . $item_loop ]    = $order->get_item_subtotal( $item, false );

                    if ( $args[ 'amount_' . $item_loop ] < 0 ) {
                        return false; // Abort - negative line
                    }

                    if ( $product->get_sku() ) {
                        $args[ 'item_number_' . $item_loop ] = $product->get_sku();
                    }
                    $item_loop ++;
                }
            }
            // Discount
            //if ( $order->get_cart_discount() > 0 ) {
            //    $args['discount_amount_cart'] = round( $order->get_cart_discount(), 2 );
            //}
            if ( $order->get_total_discount() > 0 ) {
                $args['discount_amount_cart'] = round( $order->get_total_discount(), 2 );
            }

            // Fees
            if ( sizeof( $order->get_fees() ) > 0 ) {
                foreach ( $order->get_fees() as $item ) {
                    $item_loop ++;
                    $args[ 'item_name_' . $item_loop ] = $this->transax_item_name( $item['name'] );
                    $args[ 'quantity_' . $item_loop ]  = 1;
                    $args[ 'amount_' . $item_loop ]    = $item['line_total'];

                    if ( $args[ 'amount_' . $item_loop ] < 0 ) {
                        return false; // Abort - negative line
                    }
                }
            }
            // Shipping Cost item - paypal only allows shipping per item, we want to send shipping for the order
            if ( $order->get_total_shipping() > 0 ) {
                $item_loop ++;
                $args[ 'item_name_' . $item_loop ] = $this->transax_item_name( sprintf( __( 'Shipping via %s', 'woocommerce' ), $order->get_shipping_method() ) );
                $args[ 'quantity_' . $item_loop ]  = '1';
                $args[ 'amount_' . $item_loop ]    = number_format( $order->get_total_shipping(), 2, '.', '' );
            }
            return $args;
        }

        function get_line_items_include_tax($order) {
            $item_names = array();

            if (sizeof($order->get_items()) > 0) {
                foreach ($order->get_items() as $item) {
                    if ($item['qty']) {
                        $item_names[] = $item['name'] . ' x ' . $item['qty'];
                    }
                }
            }

            $args[0]['item_name_1'] = $this->transax_item_name(sprintf(__('Order %s', 'woocommerce'), $order->get_order_number()) . " - " . implode(', ', $item_names));
            $args[0]['quantity_1'] = '1';
            $args[0]['amount_1'] = number_format($order->get_total() - round($order->get_total_shipping() + $order->get_shipping_tax(), 2) + $order->get_total_discount(), 2, '.', '');

            if (( $order->get_total_shipping() + $order->get_shipping_tax() ) > 0) {
                $args[1]['item_name_2'] = $this->transax_item_name(__('Shipping via', 'woocommerce') . ' ' . ucwords($order->get_shipping_method()));
                $args[1]['quantity_2'] = '1';
                $args[1]['amount_2'] = number_format($order->get_total_shipping() + $order->get_shipping_tax(), 2, '.', '');
            }

            // Discount

            if ( $order->get_total_discount() > 0 ) {
                $args['discount_amount_cart'] = round( $order->get_total_discount(), 2 );
            }

            return $args;
        }

        function process_payment($order_id) {
            global $woocommerce;
            $order      = new WC_Order($order_id);
            $order_details = $this->get_line_items( $order );

            if($order_details)
            {
                $item = 0;
                $product = '';

                foreach($order_details as $details):
                    if(is_array($details)):
                        $product.='Product Name :'. $details['item_name_'.$item].'  Quantity : '.$details['quantity_'.$item].'  Amount : '.$details['amount_'.$item].'  ';
                        $item ++;
                    endif;
                endforeach;
            }
            else
            {
                $item = 1;
                $product = '';
                $order_details = $this->get_line_items_include_tax( $order );

                foreach($order_details as $details):
                    $product.='Product Name :'. $details['item_name_'.$item].' Amount : '.$details['amount_'.$item].'  ';
                    $item ++;
                endforeach;

            }
            //create client
            $soapclient = new nusoap_client('https://secure.transaxgateway.com/roxapi/rox.asmx?WSDL','wsdl');
            //gather parameters

            $params['TransactionType'] = 'sale';
			if(!is_null($this->settings['testmode']) && $this->settings['testmode'] != 'no'){
			    $params['GatewayUserName'] = 'TransaxDemo';
			    $params['GatewayPassword'] = 'Pineapple123!';
			} else {
			    $params['GatewayUserName'] = $this->settings['gatewayusername'];
			    $params['GatewayPassword'] = $this->settings['gatewaypassword'];
			}
            $params['IPAddress'] = $_SERVER['REMOTE_ADDR'];
            $params['PaymentType'] = 'creditcard';
            $params['CCNumber'] = $_POST['transax-card-number'];
            $params['CCExpDate'] = transax_woocommerce_convert_date($_POST['transax-card-expiry']);
            $params['CVV'] = $_POST['transax-card-cvc'];
			$params['Amount'] = $order->get_total();
            $params['OrderID'] = $order->get_id();
            $params['OrderDescription'] = $product;
			if(!is_null($this->settings['ziponly']) && $this->settings['ziponly'] != 'yes'){
				$params['FirstName'] = $order->get_billing_first_name();
				$params['LastName'] = $order->get_billing_last_name();
				$params['Company'] = $order->get_billing_company();
				$params['Address1'] = $order->get_billing_address_1();
				$params['Address2'] = $order->get_billing_address_2();
				$params['City'] = $order->get_billing_city();
				$params['State'] = $this->get_transax_state( $order->get_billing_country(), $order->get_billing_state() );
			}
            $params['Zip'] = $order->get_billing_postcode();
            $params['Country'] = $order->get_billing_country();
            $params['Phone'] = $order->get_billing_phone();
            $params['EMail'] = $order->get_billing_email();

            //Shipping
            if ( 'yes' == $this->send_shipping ){
				$params['address_override'] = ( $this->address_override == 'yes' ) ? 1 : 0;
				$params['no_shipping'] = 0;
				$params['ShippingFirstName'] = $order->get_shipping_first_name();
				$params['ShippingLastName'] = $order->get_shipping_last_name();
				$params['ShippingCompany'] = $order->get_shipping_company();
				$params['ShippingAddress1'] = $order->get_shipping_address_1();
				$params['ShippingAddress2'] = $order->get_shipping_address_2();
				$params['ShippingCity'] = $order->get_shipping_city();
				$params['ShippingState'] = $this->get_transax_state( $order->get_shipping_country(), $order->get_shipping_state() );
				$params['ShippingCountry'] = $order->get_shipping_country();
                $params['ShippingZip'] = $order->get_shipping_postcode();
            }else{
                $params['no_shipping'] = 1;
            }

            $productArray = array();
            $x = 0;
            foreach( $order->get_items() as $item_id => $item_product ) {
                $productArray[$x] = $item_product->get_product();
                $x++;
            }
			$soapclient->setUseCURL(true);
			$soapclient->setCurlOption(CURLOPT_SSLVERSION, '6'); // TLS 1.2

            $proxy  = $soapclient->getProxy();
			if($proxy == null) {
				wc_add_notice('Error creating proxy.', 'error');
				error_log($soapclient->getError());
			}
            $result = $proxy->ProcessTransaction(Array('objparameters' => $params));

            if($result['ProcessTransactionResult']['STATUS_CODE']== 1){

                $order->update_status($this->settings['successstatus'], __('Payment Successful. Thank you for your order.', 'woocommerce'));
                if(strcmp($this->settings['successstatus'], 'completed'))
                {
                    // Reduce stock levels
                    wc_reduce_stock_levels($order->get_id());
                }
                // Remove cart
                $woocommerce->cart->empty_cart();
                return array(
                    'result'    => 'success',
                    'redirect'  =>  $this->get_return_url( $order )
                );
            }
            else{
                //transaction Failed. Update the order status to Failed
                $order->update_status($this->settings['failurestatus'] , __($result['ProcessTransactionResult']['STATUS_MSG'], 'woocommerce'));
                if(strcmp($this->settings['failurestatus'],'on-hold'))
                {
                    // presumed actions
                    // Reduce stock levels
                    wc_reduce_stock_levels($order->get_id());
                    // Remove cart
                    $woocommerce->cart->empty_cart();
                }
                return array(
                    'result'    => 'success',
                    'redirect'  =>  $this->get_return_url( $order )
                    );
            }

            // until I know better this stuff returns a success because the transaction with transax was successfull not that there was a successful credit card process

        }

        /* Output for the order received page.   */

        function thankyou() {
            if ( $this->instructions )
                echo wpautop( wptexturize( $this->instructions ) );
        }

    }

    /**
     * Add the Gateway to WooCommerce
     * */
    function woocommerce_add_transax_gateway($methods) {
        $methods[] = 'WC_Transax_Payment_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_transax_gateway');

    /**
     * Add the Gateway to WooCommerce
     * */
    function reg_script() {
        wp_enqueue_script('script_query',plugins_url('js/trans.js', __FILE__),array(),'1.0',true);
        wp_localize_script('script_query', 'Site', array('ajax_url' => admin_url() . 'admin-ajax.php'));

    }
    add_action( 'wp_enqueue_scripts', 'reg_script' );

	function custom_checkout_field_validation() {
        global $woocommerce;

        if($_POST['payment_method']=='transax'){
            if (!$_POST['transax-card-number'])
                wc_add_notice('<strong> CC Number</strong> is a required field.', 'error');
            if(!$_POST['transax-card-expiry'])
			{
                wc_add_notice('<strong> CC Expiry Date  </strong> is a required field.', 'error');
			} else if (strlen($_POST['transax-card-expiry']) != 7 && strlen($_POST['transax-card-expiry']) != 9 )
			{
				wc_add_notice('<strong> CC Expiry Date </strong> must be in MM/YY or MM/YYYY format.', 'error');
			} else if (strlen($_POST['transax-card-expiry']) == 9)
			{
				$year = trim(explode("/", $_POST['transax-card-expiry'])[1]);
				if($year[0] != '2' && $year[1] != '0') {
					wc_add_notice('<strong> CC Expiry Date </strong> year must begin with 20 or be two digits', 'error');
				}
			}
            if(!$_POST['transax-card-cvc'])
                wc_add_notice('<strong> CVV Number </strong> is a required field.', 'error');
        }
    }
    add_action('woocommerce_checkout_process', 'custom_checkout_field_validation');
	
	function transax_woocommerce_convert_date($woocommercedate){
		$datepieces = explode("/", $woocommercedate);
		$month = trim($datepieces[0]);
		$year = trim($datepieces[1]);
		if(strlen($year) == 4) {
			$year = substr($year, 2);
		}
		return $month . $year;
	}
}
