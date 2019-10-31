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

		/**
		 * Handle price html
		 */
		add_filter('woocommerce_get_price_html', array( &$this, 'apply_discount_price_html' ), 99, 2 );

		if ( is_admin() ) {}
	}

	function get_session_coupon_code() {

		// Ensure that customer session is started
		if ( !WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie(true);
		}

		$coupon_code = WC()->session->get('coupon_code');

		return $coupon_code;
	}

	function store_coupon_code_to_session() {
		if ( isset($_GET['coupon']) ) {

			if ( !$this->is_coupon_valid_for_current_product($_GET['coupon']) ) {
				return false;
			}

			// Check and register coupon code in a custom session variable
			$coupon_code = $this->get_session_coupon_code();
			if (empty($coupon_code)) {
				$coupon_code = esc_attr( $_GET['coupon'] );
				WC()->session->set( 'coupon_code', $coupon_code ); // Set the coupon code in session
			}
		}
	}

	function add_discount_to_checkout( ) {

		// Set coupon code
		$coupon_code = $this->get_session_coupon_code();
		if ( ! empty( $coupon_code ) && ! WC()->cart->has_discount( $coupon_code ) ){
			WC()->cart->add_discount( $coupon_code ); // apply the coupon discount
			WC()->session->__unset('coupon_code'); // remove coupon code from session
		}
	}

	function is_coupon_valid_for_current_product($coupon_code = null) {
		global $product;

		if ( !$coupon_code ) {
			return false;
		}

		$coupon = new WC_Coupon($coupon_code);

		if ( isset($product) && $product ) {
			$product_id = $product->get_id();
			$coupon_product_ids = (array)$coupon->get_product_ids();

			if ( count($coupon_product_ids) && !in_array($product_id, $coupon_product_ids) ) {
				return false;
			}
		}

		return true;
	}

	function get_discounted_price($price) {

		$coupon_code = $this->get_session_coupon_code();

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

		if ( !$this->is_coupon_valid_for_current_product($coupon_code) ) {
			return $price;
		}

		switch ($coupon->get_discount_type()) {
			case 'percent_product':
			case 'percent':
				if ( $price > 0 ) {
					$discount = ($price / 100) * $coupon->get_amount();
					return $price - $discount;
				}
				break;

			case 'fixed_product':
				return $price - $coupon->get_amount();
				break;
		}
		if ( $coupon->is_type('percent_product') || $coupon->is_type('percent') ) {
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

	function apply_discount_price_html( $price, $product ) {
		if ( is_woocommerce() && $this->get_session_coupon_code() ) {
			$regular_price = get_post_meta( $product->get_id(), '_regular_price', true);
			return '<del>' . wc_price($regular_price) . '</del> ' . str_replace( '<ins>', ' Now:<ins>', $price );
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
