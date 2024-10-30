<?php

	/*
		Plugin Name: Logic Hop WooCommerce Add-on
		Plugin URI:	https://logichop.com/docs/woocommerce
		Description: The Logic Hop WooCommerce Add-on brings the power of personalization to WordPress with WooCommerce.
		Author: Logic Hop
		Version: 3.0.6
		Author URI: https://logichop.com
	*/

	if (!defined('ABSPATH')) die;

	if ( is_admin() ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		if ( ! is_plugin_active( 'logichop/logichop.php' ) && ! is_plugin_active( 'logic-hop/logichop.php' ) ) {
			add_action( 'admin_notices', 'logichop_woocommerce_plugin_notice' );
		}
	}

	function logichop_woocommerce_plugin_notice () {
		$message = sprintf(__('The Logic Hop WooCommerce Add-on requires the Logic Hop plugin. Please download and activate the <a href="%s" target="_blank">Logic Hop plugin</a>.', 'logichop'),
							'http://wordpress.org/plugins/logic-hop/'
						);

		printf('<div class="notice notice-warning is-dismissible">
						<p>
							%s
						</p>
					</div>',
					$message
				);
	}

	require_once 'includes/woocommerce.php';

	/**
	 * Plugin activation/deactviation routine to clear Logic Hop transients
	 *
	 * @since    2.0.1
	 */
	function logichop_woocommerce_activation () {
		delete_transient( 'logichop' );
    }
	register_activation_hook( __FILE__, 'logichop_woocommerce_activation' );
	register_deactivation_hook( __FILE__, 'logichop_woocommerce_activation' );

	/**
	 * Register admin notices
	 *
	 * @since    2.0.0
	 */
	function logichop_woocommerce_admin_notice () {
		global $logichop;

		$message = '';

		if ( ! $logichop->logic->addon_active('woocommerce') ) {
			$message = sprintf(__('The Logic Hop WooCommerce Add-on requires a <a href="%s" target="_blank">Logic Hop License Key or Data Plan</a>.', 'logichop'),
							'https://logichop.com/get-started/?ref=addon-woocommerce'
						);
		}

		if ( $message ) {
			printf('<div class="notice notice-warning is-dismissible">
						<p>
							%s
						</p>
					</div>',
					$message
				);
		}
	}
	add_action( 'logichop_admin_notice', 'logichop_woocommerce_admin_notice' );

	/**
	 * Plugin page links
	 *
	 * @since    1.0.0
	 * @param    array		$links			Plugin links
	 * @return   array  	$new_links 		Plugin links
	 */
	function logichop_plugin_action_links_woocommerce ($links) {
		$new_links = array();
    $new_links['settings'] = sprintf( '<a href="%s" target="_blank">%s</a>', 'https://logichop.com/docs/woocommerce', 'Instructions' );
 		$new_links['deactivate'] = $links['deactivate'];
 		return $new_links;
	}
	add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'logichop_plugin_action_links_woocommerce');

	/**
	 * Initialize functionality
	 *
	 * @since    1.0.0
	 */
	function logichop_integration_init_woocommerce () {
		global $logichop;

		if ( isset( $logichop->logic ) && $logichop->logic->addon_active('woocommerce') ) {
			$logichop->logic->woocommerce = new LogicHop_WooCommerce($logichop->logic);

			add_filter('logichop_metabox_post_types', 'logichop_metabox_woo_products');
			add_filter('logichop_primary_metabox_tracking', 'logichop_primary_metabox_tracking_woo', 10, 2);
			add_filter('logichop_editor_shortcode_variables', 'logichop_editor_shortcode_vars_woo');
			add_filter('logichop_gutenberg_variables', 'logichop_gutenberg_vars_woo');
			add_filter( 'logichop_data_object_create', 'logichop_data_object_create' );

			add_action('logichop_update_data', 'logichop_update_data_woocommerce', 10, 2);
			add_action('woocommerce_thankyou', 'logichop_woocommerce_payment_complete');
			add_action('logichop_data_retrieve', 'logichop_data_retrieve_woocommerce', 10, 1);
			add_filter('logichop_condition_default_get', 'logichop_condition_default_woocommerce');
			add_filter('logichop_client_meta_integrations', 'logichop_client_meta_woocommerce');
			add_action('logichop_admin_enqueue_scripts', 'logichop_admin_enqueue_scripts_woocommerce', 10, 2);
		}
	}
	add_action('logichop_integration_init', 'logichop_integration_init_woocommerce');

	/**
	 * Add widgets to enhance WooCommerce
	 *
	 * @since    	3.0.2
	 */
	function logichop_integration_widgets_woocommerce () {
		if ( 'storefront' == get_option( 'template' ) && apply_filters( 'logichop_woocommerce_storefront', true ) ) {
			register_sidebar(
				array(
					'name' => __( 'Storefront Homepage', 'logic-hop-woocommerce' ),
					'id' => 'logichop-storefront-homepage-widget',
					'before_widget' => '<section id="%1$s" class="storefront-product-section storefront-homepage-widget-section %2$s">',
					'after_widget'  => '</section>',
					'before_title'  => '<h2 class="section-title">',
					'after_title'   => '</h2>',
				)
			);
		}
	}
	add_action( 'widgets_init', 'logichop_integration_widgets_woocommerce' );

	/**
	 * Add Logic Hop metabox to custom post type
	 *
	 * @since    1.0.0
	 */
	function logichop_metabox_woo_products ($post_types) {
		$post_types[] = 'product';
		return $post_types;
	}

	/**
	 * Hide Logic Hop metabox tracking selector
	 *
	 * @since    1.0.0
	 */
	function logichop_primary_metabox_tracking_woo ($hide_tracking, $post) {
		if ($post->post_type == 'product') return true;
		return false;
	}

	/**
 	 * Add variables to editor
 	 *
 	 * @since    2.0.0
 	 * @return   string    	Variables as datalist options
 	 */
	function logichop_editor_shortcode_vars_woo ($datalist) {
		$datalist .= '<option value="WooCommerce.Customer.Username">WooCommerce Username</option>';
		$datalist .= '<option value="WooCommerce.Customer.Email">WooCommerce Email</option>';
		$datalist .= '<option value="WooCommerce.Customer.FirstName">WooCommerce First Name</option>';
		$datalist .= '<option value="WooCommerce.Customer.LastName">WooCommerce Last Name</option>';
		$datalist .= '<option value="WooCommerce.Customer.AvatarURL">WooCommerce Avatar URL</option>';
		$datalist .= '<option value="WooCommerce.Customer.OrderCount">WooCommerce Order Count</option>';
		$datalist .= '<option value="WooCommerce.Customer.TotalSpend">WooCommerce Total Spend</option>';
		$datalist .= '<option value="WooCommerce.Cart">WooCommerce Cart Count</option>';
		return $datalist;
	}

	/**
	 * Add variables to Gutenberg plugin
	 *
	 * @since    2.0.3
	 * @return   string    	Variables as array
	 */
	function logichop_gutenberg_vars_woo ( $options ) {

		$options[] = [ 'value' => 'WooCommerce.Customer.FirstName',
										'label' => 'WooCommerce First Name'
								];
		$options[] = [ 'value' => 'WooCommerce.Customer.LastName',
										'label' => 'WooCommerce Last Name'
								];
		$options[] = [ 'value' => 'WooCommerce.Customer.Username',
										'label' => 'WooCommerce Username'
								];
		$options[] = [ 'value' => 'WooCommerce.Customer.Email',
										'label' => 'WooCommerce Email'
								];
		$options[] = [ 'value' => 'WooCommerce.Customer.AvatarURL',
										'label' => 'WooCommerce Avatar URL'
								];
		$options[] = [ 'value' => 'WooCommerce.Cart',
										'label' => 'WooCommerce Cart Count'
								];
		$options[] = [ 'value' => 'WooCommerce.Customer.OrderCount',
										'label' => 'WooCommerce Order Count'
								];
		$options[] = [ 'value' => 'WooCommerce.Customer.TotalSpend',
										'label' => 'WooCommerce Total Spend'
								];

		return $options;
	}

	/**
	 * Display products in Pages
	 *
	 * @since    1.0.0
	 */
	function logichop_condition_pages_get_json_woo ($pages) {

		$query = new WP_Query(array(
						'post_type' => 'product',
						'post_status' => 'publish',
						'posts_per_page' => -1,
						'order' => 'ASC',
						'orderby' > 'ID',
						'meta_query' => array(
							array(
								'key' => '_logichop_track_page',
								'value' => true,
							   	'compare' => '	='
							)
						)
					));

		if ($query) {
			foreach ($query->posts as $p) {
				$pages->{$p->ID} = $p->post_title;
			}
		}

		return $pages;
	}
	//add_filter('logichop_condition_pages_get_json', 'logichop_condition_pages_get_json_woo');

	/**
	 * Create default data object
	 *
	 * @since    1.0.0
	 */
	function logichop_data_object_create ( $data = null ) {
		if ( is_null( $data ) ) {
			$data = new stdclass;
		}
		$data->WooCommerce = new stdclass;
		$data->WooCommerce->Customer = new stdclass;
		$data->WooCommerce->Cart = 0;
		$data->WooCommerce->InCart = array ();
		$data->WooCommerce->Products = array ();
		$data->WooCommerce->ProductsSession = array ();
		$data->WooCommerce->ProductsPurchased = array ();
		$data->WooCommerce->Category = array ();
		$data->WooCommerce->Categories = array ();
		$data->WooCommerce->CategoriesPurchased = array ();
		$data->WooCommerce->CategoriesSession = array ();
		$data->WooCommerce->Tag = array ();
		$data->WooCommerce->Tags = array ();
		$data->WooCommerce->TagsSession = array ();

		return $data;
	}

	/**
	 * Update user data
	 *
	 * @since    1.0.0
	 */
	function logichop_update_data_woocommerce ($post_id, $post_type) {
		global $logichop;

		if ( is_null( $logichop->logic->data_factory->get_value( 'WooCommerce' ) ) ) {
			logichop_data_object_create();
		}

		$woocommerce = $logichop->logic->data_factory->get_value( 'WooCommerce' );

		$woocommerce->Customer 	= $logichop->logic->woocommerce->customer_data();
		$woocommerce->Cart 			= $logichop->logic->woocommerce->cart_count();
		$woocommerce->InCart 		= $logichop->logic->woocommerce->cart_contents();
		$woocommerce->Category 	= array ();
		$woocommerce->Tag 			= array ();

		if ( $woocommerce->Customer->ID != 0 && ! $woocommerce->Customer->OrderLookup ) { // ORDERS HAVE NOT BEEN LOOKED-UP
			$orders = $logichop->logic->woocommerce->customer_orders( $woocommerce->Customer->ID );
			$woocommerce->ProductsPurchased = $orders->Products;
			$woocommerce->CategoriesPurchased = $orders->Categories;
			$woocommerce->Customer->OrderLookup = true;
		}

		if ($post_type == 'product') {
			if ($categories = get_the_terms($post_id, 'product_cat')) {
				foreach ($categories as $cat) {
					$woocommerce->Category[$cat->term_id] = 1;

					$value = (isset($woocommerce->Categories[$cat->term_id])) ? $woocommerce->Categories[$cat->term_id] : 0;
					$woocommerce->Categories[$cat->term_id] = $value + 1;	// TRACK CATEGORY VIEW

					$value = (isset($woocommerce->CategoriesSession[$cat->term_id])) ? $woocommerce->CategoriesSession[$cat->term_id] : 0;
					$woocommerce->CategoriesSession[$cat->term_id] = $value + 1;	// TRACK CATEGORY VIEW --> CURRENT SESSION ONLY

					$logichop->logic->data_remote_put('wc_category', $cat->term_id); // TRACK CATEGORY --> STORE CATEGORY VIEW
				}
			}

			if ($tags = get_the_terms($post_id, 'product_tag')) {
				foreach ($tags as $tag) {
					$woocommerce->Tag[$tag->term_id] = 1;

					$value = (isset($woocommerce->Tags[$tag->term_id])) ? $woocommerce->Tags[$tag->term_id] : 0;
					$woocommerce->Tags[$tag->term_id] = $value + 1;	// TRACK TAG VIEW

					$value = (isset($woocommerce->TagsSession[$tag->term_id])) ? $woocommerce->TagsSession[$tag->term_id] : 0;
					$woocommerce->TagsSession[$tag->term_id] = $value + 1;	// TRACK TAG VIEW --> CURRENT SESSION ONLY

					$logichop->logic->data_remote_put('wc_tag', $tag->term_id); // TRACK TAG --> STORE TAG VIEW
				}
			}

			$value = (isset($woocommerce->Products[$post_id])) ? $woocommerce->Products[$post_id] : 0;
			$woocommerce->Products[$post_id] = $value + 1;	// TRACK PRODUCT VIEW

			$value = (isset($woocommerce->ProductsSession[$post_id])) ? $woocommerce->ProductsSession[$post_id] : 0;
			$woocommerce->ProductsSession[$post_id] = $value + 1;	// TRACK PRODUCT VIEW --> CURRENT SESSION ONLY

			$logichop->logic->data_factory->set_value( 'WooCommerce', $woocommerce );
			$logichop->logic->data_remote_put('wc_product', $post_id); // TRACK PRODUCT --> STORE PRODUCT VIEW
		}
	}

	/**
	 * Callback when order payment completed
	 *
	 * @since    1.0.0
	 */
	function logichop_woocommerce_payment_complete ($order_id) {
		global $logichop;

		$orders = $logichop->logic->woocommerce->order_products($order_id);
	}

	/**
	 * Parse data returned from SPF lookup
	 *
	 * @since    1.0.0
	 * @param    array		$data	Store data
	 * @return   boolean   	Data retrieved
	 */
	function logichop_data_retrieve_woocommerce ($data) {
		global $logichop;

		$woocommerce = $logichop->logic->data_factory->get_value( 'WooCommerce' );
		if ( is_array($data) ) {
			$data = array_change_key_case($data, CASE_LOWER);
		}
		
		if (isset($data['wc_product'])) {
			foreach ($data['wc_product'] as $key => $value) {
				$woocommerce->Products[$key] = $value;
			}
		}

		if (isset($data['wc_category'])) {
			foreach ($data['wc_category'] as $key => $value) {
				$woocommerce->Categories[$key] = $value;
			}
		}

		if (isset($data['wc_tag'])) {
			foreach ($data['wc_tag'] as $key => $value) {
				$woocommerce->Tags[$key] = $value;
			}
		}

		$logichop->logic->data_factory->set_value( 'WooCommerce', $woocommerce );
		return false;
	}

	/**
	 * Generate default conditions
	 *
	 * @since    1.0.0
	 * @param    array		$conditions		Array of default conditions
	 * @return   array    	$conditions		Array of default conditions
	 */
	function logichop_condition_default_woocommerce ($conditions) {
		global $logichop;

		$conditions['woocommerce_cart_empty'] = array (
				'title' => "WooCommerce Shopping Cart is Empty",
				'rule'	=> '{"==": [ {"var": "WooCommerce.Cart" }, false ] }',
				'info'	=> "Is the WooCommerce shopping cart empty."
			);
		$conditions['woocommerce_cart_full'] = array (
				'title' => "WooCommerce Shopping Cart has Products",
				'rule'	=> '{"==": [ {"var": "WooCommerce.Cart" }, true ] }',
				'info'	=> "Is the WooCommerce shopping cart is not empty."
			);
		$conditions['woocommerce_customer'] = array (
				'title' => "WooCommerce Customer Data Available",
				'rule'	=> '{"==": [ {"var": "WooCommerce.Customer.Active" }, true ] }',
				'info'	=> "Is WooCommerce customer data available."
			);
		$conditions['woocommerce_paying_customer'] = array (
				'title' => "WooCommerce Paying Customer",
				'rule'	=> '{"==": [ {"var": "WooCommerce.Customer.PayingCustomer" }, true ] }',
				'info'	=> "Is this a WooCommerce paying customer."
			);
		return $conditions;
	}

	/**
	 * Generate client meta data
	 *
	 * @since    1.0.0
	 * @param    array		$integrations	Integration names
	 * @return   array    	$integrations	Integration names
	 */
	function logichop_client_meta_woocommerce ($integrations) {
		$integrations[] = 'woocommerce';
		return $integrations;
	}

	/**
	 * Enqueue scripts
	 *
	 * @since    1.0.0
	 */
	function logichop_admin_enqueue_scripts_woocommerce ($hook, $post_type) {
		global $logichop;

		if ($post_type == 'logichop-conditions') {
			$js_path = sprintf('%sadmin/logichop_woocommerce.js', plugin_dir_url( __FILE__ ));
			wp_enqueue_script( 'logichop_woocommerce', $js_path, array( 'jquery' ), $logichop->logic->woocommerce->version, false );
		}
	}
