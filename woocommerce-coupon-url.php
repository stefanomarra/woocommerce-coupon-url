<?php
/*
Plugin Name: WooCommerce Coupon URL
Plugin URI: https://www.stefanomarra.com
Description: Apply a coupon code with query string parameter "coupon" and show discounted product price automatically
Version: 1.0
Author: Stefano Marra
Author URI: https://www.stefanomarra.com
License: GPL2
*/

class WooCommerce_Coupon_URL {

	/**
	 * Initialize
	 */
	function __construct() {

		/**
		 * Capture and store the coupon code in wc session
		 */
		add_action('init', array( &$this, 'store_coupon_code_to_session'));

		/**
		 * Apply the coupon store in the wc session in checkout page
		 */
		add_action( 'woocommerce_before_checkout_form', array(&$this, 'add_discount_to_checkout'), 10, 0 );

		/**
		 * Apply discount on simple, grouped and external product prices
		 */
		add_filter('woocommerce_product_get_price', array( &$this, 'apply_discount_to_price' ), 99, 2 );
		add_filter('woocommerce_product_get_regular_price', array( &$this, 'apply_discount_to_price' ), 99, 2 );

		/**
		 * Apply discount on variation prices
		 */
		add_filter('woocommerce_product_variation_get_regular_price', array( &$this, 'apply_discount_to_price' ), 99, 2 );
		add_filter('woocommerce_product_variation_get_price', array( &$this, 'apply_discount_to_price' ), 99, 2 );

		/**
		 * Apply discount on variable (price range) prices
		 */
		add_filter('woocommerce_variation_prices_price', array( &$this, 'apply_discount_to_variable_price' ), 99, 3 );
		add_filter('woocommerce_variation_prices_regular_price', array( &$this, 'apply_discount_to_variable_price' ), 99, 3 );

		if ( is_admin() ) {}
	}

	function store_coupon_code_to_session() {
		if ( isset($_GET['coupon']) ) {


			// Ensure that customer session is started
			if ( !WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie(true);
			}

			// var_dump(WC()->session->get('coupon_code'));die();

			// Check and register coupon code in a custom session variable
			$coupon_code = WC()->session->get('coupon_code');
			if (empty($coupon_code)) {
				$coupon_code = esc_attr( $_GET['coupon'] );
				WC()->session->set( 'coupon_code', $coupon_code ); // Set the coupon code in session
			}
		}
	}

	function add_discount_to_checkout( ) {

		// Set coupon code
		$coupon_code = WC()->session->get('coupon_code');
		if ( ! empty( $coupon_code ) && ! WC()->cart->has_discount( $coupon_code ) ){
			WC()->cart->add_discount( $coupon_code ); // apply the coupon discount
			WC()->session->__unset('coupon_code'); // remove coupon code from session
		}
	}

	function get_discounted_price($price) {
		$coupon_code = WC()->session->get('coupon_code');

		/**
		 * No coupon set
		 */
		if (!$coupon_code) {
			return $price;
		}

		$coupon = new WC_Coupon($coupon_code);

		$WC_Discounts = new WC_Discounts();
		if ( !$WC_Discounts->is_coupon_valid($coupon) ) {
			return $price;
		}

		if ( $coupon->is_type('percent_product') || $coupon->is_type('percent') ) {
			if ( $price > 0 ) {
				$discount = ($price / 100) * $coupon->get_amount();
				return $price - $discount;
			}
		}

		return $price;
	}

	function apply_discount_to_price( $price, $product ) {
		if ( is_woocommerce() ) {
			return $this->get_discounted_price($price);
		}

		return $price;
	}

	function apply_discount_to_variable_price( $price, $variation, $product ) {
		if ( is_woocommerce() ) {
			return $this->get_discounted_price($price);
		}

		return $price;
	}

}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	// add_action( 'woocommerce_loaded', 'woocommerce_coupon_url_run', 10, 1 );
	// function woocommerce_coupon_url_run() {
	// 	$woocommerce_coupon_url = new WooCommerce_Coupon_URL();
	// }
	$woocommerce_coupon_url = new WooCommerce_Coupon_URL();
}
