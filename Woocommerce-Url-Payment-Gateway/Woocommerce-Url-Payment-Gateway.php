<?php
/**
 * Plugin Name: Woocommerce-Url-Payment-Gateway
 * Description: Add direct url payment
 * Author: Swag
 * Version: 1.0.2
 */
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}



add_action( 'woocommerce_product_after_variable_attributes', 'art_term_production_fields', 10, 3 );
function art_term_production_fields( $loop, $variation_data, $variation ) {
   woocommerce_wp_text_input( array(
      'id'                => '_term_prod_var[' . $variation->ID . ']', // id поля
      'label'             => 'Payment URL', // Надпись над полем
      'description'       => 'Укажите ссылку',// Описание поля
      'desc_tip'          => 'true', // Всплывающая подсказка
      'placeholder'       => '', // Надпись внутри поля
      'type'              => 'url', // Тип поля
      'custom_attributes' => array( // Произвольные аттрибуты
         'step' => 'any', // Шаг значений
         'min'  => '0', // Минимальное значение
      ),
      'value'             => get_post_meta( $variation->ID, '_term_prod_var', true ),
   ) );
}
add_action( 'woocommerce_save_product_variation', 'art_save_variation_settings_fields', 10, 2 );
function art_save_variation_settings_fields( $post_id ) {
   $woocommerce__term_prod_var = $_POST['_term_prod_var'][ $post_id ];
   if (isset($woocommerce__term_prod_var) && ! empty( $woocommerce__term_prod_var ) ) {
      update_post_meta( $post_id, '_term_prod_var', esc_attr( $woocommerce__term_prod_var ) );
   }
}


add_action( 'woocommerce_product_options_general_product_data', 'art_woo_add_custom_fields' );
global $product, $post;
function art_woo_add_custom_fields() {
   // текстовое поле
   woocommerce_wp_text_input( array(
      'id'                => '_single_var',
      'label'             => 'Payment url',
      'placeholder'       => '',
      'desc_tip'          => 'url',
      'description'       => 'Укажите ссылку',
   ));
}
add_action( 'woocommerce_process_product_meta', 'art_woo_custom_fields_save', 10, 2 );
function art_woo_custom_fields_save( $post_id ) {
   // Сохранение текстового поля
  $woocommerce_single_var = $_POST['_single_var'];
  if ( !empty($woocommerce_single_var) ) {
  update_post_meta( $post_id, '_single_var', esc_attr( $woocommerce_single_var ) );
}
}

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_offline_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_Offline';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_offline_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_offline_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=offline_gateway' ) . '">' . __( 'Configure', 'wc-gateway-offline' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_offline_gateway_plugin_links' );


/**
 * Url Payment Gateway
 *
 * Provides an Url Payment Gateway.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Offline
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		SkyVerge
 */
add_action( 'plugins_loaded', 'wc_offline_gateway_init', 11 );

function wc_offline_gateway_init() {

	class WC_Gateway_Offline extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'offline_gateway';
			$this->icon               = apply_filters('woocommerce_offline_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Woocommerce-Url-Payment-Gateway', 'wc-gateway-offline' );
			$this->method_description = __( 'Allows direct url payments' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  
			// Customer Emails
			// add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_offline_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-offline' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Url Payment', 'wc-gateway-offline' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-offline' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-offline' ),
					'default'     => __( 'Credit/Debit card, PayPal business', 'wc-gateway-offline' ),
					'desc_tip'    => true,
				),
				

				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-offline' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-offline' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	
	
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		// public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
		// 	if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
		// 		echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
		// 	}
		// }

		


		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {		
			$order = wc_get_order( $order_id );
			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting offline payment', 'wc-gateway-offline' ) );
			
			// Reduce stock levels
			$order->reduce_order_stock();

			foreach ($order->get_items() as $item_key => $item ):
				if ( $item->get_variation_id() != 'Null' ) 
				{
					$variation_id = $item->get_variation_id();
                    
                    // DEBUG
	    			//$filename = 'metadata3.txt';	 
		    		//file_put_contents($filename, $variation_id);

					$url = get_post_meta( $variation_id, '_term_prod_var', true);
					if ($url != 'Null') {
						$quantity = $item['quantity'];
						break;
					}
				}
				else
                if ( $item->get_product() != 'Null' ) 
				{
					$product = $item->get_product();
                    $product_id = $product->get_id();
                    //$product_name = $product->get_name();
    				
                    // DEBUG
	    			//$filename = 'metadata3.txt';	 
		    		//file_put_contents($filename, $product);

					$url = get_post_meta( $product_id, '_single_var', true);
					if ($url != 'Null') {
						$quantity = $item['quantity'];
						break;
					}
				}
			endforeach;		
								
			if ($url != 'Null') {
				$url = str_replace("&amp;", "&", $url);
				$url = str_replace("Qnty", $quantity, $url);
				
				// DEBUG
				//$filename = 'metadata.txt';	 
				//file_put_contents($filename, $url);
				//file_put_contents($filename, $quantity);
			}

			//	Remove cart
			WC()->cart->empty_cart();
		
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $url
			);
		}
	}
	
	
	 // end \WC_Gateway_Offline class
}