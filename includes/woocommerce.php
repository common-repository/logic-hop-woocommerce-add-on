<?php

if (!defined('ABSPATH')) die;

/**
 * WooCommerce functionality.
 *
 * Provides WooCommerce functionality.
 *
 * @since      1.0.0
 * @package    LogicHop
 */

class LogicHop_WooCommerce {

	/**
	 * Core functionality & logic class
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      LogicHop_Core    $logic    Core functionality & logic.
	 */
	private $logic;

	/**
	 * Plugin version
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      integer    $version    Core functionality & logic.
	 */
	public $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    	1.0.0
	 * @param       object    $logic	LogicHop_Core functionality & logic.
	 */
	public function __construct( $logic ) {
		$this->logic		= $logic;
		$this->version		= '3.0.5';

		add_action( 'init', array( $this, 'storefront_setup' ) );
	}

	/**
	 * Is the Storefront theme or child-theme active
	 * False when logichop_woocommerce_storefront filter returning false
	 *
	 * @since    	3.0.2
	 * @return      boolean     If Storefront is active
	 */
	public function storefront_active () {
		if ( 'storefront' == get_option( 'template' ) && apply_filters( 'logichop_woocommerce_storefront', true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Add actions when Storefront theme is enabled
	 *
	 * @since    	1.0.0
	 * @param       object    $logic	LogicHop_Core functionality & logic.
	 */
	public function storefront_setup () {
		if ( $this->storefront_active() ) {
			add_action( 'homepage', array( $this, 'storefront_homepage_widget_area' ), 90 );
		}
	}

	/**
	 * Storefront Homepage Widget Section
	 *
	 * @since    	3.0.2
	 */
	public function storefront_homepage_widget_area () {
		if ( is_active_sidebar( 'logichop-storefront-homepage-widget' ) ) {
			dynamic_sidebar( 'logichop-storefront-homepage-widget' );
		}
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @since    	1.0.0
	 * @return      boolean     If WooCommerce is active
	 */
	public function active () {
		if (class_exists('woocommerce') && function_exists('WC')) return true;
		return false;
	}

	/**
	 * Check if WooCommerce is legacy version
	 *
	 * @since    	1.0.1
	 * @return      boolean     If WooCommerce is a legacy version
	 */
	public function is_legacy () {
		if ($this->active()) {
			if (WC()->version >= '3.0.0') return false;
		}
		return true;
	}

	/**
	 * Get customer data
	 *
	 * @since    	1.0.0
	 * @return      object     Customer data
	 */
	public function customer_data () {

		$woocommerce = $this->logic->data_factory->get_value( 'WooCommerce' );

		$customer = new stdclass;
		$customer->Active 			= false;
		$customer->ID 				= 0;
		$customer->Username 		= '';
		$customer->Email	 		= '';
		$customer->FirstName 		= '';
		$customer->LastName 		= '';
		$customer->AvatarURL 		= '';
		$customer->OrderCount 		= 0;
		$customer->TotalSpend 		= 0;
		$customer->PayingCustomer 	= false;
		$customer->OrderLookup 		= (isset($woocommerce->Customer->OrderLookup)) ? $woocommerce->Customer->OrderLookup : false;

		if ($this->active()) {
			$uid = get_current_user_id();
			if ($uid) {
				$customer->ID = $uid;
				if ($this->is_legacy()) {
					$user = get_userdata($uid);
					if ($user) {
						$customer->Username 		= $user->user_login;
						$customer->Email 			= $user->user_email;
						$customer->FirstName 		= $user->first_name;
						$customer->LastName 		= $user->last_name;
						$customer->AvatarURL 		= get_avatar_url($user->user_email);
						$customer->OrderCount 		= wc_get_customer_order_count($uid);
						$customer->TotalSpend 		= wc_get_customer_total_spent($uid);
						$customer->PayingCustomer 	= WC()->customer->is_paying_customer($uid);
					}
				} else {
					$customer->Username 		= WC()->customer->get_username();
					$customer->Email 			= WC()->customer->get_email();
					$customer->FirstName 		= WC()->customer->get_first_name();
					$customer->LastName 		= WC()->customer->get_last_name();
					$customer->AvatarURL 		= WC()->customer->get_avatar_url();
					$customer->OrderCount 		= wc_get_customer_order_count($uid);
					$customer->TotalSpend 		= wc_get_customer_total_spent($uid);
					$customer->PayingCustomer 	= WC()->customer->get_is_paying_customer();
				}
			}
			if (!$customer->TotalSpend) 	$customer->TotalSpend = 0;
			if ($customer->Username != '') 	$customer->Active = true;
		}

		return $customer;
	}

	/**
	 * Get customer order history
	 *
	 * @since    	1.0.2
	 * @return      object     Order history
	 */
	public function customer_orders ($uid = false) {
		$orders = new stdclass;
		$orders->Products 	= array();
		$orders->Categories	= array();

		if ($this->active() && $uid) {
			$quantity = ($this->is_legacy()) ? 'qty' : 'quantity';
			$all_orders = get_posts(array(
							'post_type' 	=> 'shop_order',
							'post_status' 	=> 'wc-completed',
							'numberposts' 	=> -1,
							'meta_key'    	=> '_customer_user',
							'meta_value'  	=> $uid
						));
			if ($all_orders) {
				foreach ($all_orders as $o) {
					$order = wc_get_order($o->ID);
               		$products = ($order) ? $order->get_items() : false;
               		if ($products) {
               			foreach ($products as $p) {
							$pid = $p['product_id'];
							$product_count = (isset($orders->Products[$pid])) ? $orders->Products[$pid] : 0;
							$product_count += $p[$quantity];
							$orders->Products[$pid] = $product_count;

							if ($categories = get_the_terms($pid, 'product_cat')) {
								foreach ($categories as $cat) {
									$cat_count = (isset($orders->Categories[$cat->term_id])) ? $orders->Categories[$cat->term_id] : 0;
									$cat_count += $p[$quantity];
									$orders->Categories[$cat->term_id] = $cat_count;
								}
							}
						}
	               	}
				}
			}
		}
		return $orders;
	}

	/**
	 * Get order products
	 *
	 * @since    	1.0.2
	 */
	public function order_products ($order_id) {
		if ($this->active()) {
			$quantity = ($this->is_legacy()) ? 'qty' : 'quantity';
			$order = wc_get_order($order_id);
			$products = ($order) ? $order->get_items() : false;
			if ($products) {

				$woocommerce = $this->logic->data_factory->get_value( 'WooCommerce' );

				foreach ($products as $p) {
					$pid = $p['product_id'];
					$product_count = (isset($woocommerce->ProductsPurchased[$pid])) ? $woocommerce->ProductsPurchased[$pid] : 0;
					$product_count += $p[$quantity];
					$woocommerce->ProductsPurchased[$pid] = $product_count;

					if ($categories = get_the_terms($pid, 'product_cat')) {
						foreach ($categories as $cat) {
							$cat_count = (isset($woocommerce->CategoriesPurchased[$cat->term_id])) ? $woocommerce->CategoriesPurchased[$cat->term_id] : 0;
							$cat_count += $p[$quantity];
							$woocommerce->CategoriesPurchased[$cat->term_id] = $cat_count;
						}
					}
				}

				$this->logic->data_factory->set_value( 'WooCommerce', $woocommerce );

			}
		}
	}

	/**
	 * Get shopping cart count
	 *
	 * @since    	1.0.0
	 * @return      integer     Items in shopping cart
	 */
	public function cart_count () {
		if ($this->active()) return WC()->cart->get_cart_contents_count();
		return 0;
	}

	/**
	 * Get shopping cart contents
	 *
	 * @since    	1.0.0
	 * @return      integer     Items in shopping cart
	 */
	public function cart_contents () {
		$cart = array ();
		if ($this->active()) {
			$contents = WC()->cart->get_cart();
			if ($contents) {
				foreach ($contents as $c) {
					$cart[$c['product_id']] = $c['quantity'];
				}
			}
		}
		return $cart;
	}
}
