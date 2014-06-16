<?php (__FILE__ == $_SERVER['SCRIPT_FILENAME']) ? die(header('Location: /')) : null;

class qsot_core_hacks {
	protected static $o = null;
	protected static $options = null;

	public static function pre_init() {
		// load all the options, and share them with all other parts of the plugin
		$options_class_name = apply_filters('qsot-options-class-name', '');
		if (!empty($options_class_name)) {
			self::$options =& $options_class_name::instance();
			//self::_setup_admin_options();
		}

		$settings_class_name = apply_filters('qsot-settings-class-name', '');
		if (!empty($settings_class_name)) {
			self::$o =& $settings_class_name::instance();
			add_action('qsot-draw-page-template-list', array(__CLASS__, 'page_draw_page_template_list'), 10, 1);
			add_filter('qsot-page-templates-list', array(__CLASS__, 'page_templates_list'), 10, 2);
			add_filter('qsot-get-page-template-list', array(__CLASS__, 'page_get_page_template_list'), 10, 1);
			add_action('add_meta_boxes', array(__CLASS__, 'page_add_meta_boxes'), 100, 2);
			add_action('add_meta_boxes', array(__CLASS__, 'order_add_meta_boxes'), 100, 2);
			add_action('add_meta_boxes', array(__CLASS__, 'order_add_late_meta_boxes'), 100000, 2);
			add_filter('page_template', array(__CLASS__, 'page_template_default'), 10, 1);
			add_filter('qsot-maybe-override-theme_default', array(__CLASS__, 'maybe_override_template'), 10, 3);
			add_action('save_post', array(__CLASS__, 'save_page'), 10, 2);
			add_action('load-post.php', array(__CLASS__, 'hack_template_save'), 1);
			add_action('load-post-new.php', array(__CLASS__, 'hack_template_save'), 1);
			add_action('plugins_loaded', array(__CLASS__, 'plugins_loaded'), 100);

			add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'update_service_fee_subtotal_on_order_creation'), 10, 2);
			add_filter('woocommerce_get_order_note_type', array(__CLASS__, 'get_order_note_type'), 10, 2);

			add_action('save_post', array(__CLASS__, 'update_order_user_addresses'), 1000000, 2);

			add_action('pre_user_query', array(__CLASS__, 'or_user_meta_query'), 100, 1);
			add_action('pre_user_query', array(__CLASS__, 'or_display_name_user_query'), 101, 1);

			add_filter('product_type_options', array(__CLASS__, 'add_no_processing_option'), 999);
			add_action('woocommerce_order_item_needs_processing', array(__CLASS__, 'do_not_process_product'), 10, 3);
			add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_product_meta'), 999, 2);

			add_action('woocommerce_order_actions', array(__CLASS__, 'add_view_customer_facing_emails'), 10, 1);
			add_action('woocommerce_order_action_view-completed-email', array(__CLASS__, 'view_completed_email'), 10, 1);
		}
	}

	public static function plugins_loaded() {
		remove_action('wp_ajax_woocommerce_json_search_customers', 'woocommerce_json_search_customers');
		add_action('wp_ajax_woocommerce_json_search_customers', array(__CLASS__, 'woocommerce_json_search_customers'));

		remove_action('wp_ajax_woocommerce_add_order_note', 'woocommerce_add_order_note');
		add_action('wp_ajax_woocommerce_add_order_note', array(__CLASS__, 'woocommerce_add_order_note'));

		remove_action('wp_ajax_woocommerce_add_order_fee', 'woocommerce_ajax_add_order_fee');
		add_action('wp_ajax_woocommerce_add_order_fee', array(__CLASS__, 'woocommerce_ajax_add_order_fee'));

		remove_action('wp_ajax_woocommerce_add_order_item', 'woocommerce_ajax_add_order_item');
		add_action('wp_ajax_woocommerce_add_order_item', array(__CLASS__, 'woocommerce_ajax_add_order_item'));
	}

	public static function order_add_meta_boxes($post_type, $post) {
		if ($post_type !== 'shop_order') return;

		remove_meta_box('woocommerce-order-notes', 'shop_order', 'side');
		add_meta_box(
			'qsotcommerce-order-notes',
			__('Order Notes', 'woocommerce'),
			array(__CLASS__, 'woocommerce_order_notes_meta_box'),
			'shop_order',
			'side',
			'default'
		);

		remove_meta_box('woocommerce-order-data', 'shop_order', 'normal', 'high');
		add_meta_box(
			'woocommerce-order-data',
			__('Order Data', 'woocommerce'),
			array(__CLASS__, 'woocommerce_order_data_meta_box'),
			'shop_order',
			'normal',
			'high'
		);

		remove_meta_box('woocommerce-order-items', 'shop_order', 'normal', 'high');
		add_meta_box(
			'woocommerce-order-items',
			__( 'Order Items', 'woocommerce' )
					.' <span class="tips" data-tip="'.__('Note: if you edit quantities or remove items from the order you will need to manually update stock levels.', 'woocommerce')
					.'">[?]</span>',
			array(__CLASS__, 'woocommerce_order_items_meta_box'),
			'shop_order',
			'normal',
			'high'
		);
	}

	// effort to work around the new wp core page template existence validation, which prohibits page templates not in the theme
	public static function hack_template_save() {
		wp_reset_vars( array( 'action' ) );
		if ( isset( $_GET['post'] ) )
			$post_id = $post_ID = (int) $_GET['post'];
		elseif ( isset( $_POST['post_ID'] ) )
			$post_id = $post_ID = (int) $_POST['post_ID'];
		else
			$post_id = $post_ID = 0;

		$post = $post_type = $post_type_object = null;

		if ( $post_id )
			$post = get_post( $post_id );

		$post_type = $post ? $post->post_type : ( isset($_REQUEST['post_type']) ? $_REQUEST['post_type'] : false );
		if (!$post_type) return;
		if ($post_type !== 'page') return;
		if (!isset($_REQUEST['page_template'])) return;

		update_post_meta($post->ID, '_wp_page_template', $_REQUEST['page_template']);
		unset($_REQUEST['page_template']);
	}

	public static function order_add_late_meta_boxes($post_type, $post) {
		if ($post_type !== 'shop_order') return;

		remove_meta_box('woocommerce-order-totals', 'shop_order', 'side', 'default');
		add_meta_box(
			'woocommerce-order-totals',
			__( 'Order Totals', 'woocommerce' ),
			array(__CLASS__, 'woocommerce_order_totals_meta_box'),
			'shop_order',
			'side',
			'default'
		);
	}

	public static function add_view_customer_facing_emails($list) {
		$list['view-completed-email'] = __('View Order Receipt', 'qsot');
		return $list;
	}

	public static function view_completed_email($order) {
		$email_exchanger = new WC_Emails();

		$email = new WC_Email_Customer_Completed_Order();
		$email->object = $order;

		?><html>
			<head>
				<title><?php echo $email->get_subject() ?> - Preview - <?php echo get_bloginfo('name') ?></title>
			</head>
			<body>
				<?php echo $email->get_content(); ?>
			</body>
		</html><?php
		exit;
	}

	public static function add_no_processing_option($list) {
		$list['no_processing'] = array(
			'id' => '_no_processing',
			'wrapper_class' => 'show_if_simple show_if_grouped show_if_external show_if_variable no-wrap',
			'label' => __('Bypass Process', 'qsot'),
			'description' => __('Checking this box bypasses the Processing step and marks the order as Complete. (if other products in an order require processing, the order will still goto processing)', 'qsot'),
		);
	
		return $list;
	}

	public static function do_not_process_product($is, $product, $order_id) {
		if (get_post_meta($product->id, '_no_processing', true) == 'yes') $is = false;
		return $is;
	}

	public static function save_product_meta($post_id, $post) {
		$is_ticket = isset($_POST['_no_processing']) ? 'yes' : 'no';
		update_post_meta($post_id, '_no_processing', $is_ticket);
	}

	// add subtotal to fees also, for proper accounting
	public static function update_service_fee_subtotal_on_order_creation($order_id, $posted) {
		global $woocommerce;

		$order = new WC_Order($order_id);
		foreach ($order->get_fees() as $oiid => $fee) {
			if (woocommerce_get_order_item_meta($oiid, '_line_subtotal', true) === '')
				woocommerce_update_order_item_meta($oiid, '_line_subtotal', woocommerce_get_order_item_meta($oiid, '_line_total', true));
		}
	}

	// copied from woocommerce/woocommerce-ajax.php
	// modified to allow class assignment and template overriding
	function woocommerce_ajax_add_order_fee() {
		global $woocommerce;

		check_ajax_referer( 'order-item', 'security' );

		$order_id 	= absint( $_POST['order_id'] );
		$order 		= new WC_Order( $order_id );

		// Add line item
		$item_id = woocommerce_add_order_item( $order_id, array(
			'order_item_name' 		=> '',
			'order_item_type' 		=> 'fee'
		) );

		// Add line item meta
		if ( $item_id ) {
			woocommerce_add_order_item_meta( $item_id, '_tax_class', '' );
			woocommerce_add_order_item_meta( $item_id, '_line_subtotal', '' );
			woocommerce_add_order_item_meta( $item_id, '_line_total', '' );
			woocommerce_add_order_item_meta( $item_id, '_line_tax', '' );
		}

		$items = $order->get_items();
		$item = $items[$item_id];

		// allow class specification
		$class = apply_filters('woocommerce_admin_order_items_class', '', $item, $order);

		//include( trailingslashit($woocommerce->plugin_path).'admin/post-types/writepanels/order-fee-html.php' );
		include(apply_filters('qsot-woo-template', 'post-types/meta-boxes/views/html-order-fee.php', 'admin'));

		// Quit out
		die();
	}

	// copied from woocommerce/woocommerce-ajax.php
	// modified to allow template overriding
	function woocommerce_ajax_add_order_item() {
		global $woocommerce, $wpdb;

		check_ajax_referer( 'order-item', 'security' );

		$item_to_add = sanitize_text_field( $_POST['item_to_add'] );
		$order_id = absint( $_POST['order_id'] );

		// Find the item
		if ( ! is_numeric( $item_to_add ) )
			die();

		$post = get_post( $item_to_add );

		if ( ! $post || ( $post->post_type !== 'product' && $post->post_type !== 'product_variation' ) )
			die();

		$_product = get_product( $post->ID );

		$order = new WC_Order( $order_id );
		$class = 'new_row';

		// Set values
		$item = array();

		$item['product_id'] 			= $_product->id;
		$item['variation_id'] 			= isset( $_product->variation_id ) ? $_product->variation_id : '';
		$item['name'] 					= $_product->get_title();
		$item['tax_class']				= $_product->get_tax_class();
		$item['qty'] 					= 1;
		$item['line_subtotal'] 			= number_format( (double) $_product->get_price_excluding_tax(), 2, '.', '' );
		$item['line_subtotal_tax'] 		= '';
		$item['line_total'] 			= number_format( (double) $_product->get_price_excluding_tax(), 2, '.', '' );
		$item['line_tax'] 				= '';

		$item = apply_filters('woocommerce_ajax_before_add_order_item', $item, $_product, $order);
		// Add line item
		$item_id = woocommerce_add_order_item( $order_id, array(
			'order_item_name' 		=> $item['name'],
			'order_item_type' 		=> 'line_item'
		) );

		$class = apply_filters('woocommerce_admin_order_items_class', $class, $item, $order);

		// Add line item meta
		if ( $item_id ) {
			woocommerce_add_order_item_meta( $item_id, '_qty', $item['qty'] );
			woocommerce_add_order_item_meta( $item_id, '_tax_class', $item['tax_class'] );
			woocommerce_add_order_item_meta( $item_id, '_product_id', $item['product_id'] );
			woocommerce_add_order_item_meta( $item_id, '_variation_id', $item['variation_id'] );
			woocommerce_add_order_item_meta( $item_id, '_line_subtotal', $item['line_subtotal'] );
			woocommerce_add_order_item_meta( $item_id, '_line_subtotal_tax', $item['line_subtotal_tax'] );
			woocommerce_add_order_item_meta( $item_id, '_line_total', $item['line_total'] );
			woocommerce_add_order_item_meta( $item_id, '_line_tax', $item['line_tax'] );
		}

		do_action( 'woocommerce_ajax_add_order_item_meta', $item_id, $item );

		//include( 'admin/post-types/writepanels/order-item-html.php' );
		include(apply_filters('qsot-woo-template', 'post-types/meta-boxes/views/html-order-item.php', 'admin'));

		// Quit out
		die();
	}
	// copied from woocommerce/admin/post-types/writepanels/writepanel-order_data.php
	/**
	 * Displays the order totals meta box.
	 *
	 * @access public
	 * @param mixed $post
	 * @return void
	 */
	public function woocommerce_order_totals_meta_box( $post ) {
		global $woocommerce, $theorder, $wpdb;

		if ( ! is_object( $theorder ) )
			$theorder = new WC_Order( $post->ID );

		$order = $theorder;

		$data = get_post_meta( $post->ID );
		?>
		<div class="totals_group">
			<h4><span class="discount_total_display inline_total"></span><?php _e( 'Discounts', 'woocommerce' ); ?></h4>
			<ul class="totals">

				<li class="left">
					<label><?php _e( 'Cart Discount:', 'woocommerce' ); ?>&nbsp;<a class="tips" data-tip="<?php _e( 'Discounts before tax - calculated by comparing subtotals to totals.', 'woocommerce' ); ?>" href="#">[?]</a></label>
					<input type="number" step="any" min="0" id="_cart_discount" name="_cart_discount" placeholder="0.00" value="<?php
						if ( isset( $data['_cart_discount'][0] ) )
							echo esc_attr( $data['_cart_discount'][0] );
					?>" class="calculated" />
				</li>

				<li class="right">
					<label><?php _e( 'Order Discount:', 'woocommerce' ); ?>&nbsp;<a class="tips" data-tip="<?php _e( 'Discounts after tax - user defined.', 'woocommerce' ); ?>" href="#">[?]</a></label>
					<input type="number" step="any" min="0" id="_order_discount" name="_order_discount" placeholder="0.00" value="<?php
						if ( isset( $data['_order_discount'][0] ) )
							echo esc_attr( $data['_order_discount'][0] );
					?>" />
				</li>

			</ul>

			<ul class="wc_coupon_list">

			<?php
				$coupons = $order->get_items( array( 'coupon' ) );

				foreach ( $coupons as $item_id => $item ) {

					$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'shop_coupon' AND post_status = 'publish' LIMIT 1;", $item['name'] ) );

					$link = $post_id ? admin_url( 'post.php?post=' . $post_id . '&action=edit' ) : admin_url( 'edit.php?s=' . esc_url( $item['name'] ) . '&post_status=all&post_type=shop_coupon' );

					echo '<li class="tips code" data-tip="' . esc_attr( woocommerce_price( $item['discount_amount'] ) ) . '"><a href="' . $link . '"><span>' . esc_html( $item['name'] ). '</span></a></li>';

				}
			?>

			</ul>
			<div class="clear"></div>

			<?php do_action('woocommerce_admin_order_totals_after_coupon_list', $order) ?>

		</div>

		<?php /* adding if statement around shipping to hide it if it is irrelevant, like many other woocommerce features */ ?>
		<?php if (get_option('woocommerce-order-totals') == 'yes'): ?>
			<div class="totals_group">
				<h4><?php _e( 'Shipping', 'woocommerce' ); ?></h4>
				<ul class="totals">

					<li class="wide">
						<label><?php _e( 'Label:', 'woocommerce' ); ?></label>
						<input type="text" id="_shipping_method_title" name="_shipping_method_title" placeholder="<?php _e( 'The shipping title the customer sees', 'woocommerce' ); ?>" value="<?php
							if ( isset( $data['_shipping_method_title'][0] ) )
								echo esc_attr( $data['_shipping_method_title'][0] );
						?>" class="first" />
					</li>

					<li class="left">
						<label><?php _e( 'Cost:', 'woocommerce' ); ?></label>
						<input type="number" step="any" min="0" id="_order_shipping" name="_order_shipping" placeholder="0.00 <?php _e( '(ex. tax)', 'woocommerce' ); ?>" value="<?php
							if ( isset( $data['_order_shipping'][0] ) )
								echo esc_attr( $data['_order_shipping'][0] );
						?>" class="first" />
					</li>

					<li class="right">
						<label><?php _e( 'Method:', 'woocommerce' ); ?></label>
						<select name="_shipping_method" id="_shipping_method" class="first">
							<option value=""><?php _e( 'N/A', 'woocommerce' ); ?></option>
							<?php
								$chosen_method 	= ! empty( $data['_shipping_method'][0] ) ? $data['_shipping_method'][0] : '';
								$found_method 	= false;

								if ( $woocommerce->shipping() ) {
									foreach ( $woocommerce->shipping->load_shipping_methods() as $method ) {

										if ( strpos( $chosen_method, $method->id ) === 0 )
											$value = $chosen_method;
										else
											$value = $method->id;

										echo '<option value="' . esc_attr( $value ) . '" ' . selected( $chosen_method == $value, true, false ) . '>' . esc_html( $method->get_title() ) . '</option>';
										if ( $chosen_method == $value )
											$found_method = true;
									}
								}

								if ( ! $found_method && ! empty( $chosen_method ) ) {
									echo '<option value="' . esc_attr( $chosen_method ) . '" selected="selected">' . __( 'Other', 'woocommerce' ) . '</option>';
								} else {
									echo '<option value="other">' . __( 'Other', 'woocommerce' ) . '</option>';
								}
							?>
						</select>
					</li>

				</ul>
				<?php do_action( 'woocommerce_admin_order_totals_after_shipping', $post->ID ) ?>
				<div class="clear"></div>
			</div>
		<?php endif; ?>

		<?php if ( get_option( 'woocommerce_calc_taxes' ) == 'yes' ) : ?>

		<div class="totals_group tax_rows_group">
			<h4><?php _e( 'Tax Rows', 'woocommerce' ); ?></h4>
			<div id="tax_rows" class="total_rows">
				<?php
					global $wpdb;

					$rates = $wpdb->get_results( "SELECT tax_rate_id, tax_rate_country, tax_rate_state, tax_rate_name, tax_rate_priority FROM {$wpdb->prefix}woocommerce_tax_rates ORDER BY tax_rate_name" );

					$tax_codes = array();

					foreach( $rates as $rate ) {
						$code = array();

						$code[] = $rate->tax_rate_country;
						$code[] = $rate->tax_rate_state;
						$code[] = $rate->tax_rate_name ? sanitize_title( $rate->tax_rate_name ) : 'TAX';
						$code[] = absint( $rate->tax_rate_priority );

						$tax_codes[ $rate->tax_rate_id ] = strtoupper( implode( '-', array_filter( $code ) ) );
					}

					foreach ( $order->get_taxes() as $item_id => $item ) {
						include(apply_filters('qsot-woo-template', 'post-types/meta-boxes/views/html-order-tax.php', 'admin'));
					}
				?>
			</div>
			<h4><a href="#" class="add_tax_row"><?php _e( '+ Add tax row', 'woocommerce' ); ?> <span class="tips" data-tip="<?php _e( 'These rows contain taxes for this order. This allows you to display multiple or compound taxes rather than a single total.', 'woocommerce' ); ?>">[?]</span></a></a></h4>
			<div class="clear"></div>
		</div>
		<div class="totals_group">
			<h4><span class="tax_total_display inline_total"></span><?php _e( 'Tax Totals', 'woocommerce' ); ?></h4>
			<ul class="totals">

				<li class="left">
					<label><?php _e( 'Sales Tax:', 'woocommerce' ); ?>&nbsp;<a class="tips" data-tip="<?php _e( 'Total tax for line items + fees.', 'woocommerce' ); ?>" href="#">[?]</a></label>
					<input type="number" step="any" min="0" id="_order_tax" name="_order_tax" placeholder="0.00" value="<?php
						if ( isset( $data['_order_tax'][0] ) )
							echo esc_attr( $data['_order_tax'][0] );
					?>" class="calculated" />
				</li>

				<li class="right">
					<label><?php _e( 'Shipping Tax:', 'woocommerce' ); ?></label>
					<input type="number" step="any" min="0" id="_order_shipping_tax" name="_order_shipping_tax" placeholder="0.00" value="<?php
						if ( isset( $data['_order_shipping_tax'][0] ) )
							echo esc_attr( $data['_order_shipping_tax'][0] );
					?>" />
				</li>

			</ul>
			<div class="clear"></div>
		</div>

		<?php endif; ?>

		<div class="totals_group">
			<h4><?php _e( 'Order Totals', 'woocommerce' ); ?></h4>
			<ul class="totals">

				<li class="left">
					<label><?php _e( 'Order Total:', 'woocommerce' ); ?></label>
					<input type="number" step="any" min="0" id="_order_total" name="_order_total" placeholder="0.00" value="<?php
						if ( isset( $data['_order_total'][0] ) )
							echo esc_attr( $data['_order_total'][0] );
					?>" class="calculated" />
				</li>

				<li class="right">
					<label><?php _e( 'Payment Method:', 'woocommerce' ); ?></label>
					<select name="_payment_method" id="_payment_method" class="first">
						<option value=""><?php _e( 'N/A', 'woocommerce' ); ?></option>
						<?php
							$chosen_method 	= ! empty( $data['_payment_method'][0] ) ? $data['_payment_method'][0] : '';
							$found_method 	= false;

							if ( $woocommerce->payment_gateways() ) {
								foreach ( $woocommerce->payment_gateways->payment_gateways() as $gateway ) {
									if ( $gateway->enabled == "yes" ) {
										echo '<option value="' . esc_attr( $gateway->id ) . '" ' . selected( $chosen_method, $gateway->id, false ) . '>' . esc_html( $gateway->get_title() ) . '</option>';
										if ( $chosen_method == $gateway->id )
											$found_method = true;
									}
								}
							}

							if ( ! $found_method && ! empty( $chosen_method ) ) {
								echo '<option value="' . esc_attr( $chosen_method ) . '" selected="selected">' . __( 'Other', 'woocommerce' ) . '</option>';
							} else {
								echo '<option value="other">' . __( 'Other', 'woocommerce' ) . '</option>';
							}
						?>
					</select>
				</li>

			</ul>
			<div class="clear"></div>

			<?php do_action('woocommerce_admin_after_order_totals', $order) ?>

		</div>
		<p class="buttons">
			<?php if ( get_option( 'woocommerce_calc_taxes' ) == 'yes' ) : ?>
				<button type="button" class="button calc_line_taxes"><?php _e( 'Calc taxes', 'woocommerce' ); ?></button>
			<?php endif; ?>
			<button type="button" class="button calc_totals button-primary"><?php _e( 'Calc totals', 'woocommerce' ); ?></button>
		</p>
		<?php
	}

	// copied from woocommerce/admin/post-types/writepanels/writepanel-order_data.php
	// modified to allow for additional buttons and additional actions, as well as templaet overriding
	public static function woocommerce_order_items_meta_box( $post ) {
		global $wpdb, $thepostid, $theorder, $woocommerce;
		//$writepanel_path = trailingslashit($woocommerce->plugin_path).'admin/post-types/writepanels/';

		if ( ! is_object( $theorder ) )
			$theorder = new WC_Order( $thepostid );

		$order = $theorder;

		$data = get_post_meta( $post->ID );
		do_action('woocommerce_admin_before_order_items', $post, $order, $data);
		?>
		<div class="woocommerce_order_items_wrapper">
			<table cellpadding="0" cellspacing="0" class="woocommerce_order_items">
				<thead>
					<tr>
						<th><input type="checkbox" class="check-column" /></th>
						<th class="item" colspan="2"><?php _e( 'Item', 'woocommerce' ); ?></th>

						<?php do_action( 'woocommerce_admin_order_item_headers' ); ?>

						<?php if ( get_option( 'woocommerce_calc_taxes' ) == 'yes' ) : ?>
							<th class="tax_class"><?php _e( 'Tax Class', 'woocommerce' ); ?>&nbsp;<a class="tips" data-tip="<?php _e( 'Tax class for the line item', 'woocommerce' ); ?>." href="#">[?]</a></th>
						<?php endif; ?>

						<th class="quantity"><?php _e( 'Qty', 'woocommerce' ); ?></th>

						<th class="line_cost"><?php _e( 'Totals', 'woocommerce' ); ?>&nbsp;<a class="tips" data-tip="<?php _e( 'Line subtotals are before pre-tax discounts, totals are after.', 'woocommerce' ); ?>" href="#">[?]</a></th>

						<?php if ( get_option( 'woocommerce_calc_taxes' ) == 'yes' ) : ?>
							<th class="line_tax"><?php _e( 'Tax', 'woocommerce' ); ?></th>
						<?php endif; ?>

						<?php do_action( 'woocommerce_admin_after_order_item_headers' ); /*@@@@LOUSHOU - allow addition of columns to the end of the list */ ?>
						<th width="1%">&nbsp;</th>
					</tr>
				</thead>
				<tbody id="order_items_list">

					<?php
						// List order items
						$order_items = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', array( 'line_item', 'fee' ) ) );

						foreach ( $order_items as $item_id => $item ) {

							$class = apply_filters('woocommerce_admin_order_items_class', '', $item, $order);
							switch ( $item['type'] ) {
								case 'line_item' :
									$_product 	= $order->get_product_from_item( $item );
									$item_meta 	= $order->get_item_meta( $item_id );

									//include( $writepanel_path.'order-item-html.php' );
									//@@@@LOUSHOU - allow overtake of template
									include(apply_filters('qsot-woo-template', 'post-types/meta-boxes/views/html-order-item.php', 'admin'));
								break;
								case 'fee' :
									//include( $writepanel_path.'order-fee-html.php' );
									//@@@@LOUSHOU - allow overtake of template
									include(apply_filters('qsot-woo-template', 'post-types/meta-boxes/views/html-order-fee.php', 'admin'));
								break;
							}

							do_action( 'woocommerce_order_item_' . $item['type'] . '_html' );

						}
					?>
				</tbody>
			</table>
		</div>

		<p class="bulk_actions">
			<select>
				<option value=""><?php _e( 'Actions', 'woocommerce' ); ?></option>
				<optgroup label="<?php _e( 'Edit', 'woocommerce' ); ?>">
					<option value="delete"><?php _e( 'Delete Lines', 'woocommerce' ); ?></option>
				</optgroup>
				<optgroup label="<?php _e( 'Stock Actions', 'woocommerce' ); ?>">
					<option value="reduce_stock"><?php _e( 'Reduce Line Stock', 'woocommerce' ); ?></option>
					<option value="increase_stock"><?php _e( 'Increase Line Stock', 'woocommerce' ); ?></option>
				</optgroup>
				<?php do_action('woocommerce_order_items_bulk_actions', $order, $data, $order_items) ?>
			</select>

			<button type="button" class="button do_bulk_action wc-reload" title="<?php _e( 'Apply', 'woocommerce' ); ?>"><span><?php _e( 'Apply', 'woocommerce' ); ?></span></button>
		</p>

		<div class="add_items" style="text-align:right; margin:1em 0;">
			<select id="add_item_id" class="ajax_chosen_select_products_and_variations" multiple="multiple" data-placeholder="<?php _e( 'Search for a product&hellip;', 'woocommerce' ); ?>" style="width: 400px"></select>
			<div class="buttons" style="margin-right:9px;">
				<button type="button" class="button add_order_item"><?php _e( 'Add item(s)', 'woocommerce' ); ?></button>
				<button type="button" class="button add_order_fee"><?php _e( 'Add fee', 'woocommerce' ); ?></button>
				<?php do_action('woocommerce_order_item_add_line_buttons', $order, $data, $order_items) ?>
			</div>
		</div>
		<div class="clear"></div>
		<?php
	}

	// copied from woocommerce/woocommerce-ajax.php
	// modified to allow other comment types to have an action to save info on
	function woocommerce_add_order_note() {

		global $woocommerce;

		check_ajax_referer( 'add-order-note', 'security' );

		$post_id 	= (int) $_POST['post_id'];
		$note		= wp_kses_post( trim( stripslashes( $_POST['note'] ) ) );
		$note_type	= $_POST['note_type'];

		$is_customer_note = $note_type == 'customer' ? 1 : 0;

		if ( $post_id > 0 ) {
			$order = new WC_Order( $post_id );
			$comment_id = $order->add_order_note( $note, $is_customer_note );
			do_action('woocommerce_ajax_save_order_note', $comment_id, $note_type, $note, $order);

			echo '<li rel="'.$comment_id.'" class="note ';
			if ($is_customer_note) echo 'customer-note';
			echo '"><div class="note_content">';
			echo wpautop( wptexturize( $note ) );
			echo '</div><p class="meta">';
			echo '('.apply_filters('woocommerce_get_order_note_type', 'private', get_comment($comment_id)).')';
			echo '<a href="#" class="delete_note">'.__( 'Delete note', 'woocommerce' ).'</a>';
			echo '</p>';
			echo '</li>';

		}

		// Quit out
		die();
	}

	// copied from woocommerce/admin/post-types/writepanels/writepanel-order_notes.php
	// modified to allow different comment types
	public static function woocommerce_order_notes_meta_box() {
		global $woocommerce, $post;

		$args = array(
			'post_id' 	=> $post->ID,
			'approve' 	=> 'approve',
			'type' 		=> 'order_note'
		);

		// changing required permission for viewing comments to the 'edit_shop_order' permissiosn instead of manage_woocommerce, because manage_woocommerce gives access to 
		// woocommerce settings, which some users who need access to order notes may not have (like box-office and box-office-manager)
		if (current_user_can('edit_shop_order')) remove_filter('comments_clauses', 'woocommerce_exclude_order_comments');
		$notes = get_comments( $args );
		if (current_user_can('edit_shop_order')) add_filter('comments_clauses', 'woocommerce_exclude_order_comments');

		echo '<ul class="order_notes">';

		if ( $notes ) {
			foreach( $notes as $note ) {
				$note_classes = get_comment_meta( $note->comment_ID, 'is_customer_note', true ) ? array( 'customer-note', 'note' ) : array( 'note' );

				?>
				<li rel="<?php echo absint( $note->comment_ID ) ; ?>" class="<?php echo implode( ' ', $note_classes ); ?>">
					<div class="note_content">
						<?php echo wpautop( wptexturize( wp_kses_post( $note->comment_content ) ) ); ?>
					</div>
					<p class="meta">
						<?php printf( __( 'added %s ago', 'woocommerce' ), human_time_diff( strtotime( $note->comment_date_gmt ), current_time( 'timestamp', 1 ) ) ); ?>
						(<?php echo apply_filters('woocommerce_get_order_note_type', 'private', $note) ?>)
						<a href="#" class="delete_note"><?php _e( 'Delete note', 'woocommerce' ); ?></a>
					</p>
				</li>
				<?php
			}
		} else {
			echo '<li>' . __( 'There are no notes for this order yet.', 'woocommerce' ) . '</li>';
		}

		echo '</ul>';
		?>
		<div class="add_note">
			<h4><?php _e( 'Add note', 'woocommerce' ); ?> <img class="help_tip" data-tip='<?php esc_attr_e( 'Add a note for your reference, or add a customer note (the user will be notified).', 'woocommerce' ); ?>' src="<?php echo $woocommerce->plugin_url(); ?>/assets/images/help.png" height="16" width="16" /></h4>
			<p>
				<textarea type="text" name="order_note" id="add_order_note" class="input-text" cols="20" rows="5"></textarea>
			</p>
			<p>
				<?php
					$note_types = apply_filters('woocommerce_order_note_types', array(
						'customer' => __('Customer note', 'woocommerce'),
					), $post);
				?>
				<select name="order_note_type" id="order_note_type">
					<option value=""><?php _e( 'Private note', 'woocommerce' ); ?></option>
					<?php foreach ($note_types as $val => $label): ?>
						<option value="<?php echo esc_attr($val) ?>"><?php echo $label ?></option>
					<?php endforeach; ?>
				</select>
				<a href="#" class="add_note button"><?php _e( 'Add', 'woocommerce' ); ?></a>
			</p>
		</div>
		<script type="text/javascript">

			jQuery('#qsotcommerce-order-notes')

			.on( 'click', 'a.add_note', function() {
				if (!jQuery('textarea#add_order_note').val()) return;

				jQuery('#qsotcommerce-order-notes').block({ message: null, overlayCSS: { background: '#fff url(<?php echo $woocommerce->plugin_url(); ?>/assets/images/ajax-loader.gif) no-repeat center', opacity: 0.6 } });

				var data = {
					action: 		'woocommerce_add_order_note',
					post_id:		'<?php echo $post->ID; ?>',
					note: 			jQuery('textarea#add_order_note').val(),
					note_type:		jQuery('select#order_note_type').val(),
					security: 		'<?php echo wp_create_nonce("add-order-note"); ?>'
				};

				jQuery.post( '<?php echo admin_url('admin-ajax.php'); ?>', data, function(response) {

					jQuery('ul.order_notes').prepend( response );
					jQuery('#qsotcommerce-order-notes').unblock();
					jQuery('#add_order_note').val('');

				});

				return false;

			})

			.on( 'click', 'a.delete_note', function() {

				var note = jQuery(this).closest('li.note');

				jQuery(note).block({ message: null, overlayCSS: { background: '#fff url(<?php echo $woocommerce->plugin_url(); ?>/assets/images/ajax-loader.gif) no-repeat center', opacity: 0.6 } });

				var data = {
					action: 		'woocommerce_delete_order_note',
					note_id:		jQuery(note).attr('rel'),
					security: 		'<?php echo wp_create_nonce("delete-order-note"); ?>'
				};

				jQuery.post( '<?php echo admin_url('admin-ajax.php'); ?>', data, function(response) {

					jQuery(note).remove();

				});

				return false;

			});

		</script>
		<?php
	}

	public static function get_order_note_type($type, $note) {
		if (get_comment_meta($note->comment_ID, 'is_customer_note', true) == 1) $type = 'customer note';
		return $type;
	}

	/**
	 * Search for customers and return json
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_json_search_customers() {

		check_ajax_referer( 'search-customers', 'security' );

//		header( 'Content-Type: application/json; charset=utf-8' );

		$term = urldecode( stripslashes( strip_tags( $_GET['term'] ) ) );

		if ( empty( $term ) )
			die();

		$default = isset( $_GET['default'] ) ? $_GET['default'] : __( 'Guest', 'woocommerce' );

		$found_customers = array( '' => $default );

		$customers_query = new WP_User_Query( array(
			'fields'			=> 'all',
			'orderby'			=> 'display_name',
			'search'			=> '*' . $term . '*',
			'search_columns'	=> array( 'ID', 'user_login', 'user_email', 'user_nicename' ),
			/*
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => 'billing_first_name',
					'value' => $term,
					'compare' => 'LIKE',
				),
				array(
					'key' => 'billing_last_name',
					'value' => $term,
					'compare' => 'LIKE',
				),
				array(
					'key' => 'billing_email',
					'value' => $term,
					'compare' => 'LIKE',
				),
			),
			*/
		) );

		// SELECT DISTINCT SQL_CALC_FOUND_ROWS wp_users.*
		// FROM wp_users 
		// INNER JOIN wp_usermeta
		//		ON (wp_users.ID = wp_usermeta.user_id)
		// INNER JOIN wp_usermeta AS mt1
		//		ON (wp_users.ID = mt1.user_id)
		// INNER JOIN wp_usermeta AS mt2
		//		ON (wp_users.ID = mt2.user_id)
		// WHERE 1=1
		//		AND (ID = 'john' OR user_login LIKE '%john%' OR user_email LIKE '%john%' OR user_nicename LIKE '%john%')
		//		AND (
		//			(wp_usermeta.meta_key = 'billing_first_name' AND CAST(wp_usermeta.meta_value AS CHAR) LIKE '%john%') OR
		//			(mt1.meta_key = 'billing_last_name' AND CAST(mt1.meta_value AS CHAR) LIKE '%john%') OR 
		//			(mt2.meta_key = 'billing_email' AND CAST(mt2.meta_value AS CHAR) LIKE '%john%')
		//		)
		// ORDER BY display_name ASC;

		$customers = $customers_query->get_results();

		if ( $customers ) {
			foreach ( $customers as $customer ) {
				$found_customers[ $customer->ID ] = $customer->display_name . ' (#' . $customer->ID . ' &ndash; ' . sanitize_email( $customer->user_email ) . ')';
			}
		}

		echo json_encode( $found_customers );
		die();
	}

	public static function or_user_meta_query(&$query) {
		global $wpdb;
		//dont remember why i did this , but i just had to fix it to not modify wp_capabilities
		$query->query_where = preg_replace('#^(.*)(and\s*\(\s*\(\s*'.$wpdb->usermeta.'\.(?!meta_key = \'wp_capabilities))(.*)$#si', '\1or (('.$wpdb->usermeta.'.\3', $query->query_where);
	}

	public static function or_display_name_user_query(&$query) {
		global $wpdb;
		$term = preg_replace('#\s+#', '%', urldecode( stripslashes( strip_tags( $_GET['term'] ) ) ));
		$term = empty($term) && is_admin() ? preg_replace('#\s+#', '%', urldecode( stripslashes( strip_tags( $_REQUEST['s'] ) ) )) : $term;
		if (!empty($term)) $query->query_where = preg_replace('#^(.*)(where 1=1 and \()(.*)#si', '\1\2'.$wpdb->prepare('display_name like %s or ', '%'.$term.'%').'\3', $query->query_where);
		$query->query_orderby = ' GROUP BY '.$wpdb->users.'.id '.$query->query_orderby;
	}

	public static function save_page($post_id, $post) {
		$opost = clone $post;
		if ($post->post_type == 'revision') $post = get_post($post->post_parent);
		if (!post_type_supports($post->post_type, 'page-attributes')) return;

		$templates = array_flip(apply_filters('qsot-get-page-template-list', array()));
		if (isset($_POST['page_template']) && isset($templates[$_POST['page_template']])) {
			update_post_meta($opost->ID, '_wp_page_template', $_POST['page_template']);
			update_post_meta($post->ID, '_wp_page_template', $_POST['page_template']);
		}
	}

	public static function page_get_page_template_list($current) {
		$templates = apply_filters('qsot-page-templates-list', get_page_templates(), $template);
		return $templates;
	}

	public static function page_template_default($template) {
		$post = get_queried_object();
		if ($post->post_type != 'page') return $template;

		$page_template = get_page_template_slug();

		return apply_filters('qsot-maybe-override-theme_default', $template, $page_template, 'page.php');
	}

	public static function maybe_override_template($template='', $possible_plugin_filename='', $theme_filename='') {
		if (empty($possible_plugin_filename) || empty($theme_filename)) return $template;

		$defaults = array(
			trailingslashit(get_template_directory()).$theme_filename => 1,
			trailingslashit(get_stylesheet_directory()).$theme_filename => 1,
		);
		if (!isset($defaults[$template])) return $template;

		$dirs = apply_filters('qsot-theme-template-dirs', array(self::$o->core_dir.'templates/theme/'), $list, $selected);
		
		foreach ($dirs as $dir) {
			$dir = trailingslashit($dir);
			if (file_exists($dir.$possible_plugin_filename) && is_file($dir.$possible_plugin_filename)) {
				$template = $dir.$possible_plugin_filename;
				break;
			}
		}

		return $template;
	}

	public static function page_add_meta_boxes($post_type, $post) {
		if (!post_type_supports($post_type, 'page-attributes')) return;

		remove_meta_box('pageparentdiv', null, 'side');
		add_meta_box(
			'qsot-pageparentdiv',
			'page' == $post_type ? __('Page Attributes') : __('Attributes'),
			array(__CLASS__, 'page_attributes_meta_box'),
			null,
			'side',
			'core'
		);
	}

	public static function page_draw_page_template_list($default) {
		$templates = apply_filters('qsot-get-page-template-list', array());
		ksort( $templates );
		foreach (array_keys( $templates ) as $template )
			: if ( $default == $templates[$template] )
				$selected = " selected='selected'";
			else
				$selected = '';
		echo "\n\t<option value='".$templates[$template]."' $selected>$template</option>";
		endforeach;
	}

	public static function page_templates_list($list, $selected) {
		$dirs = apply_filters('qsot-page-template-dirs', array(self::$o->core_dir.'templates/theme/'), $list, $selected);
		$regex = '#^.+\.php$#i';
		$add_list = array();

		foreach ($dirs as $dir) {
			try {
				$iter = new RegexIterator(
					new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator(
							$dir
						),
						RecursiveIteratorIterator::SELF_FIRST
					),
					$regex,
					RecursiveRegexIterator::GET_MATCH
				);

				// require every file found
				foreach ($iter as $fullpath => $file) {
					if (!preg_match('|Template Name:(.*)$|mi', file_get_contents($fullpath), $header))
						continue;
					$file = is_array($file) ? array_shift($file) : $file;
					$file = str_replace(trailingslashit($dir), '', $file);
					$add_list[$file] = _cleanup_header_comment($header[1]);
				}
			} catch (Exception $e) {}
		}

		$list = array_flip(array_merge($add_list, array_flip($list)));
		ksort($list);

		return $list;
	}

	/** @@@@OpenTickets - direct copy from /wp-admin/includes/meta-boxes.php - with a few modifications for readability and the changing of the function that draws the page list
	 * Display page attributes form fields.
	 *
	 * @since 2.7.0
	 *
	 * @param object $post
	 */
	function page_attributes_meta_box($post) {
		$post_type_object = get_post_type_object($post->post_type);
		if ( $post_type_object->hierarchical ):
			$dropdown_args = array(
				'post_type'        => $post->post_type,
				'exclude_tree'     => $post->ID,
				'selected'         => $post->post_parent,
				'name'             => 'parent_id',
				'show_option_none' => __('(no parent)'),
				'sort_column'      => 'menu_order, post_title',
				'echo'             => 0,
			);

			$dropdown_args = apply_filters( 'page_attributes_dropdown_pages_args', $dropdown_args, $post );
			$pages = wp_dropdown_pages( $dropdown_args );
			?>
			<?php if ( ! empty($pages) ): ?>
				<p><strong><?php _e('Parent') ?></strong></p>
				<label class="screen-reader-text" for="parent_id"><?php _e('Parent') ?></label>
				<?php echo $pages; ?>
			<?php endif; // pages ?>
		<?php endif; // hierarchial ?>
		<?php if ( 'page' == $post->post_type && 0 != count( apply_filters('qsot-page-templates-list', get_page_templates(), $template) ) ): ?>
			<?php $template = !empty($post->page_template) ? $post->page_template : false; ?>
			<p><strong><?php _e('Template') ?></strong></p>
			<label class="screen-reader-text" for="page_template"><?php _e('Page Template') ?></label>
			<select name="page_template" id="page_template">
				<option value='default'><?php _e('Default Template'); ?></option>
				<?php do_action('qsot-draw-page-template-list', $template); ?>
			</select>
		<?php endif; ?>
		<p><strong><?php _e('Order') ?></strong></p>
		<p>
			<label class="screen-reader-text" for="menu_order"><?php _e('Order') ?></label>
			<input name="menu_order" type="text" size="4" id="menu_order" value="<?php echo esc_attr($post->menu_order) ?>" />
		</p>
		<p><?php if ( 'page' == $post->post_type ) _e( 'Need help? Use the Help tab in the upper right of your screen.' ); ?></p>
	<?php
	}

	// copied from woocommerce/admin/post-types/writepanels/writepanel-order_data.php
	// modified to allow defaults
	public static function woocommerce_order_data_meta_box($post) {
		global $post, $wpdb, $thepostid, $theorder, $order_status, $woocommerce;

		$thepostid = absint( $post->ID );

		if ( ! is_object( $theorder ) )
			$theorder = new WC_Order( $thepostid );

		$order = $theorder;

		wp_nonce_field( 'woocommerce_save_data', 'woocommerce_meta_nonce' );

		// Custom user
		$customer_user = absint( get_post_meta( $post->ID, '_customer_user', true ) );

		// Order status
		$order_status = wp_get_post_terms( $post->ID, 'shop_order_status' );
		if ( $order_status ) {
			$order_status = current( $order_status );
			$order_status = sanitize_title( $order_status->slug );
		} else {
			$order_status = sanitize_title( apply_filters( 'woocommerce_default_order_status', 'pending' ) );
		}

		if ( empty( $post->post_title ) )
			$order_title = 'Order';
		else
			$order_title = $post->post_title;
		?>
		<style type="text/css">
			#post-body-content, #titlediv, #major-publishing-actions, #minor-publishing-actions, #visibility, #submitdiv { display:none }
		</style>
		<div class="panel-wrap woocommerce">
			<input name="post_title" type="hidden" value="<?php echo esc_attr( $order_title ); ?>" />
			<input name="post_status" type="hidden" value="publish" />
			<div id="order_data" class="panel">

				<h2><?php _e( 'Order Details', 'woocommerce' ); ?></h2>
				<p class="order_number"><?php

					echo __( 'Order number', 'woocommerce' ) . ' ' . esc_html( $order->get_order_number() ) . '. ';

					$ip_address = get_post_meta( $post->ID, '_customer_ip_address', true );

					if ( $ip_address )
						echo __( 'Customer IP:', 'woocommerce' ) . ' ' . esc_html( $ip_address );

				?></p>

				<div class="order_data_column_container">
					<div class="order_data_column">

						<h4><?php _e( 'General Details', 'woocommerce' ); ?></h4>

						<p class="form-field"><label for="order_status"><?php _e( 'Order status:', 'woocommerce' ) ?></label>
						<select id="order_status" name="order_status" class="chosen_select">
							<?php
								$statuses = (array) get_terms( 'shop_order_status', array( 'hide_empty' => 0, 'orderby' => 'id' ) );
								foreach ( $statuses as $status ) {
									echo '<option value="' . esc_attr( $status->slug ) . '" ' . selected( $status->slug, $order_status, false ) . '>' . esc_html__( $status->name, 'woocommerce' ) . '</option>';
								}
							?>
						</select></p>

						<p class="form-field last"><label for="order_date"><?php _e( 'Order Date:', 'woocommerce' ) ?></label>
							<input type="text" class="date-picker-field" name="order_date" id="order_date" maxlength="10" value="<?php echo date_i18n( 'Y-m-d', strtotime( $post->post_date ) ); ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" /> @ <input type="text" class="hour" placeholder="<?php _e( 'h', 'woocommerce' ) ?>" name="order_date_hour" id="order_date_hour" maxlength="2" size="2" value="<?php echo date_i18n( 'H', strtotime( $post->post_date ) ); ?>" pattern="\-?\d+(\.\d{0,})?" />:<input type="text" class="minute" placeholder="<?php _e( 'm', 'woocommerce' ) ?>" name="order_date_minute" id="order_date_minute" maxlength="2" size="2" value="<?php echo date_i18n( 'i', strtotime( $post->post_date ) ); ?>" pattern="\-?\d+(\.\d{0,})?" />
						</p>

						<p class="form-field form-field-wide">
							<label for="customer_user"><?php _e( 'Customer:', 'woocommerce' ) ?></label>
							<select id="customer_user" name="customer_user" class="ajax_chosen_select_customer">
								<option value=""><?php _e( 'Guest', 'woocommerce' ) ?></option>
								<?php
									if ( $customer_user ) {
										$user = get_user_by( 'id', $customer_user );
										echo '<option value="' . esc_attr( $user->ID ) . '" ' . selected( 1, 1, false ) . '>' . esc_html( $user->display_name ) . ' (#' . absint( $user->ID ) . ' &ndash; ' . esc_html( $user->user_email ) . ')</option>';
									}
								?>
							</select>
							<?php

							// Ajax Chosen Customer Selectors JS
							$woocommerce->add_inline_js( "
								jQuery('select.ajax_chosen_select_customer').ajaxChosen({
										method: 		'GET',
										url: 			'" . admin_url('admin-ajax.php') . "',
										dataType: 		'json',
										afterTypeDelay: 100,
										minTermLength: 	1,
										data:		{
											action: 	'woocommerce_json_search_customers',
										security: 	'" . wp_create_nonce("search-customers") . "'
										}
								}, function (data) {

									var terms = {};

										$.each(data, function (i, val) {
												terms[i] = val;
										});

										return terms;
								});
							" );
							?>

							<?php do_action('woocommerce_after_customer_user', $customer_user, $post, $post_id) ?>
						</p>

						<?php if ( get_option( 'woocommerce_enable_order_comments' ) != 'no' ) : ?>

							<p class="form-field form-field-wide"><label for="excerpt"><?php _e( 'Customer Note:', 'woocommerce' ) ?></label>
							<textarea rows="1" cols="40" name="excerpt" tabindex="6" id="excerpt" placeholder="<?php _e( 'Customer\'s notes about the order', 'woocommerce' ); ?>"><?php echo wp_kses_post( $post->post_excerpt ); ?></textarea></p>

						<?php endif; ?>

						<?php do_action( 'woocommerce_admin_order_data_after_order_details', $order ); ?>

					</div>
					<div class="order_data_column">
						<h4><?php _e( 'Billing Details', 'woocommerce' ); ?> <a class="edit_address" href="#">(<?php _e( 'Edit', 'woocommerce' ) ;?>)</a></h4>
						<?php
							$billing_data = apply_filters('woocommerce_admin_billing_fields', array(
								'first_name' => array(
									'label' => __( 'First Name', 'woocommerce' ),
									'show'	=> false
									),
								'last_name' => array(
									'label' => __( 'Last Name', 'woocommerce' ),
									'show'	=> false
									),
								'company' => array(
									'label' => __( 'Company', 'woocommerce' ),
									'show'	=> false
									),
								'address_1' => array(
									'label' => __( 'Address 1', 'woocommerce' ),
									'show'	=> false
									),
								'address_2' => array(
									'label' => __( 'Address 2', 'woocommerce' ),
									'show'	=> false
									),
								'city' => array(
									'label' => __( 'City', 'woocommerce' ),
									'show'	=> false
									),
								'postcode' => array(
									'label' => __( 'Postcode', 'woocommerce' ),
									'show'	=> false
									),
								'country' => array(
									'label' => __( 'Country', 'woocommerce' ),
									'show'	=> false,
									'type'	=> 'select',
									'options' => array( '' => __( 'Select a country&hellip;', 'woocommerce' ) ) + $woocommerce->countries->get_allowed_countries()
									),
								'state' => array(
									'label' => __( 'State/County', 'woocommerce' ),
									'show'	=> false
									),
								'email' => array(
									'label' => __( 'Email', 'woocommerce' ),
									),
								'phone' => array(
									'label' => __( 'Phone', 'woocommerce' ),
									),
								) );

							// Display values
							echo '<div class="address">';

								if ( $order->get_formatted_billing_address() )
									echo '<p><strong>' . __( 'Address', 'woocommerce' ) . ':</strong><br/> ' . $order->get_formatted_billing_address() . '</p>';
								else
									echo '<p class="none_set"><strong>' . __( 'Address', 'woocommerce' ) . ':</strong> ' . __( 'No billing address set.', 'woocommerce' ) . '</p>';

								foreach ( $billing_data as $key => $field ) {
									if ( isset( $field['show'] ) && $field['show'] === false )
										continue;
									$field_name = 'billing_' . $key;
									if ( $order->$field_name )
										echo '<p><strong>' . esc_html( $field['label'] ) . ':</strong> ' . esc_html( $order->$field_name ) . '</p>';
								}

							echo '</div>';

							// Display form
							echo '<div class="edit_address"><p><button class="button load_customer_billing">'.__( 'Load billing address', 'woocommerce' ).'</button></p>';

							foreach ( $billing_data as $key => $field ) {
								if ( ! isset( $field['type'] ) )
									$field['type'] = 'text';
								switch ( $field['type'] ) {
									case "select" :
										$a = array( 'id' => '_billing_' . $key, 'label' => $field['label'], 'options' => $field['options'] );
										if (isset($field['value'])) $a['value'] = $field['value']; // allow defaults
										woocommerce_wp_select($a);
									break;
									default :
										$a = array( 'id' => '_billing_' . $key, 'label' => $field['label'] );
										if (isset($field['value'])) $a['value'] = $field['value']; // allow defaults
										woocommerce_wp_text_input($a);
									break;
								}
							}
							
							// only allow updating the user's billing information if we require users to signup, creating user accounts
							if (get_option('woocommerce_enable_guest_checkout') != 'yes'): 
								echo '<p class="form-field _billing_sync_customer_address">'
									.'<input type="hidden" name="_billing_sync_customer_address" value="0" />'
									.'<input type="checkbox" name="_billing_sync_customer_address" value="1" class="billing-sync-customer-address" />'
									.'<span for="_billing_sync_customer_address">Update Customer Address</span>'
								.'</p>';
							endif;

							echo '</div>';

							do_action( 'woocommerce_admin_order_data_after_billing_address', $order );
						?>
					</div>
					<div class="order_data_column">

						<h4><?php _e( 'Shipping Details', 'woocommerce' ); ?> <a class="edit_address" href="#">(<?php _e( 'Edit', 'woocommerce' ) ;?>)</a></h4>
						<?php
							$shipping_data = apply_filters('woocommerce_admin_shipping_fields', array(
								'first_name' => array(
									'label' => __( 'First Name', 'woocommerce' ),
									'show'	=> false
									),
								'last_name' => array(
									'label' => __( 'Last Name', 'woocommerce' ),
									'show'	=> false
									),
								'company' => array(
									'label' => __( 'Company', 'woocommerce' ),
									'show'	=> false
									),
								'address_1' => array(
									'label' => __( 'Address 1', 'woocommerce' ),
									'show'	=> false
									),
								'address_2' => array(
									'label' => __( 'Address 2', 'woocommerce' ),
									'show'	=> false
									),
								'city' => array(
									'label' => __( 'City', 'woocommerce' ),
									'show'	=> false
									),
								'postcode' => array(
									'label' => __( 'Postcode', 'woocommerce' ),
									'show'	=> false
									),
								'country' => array(
									'label' => __( 'Country', 'woocommerce' ),
									'show'	=> false,
									'type'	=> 'select',
									'options' => array( '' => __( 'Select a country&hellip;', 'woocommerce' ) ) + $woocommerce->countries->get_allowed_countries()
									),
								'state' => array(
									'label' => __( 'State/County', 'woocommerce' ),
									'show'	=> false
									),
								) );

							// Display values
							echo '<div class="address">';

								if ( $order->get_formatted_shipping_address() )
									echo '<p><strong>' . __( 'Address', 'woocommerce' ) . ':</strong><br/> ' . $order->get_formatted_shipping_address() . '</p>';
								else
									echo '<p class="none_set"><strong>' . __( 'Address', 'woocommerce' ) . ':</strong> ' . __( 'No shipping address set.', 'woocommerce' ) . '</p>';

								if ( $shipping_data ) foreach ( $shipping_data as $key => $field ) {
									if ( isset( $field['show'] ) && $field['show'] === false )
										continue;
									$field_name = 'shipping_' . $key;
									if ( $order->$field_name )
										echo '<p><strong>' . esc_html( $field['label'] ) . ':</strong> ' . esc_html( $order->$field_name ) . '</p>';
								}

							echo '</div>';

							// Display form
							echo '<div class="edit_address"><p><button class="button load_customer_shipping">' . __( 'Load shipping address', 'woocommerce' ) . '</button> <button class="button billing-same-as-shipping">'. __( 'Copy from billing', 'woocommerce' ) . '</button></p>';

							if ( $shipping_data ) foreach ( $shipping_data as $key => $field ) {
								if ( ! isset( $field['type'] ) )
									$field['type'] = 'text';
								switch ( $field['type'] ) {
									case "select" :
										woocommerce_wp_select( array( 'id' => '_shipping_' . $key, 'label' => $field['label'], 'options' => $field['options'] ) );
									break;
									default :
										woocommerce_wp_text_input( array( 'id' => '_shipping_' . $key, 'label' => $field['label'] ) );
									break;
								}
							}
							
							// only allow updating the user's billing information if we require users to signup, creating user accounts
							if (get_option('woocommerce_enable_guest_checkout') != 'yes'): 
								echo '<p class="form-field _shipping_sync_customer_address">'
									.'<input type="hidden" name="_shipping_sync_customer_address" value="0" />'
									.'<input type="checkbox" name="_shipping_sync_customer_address" value="1" class="shipping-sync-customer-address" />'
									.'<span for="_shipping_sync_customer_address">Update Customer Address</span>'
								.'</p>';
							endif;

							echo '</div>';

							do_action( 'woocommerce_admin_order_data_after_shipping_address', $order );
						?>
					</div>
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
	}

	public static function update_order_user_addresses($post_id, $post) {
		if ($post->post_type != 'shop_order' && !empty($_POST)) return;

		$billing_update = isset($_POST['_billing_sync_customer_address']) && !empty($_POST['_billing_sync_customer_address']);
		$shipping_update = isset($_POST['_shipping_sync_customer_address']) && !empty($_POST['_shipping_sync_customer_address']);

		if (!$billing_update && !$shipping_update) return;

		$meta = $new = $cur = array();
		foreach (array('billing', 'shipping') as $prefix) {
			$update_key = $prefix.'_update';
			if ($$update_key) {
				foreach (array('first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'state', 'country', 'email', 'phone') as $suffix) {
					$new[$prefix.'_'.$suffix] = isset($_POST['_'.$prefix.'_'.$suffix]) ? $_POST['_'.$prefix.'_'.$suffix] : '';
					$cur[$prefix.'_'.$suffix] = get_post_meta($post_id, $prefix.'_'.$suffix, true);
				}
			}
		}

		foreach ($cur as $k => $v) {
			if ($v != $new[$k]) {
				$meta[$k] = $new[$k];
			}
		}

		if (!empty($meta)) {
			if (isset($meta['billing_first_name'])) $meta['first_name'] = $meta['billing_first_name'];
			if (isset($meta['billing_last_name'])) $meta['last_name'] = $meta['billing_last_name'];

			$customer_user_id = (int)$_POST['customer_user'];
			if (empty($customer_user_id)) return;
			$user = new WP_User($customer_user_id);
			if (!is_object($user) || !isset($user->ID)) return;

			foreach ($meta as $k => $v) update_user_meta($user->ID, $k, $v);

			if (isset($meta['first_name'], $meta['last_name']) && !empty($meta['first_name']) && !empty($meta['last_name'])) {
				global $wpdb;
				$wpdb->update($wpdb->users, array('display_name' => $meta['first_name'].' '.$meta['last_name']), array('id' => $user->ID));
			}
		}
	}
}

if (defined('ABSPATH') && function_exists('add_action')) {
	qsot_core_hacks::pre_init();
}
