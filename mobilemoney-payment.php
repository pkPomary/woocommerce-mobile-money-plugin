<?php
/*
 * Plugin Name: WooCommerce Mobile Money CI
 * Description: Recevez simplement des paiements via Mobile Money.
 * Author: Nehemie KOFFI
 * Author URI: https://nehemiekoffi.wordpress.com
 * Version: 1.0.3
 *
 */

 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */

add_filter( 'woocommerce_payment_gateways', 'mobilemoney_payment' );
function mobilemoney_payment( $gateways ) {
	$gateways[] = 'WC_MobileMoney_Payment_Gateway'; // your class name is here
	return $gateways;
}

/**
 * Display field value on the order edit page
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'mmpayment_display_admin_order_meta', 10, 1 );

function mmpayment_display_admin_order_meta($order){
    echo '<p><strong>'.__('Mobile Money Operator').':</strong> ' . get_post_meta( $order->id, 'Mobile Money Operator', true ) . '</p>';
    echo '<p><strong>'.__('Mobile Money Number').':</strong> ' . get_post_meta( $order->id, 'Mobile Money Number', true ) . '</p>';
    echo '<p><strong>'.__('Mobile Money transaction ID').':</strong> ' . get_post_meta( $order->id, 'Mobile Money transaction ID', true ) . '</p>';
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'init_mobilemoney_payment' );
function init_mobilemoney_payment() {
 
	class WC_MobileMoney_Payment_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {

            $this->id = 'wc_mmpayment'; // payment gateway plugin ID
            $this->icon = plugins_url( 'mmoney-icons.png', __FILE__ ); // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Mobile Money Payment';
            $this->method_description = 'Pay from your mobile money account'; // will be displayed on the options page
         
            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();
            
            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->icon = $this->get_option( 'icon_url' ) != "" ? $this->get_option( 'icon_url' ) : $this->icon;
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
        
            // This action hook saves the settings
	        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
 
            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

 		}
 
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Mobile Money Payment',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Mobile Money',
                    'desc_tip'    => true,
                ),
                'icon_url' => array(
                    'title'       => 'Icon URL',
                    'type'        => 'text',
                    'description' => "Link of the icon that the user will see",
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay from your mobile money account',
                ),
                'mtnmoney_msisdn' => array(
                    'title'       => 'MTN Money Number',
                    'type'        => 'text',
                    'default'     => '0246128647',
                ),
                'mtnmoney_ussd_code' => array(
                    'title'       => 'MTN Money USSD Code',
                    'type'        => 'text',
                    'default'     => '*170#',
                ),
                'telecelmoney_msisdn' => array(
                    'title'       => 'Telecel Cash Number',
                    'type'        => 'text',
                    'default'     => '0246128647',
                ),
                'telecelmoney_ussd_code' => array(
                    'title'       => 'Telecel Cash USSD Code',
                    'type'        => 'text',
                    'default'     => '*110#',
                ),
                'atmoney_msisdn' => array(
                    'title'       => 'AT Money Number',
                    'type'        => 'text',
                    'default'     => '0246128647',
                ),
                'atmoney_ussd_code' => array(
                    'title'       => 'AT Money USSD Code',
                    'type'        => 'text',
                    'default'     => '*111#',
                )
            );
 
 
         }
         
 
		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {

            global $woocommerce;

            echo 
            "<fieldset>
            <p id='mm_operator_field' class='form-row form-row-wide'>
                <label>Please make a deposit of ".$woocommerce->cart->get_cart_total()." sur l'un des numéros ci-dessous : </label> 
                <select name='mm_operator'>
                ";

                if($this->get_option( 'mtnmoney_msisdn') != ""){
                    echo '<option value="MTN Money">MTN Money ('. $this->get_option( 'mtnmoney_msisdn') .')</option>';
                }
                if($this->get_option( 'telecelmoney_msisdn') != ""){
                    echo '<option value="Telecel Cash">Telecel Cash ('. $this->get_option( 'telecelmoney_msisdn') .')</option>';
                }
                if($this->get_option( 'atmoney_msisdn') != ""){
                    echo '<option value="AT Money">AT Money ('. $this->get_option( 'atmoney_msisdn') .')</option>';
                }
                
                
            echo '
            </select>
            <span id="mm_instruction"></span>
            </p>
            <p class="form-row form-row-wide validate-required">
                <label>Mobile Money Number <abbr class="required" title="obligatory">*</abbr></label>
                <input type="text" class="input-text " name="mm_sender_msisdn" placeholder="Number that made the deposit" value="">
            </p>
            <p class="form-row form-row-wide validate-required">
                <label>Transaction ID <abbr class="required" title="obligatory">*</abbr></label>
                <input type="text" autocomplete="off" class="input-text " name="mm_transaction_id" placeholder="Find this ID in the confirmation SMS" value="">
            </p>
            </fieldset>'; 
 
		}
 
		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 	public function payment_scripts() {

            // CSS
            wp_enqueue_style('mmpayment_style', plugins_url( 'mobilemoney-payment.css', __FILE__ ));

            // JS
           // wp_register_script('mmpayment_jquery', plugins_url( 'jquery-3.5.1.js', __FILE__ ) );
           //wp_enqueue_script("jquery");

            // and this is our custom JS in your plugin directory that works with token.js
            wp_register_script('mmpayment_js', plugins_url( 'mobilemoney-payment.js', __FILE__ ), array("jquery"), true);
        
            wp_enqueue_script( 'mmpayment_js' );

            wp_localize_script( 'mmpayment_js', 'mmpayment_data', 
                array( 
                'mtnmoney_ussd_code' => $this->get_option( 'mtnmoney_ussd_code' ),
                'telecelmoney_ussd_code'=> $this->get_option( 'telecelmoney_ussd_code' ),
                'atmoney_ussd_code' => $this->get_option( 'atmoney_ussd_code' )
                ) 
            );

	 	}
 
		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {
 
 
                if( empty( $_POST[ 'mm_sender_msisdn' ]) ) {
                    wc_add_notice(  'The telephone number is obligatory!', 'error' );
                    return false;
                }

                if( empty( $_POST[ 'mm_transaction_id' ]) ) {
                    wc_add_notice(  "Please specify the transaction ID!", 'error' );
                    return false;
                }

                return true;
             
 
		}
 
		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
            global $woocommerce;
            $order = new WC_Order( $order_id );

            // Save additional fields
            $order->update_meta_data( 'Mobile Money Operator', sanitize_text_field( $_POST['mm_operator'] ) );
            $order->update_meta_data( 'Mobile Money Number', sanitize_text_field( $_POST['mm_sender_msisdn'] ) );
            $order->update_meta_data( 'Mobile Money transaction ID', sanitize_text_field( $_POST['mm_transaction_id'] ) );
        
            // Mark as on-hold (we're awaiting the cheque)
            $order->update_status('on-hold', __( 'Waiting for confirmation.', 'woocommerce' ));
        
            // Remove cart
            $woocommerce->cart->empty_cart();
        
            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order )
            );
	 	}

 	}
}
